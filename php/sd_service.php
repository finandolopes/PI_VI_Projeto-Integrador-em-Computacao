<?php
/** Service Desk PI VI. Compatível com mysqli e esquema em sql/pi_vi_service_desk.sql. */
require_once __DIR__.'/sd_policies.php';
function sdSchema(mysqli $db): void {
    static $ready = false;
    if ($ready) return;
    $result = $db->query("SHOW TABLES LIKE 'sd_tickets'");
    if (!$result || $result->num_rows === 0) throw new RuntimeException('Instale sql/pi_vi_service_desk.sql antes de usar o Service Desk.');
    $ready = true;
}
function sdProtocol(): string { return 'PI6-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))); }
function sdAudit(mysqli $db,int $id,string $actor,string $event,?string $old=null,?string $new=null,?string $note=null): void {
    $s=$db->prepare('INSERT INTO sd_ticket_history(ticket_id,actor,event_type,old_value,new_value,note) VALUES(?,?,?,?,?,?)');
    $s->bind_param('isssss',$id,$actor,$event,$old,$new,$note); $s->execute();$s->close();
}
function sdCreate(mysqli $db,array $data,string $origin='manual'): array {
    sdSchema($db);
    $name=trim((string)($data['name']??''));$email=trim((string)($data['email']??''));
    $title=trim((string)($data['title']??''));$description=trim((string)($data['description']??''));
    $category=trim((string)($data['category']??'Geral'));
    $priority=(string)($data['priority']??'media');
    if (!in_array($priority,['baixa','media','alta','critica'],true)) $priority='media';
    if (!$name||mb_strlen($name)>130||!$title||mb_strlen($title)>180||!$description||mb_strlen($description)>10000||mb_strlen($category)>80||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Revise os campos obrigatórios do chamado.');
    $token=bin2hex(random_bytes(24));$hash=hash('sha256',$token);$protocol=sdProtocol();
    $device=$data['device_id']??null; $telemetry=$data['telemetry_id']??null; $rule=$data['rule_id']??null;
    $s=$db->prepare('INSERT INTO sd_tickets(protocol,access_token_hash,origin,requester_name,requester_email,title,description,category,priority,device_id,telemetry_id,rule_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $s->bind_param('ssssssssssii',$protocol,$hash,$origin,$name,$email,$title,$description,$category,$priority,$device,$telemetry,$rule);
    if(!$s->execute())throw new RuntimeException('Não foi possível registrar o chamado.');
    $id=(int)$db->insert_id;$s->close();sdAudit($db,$id,$origin==='iot'?'system:iot':'public','created',null,'aberto');
    return ['id'=>$id,'protocol'=>$protocol,'consultation_token'=>$token];
}
function sdLookup(mysqli $db,string $protocol,string $token): ?array {
    sdSchema($db);if(!preg_match('/^PI6-[0-9]{8}-[0-9A-F]{10}$/',$protocol)||!preg_match('/^[a-f0-9]{48}$/',$token))return null;
    $hash=hash('sha256',$token);$s=$db->prepare('SELECT protocol,origin,title,category,priority,status,created_at,updated_at,first_response_at,resolved_at FROM sd_tickets WHERE protocol=? AND access_token_hash=?');
    $s->bind_param('ss',$protocol,$hash);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();return $row?:null;
}
function sdUpdate(mysqli $db,int $id,string $status,string $assignee,string $note,string $actor): void {
    sdSchema($db);
    if(!in_array($status,['aberto','em_atendimento','aguardando','resolvido','fechado'],true))throw new InvalidArgumentException('Status inválido');
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT status,assigned_to FROM sd_tickets WHERE id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$old=$s->get_result()->fetch_assoc();$s->close();if(!$old)throw new InvalidArgumentException('Chamado não encontrado'); sdValidateTransition((string)$old['status'],$status,$note);
        $s=$db->prepare("UPDATE sd_tickets SET status=?,assigned_to=?,first_response_at=IF(first_response_at IS NULL AND ? <> 'aberto', NOW(),first_response_at),resolved_at=CASE WHEN ? IN ('resolvido','fechado') THEN COALESCE(resolved_at,NOW()) ELSE NULL END WHERE id=?");
        $s->bind_param('ssssi',$status,$assignee,$status,$status,$id);$s->execute();$s->close();
        sdAudit($db,$id,$actor,'updated',(string)$old['status'].' | '.(string)$old['assigned_to'],$status.' | '.$assignee,mb_substr($note,0,3000));$db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function sdEvalIotRules(mysqli $db,array $reading): array {
    sdSchema($db);$matched=[];
    $device=(string)$reading['device_id'];$metric=(string)$reading['metric'];$value=(float)$reading['value'];$telemetry=(int)$reading['id'];
    $s=$db->prepare('SELECT id,name,operator,threshold,priority,cooldown_seconds FROM sd_iot_rules WHERE enabled=1 AND metric=? AND (device_id IS NULL OR device_id=?)');
    $s->bind_param('ss',$metric,$device);$s->execute();$rules=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    foreach($rules as $r){
        $limit=(float)$r['threshold'];$trigger=match($r['operator']){'gt'=>$value>$limit,'gte'=>$value>=$limit,'lt'=>$value<$limit,'lte'=>$value<=$limit,default=>false};
        if(!$trigger)continue;
        // Lock por dispositivo/regra: impede incidentes duplicados sob ingestão concorrente.
        $lockName='sd:'.sha1($device.':'.$r['id']);$lock=$db->prepare('SELECT GET_LOCK(?,5) AS got');$lock->bind_param('s',$lockName);$lock->execute();$got=(int)$lock->get_result()->fetch_assoc()['got'];$lock->close();
        if($got!==1)throw new RuntimeException('Não foi possível sincronizar o processamento de eventos.');
        try {
            $db->begin_transaction();$ruleId=(int)$r['id'];$cooldown=(int)$r['cooldown_seconds'];
            $q=$db->prepare("SELECT ticket_id,TIMESTAMPDIFF(SECOND,created_at,NOW()) age FROM sd_iot_rule_events WHERE device_id=? AND rule_id=? AND outcome='created' ORDER BY id DESC LIMIT 1");
            $q->bind_param('si',$device,$ruleId);$q->execute();$recent=$q->get_result()->fetch_assoc();$q->close();
            $existing=null;$q=$db->prepare("SELECT id FROM sd_tickets WHERE device_id=? AND rule_id=? AND status NOT IN ('resolvido','fechado') ORDER BY id DESC LIMIT 1");$q->bind_param('si',$device,$ruleId);$q->execute();$existing=$q->get_result()->fetch_assoc();$q->close();
            $suppressed=$existing!==null||($recent!==null&&(int)$recent['age']<$cooldown);$ticketId=$existing['id']??null;
            if(!$suppressed){$t=sdCreate($db,['name'=>'Monitor IoT','title'=>'Alerta: '.$r['name'].' ('.$device.')','description'=>'Regra '.$r['name'].' acionada. Métrica '.$metric.' = '.$value.'; limite '.$r['operator'].' '.$limit,'category'=>'IoT','priority'=>$r['priority'],'device_id'=>$device,'telemetry_id'=>$telemetry,'rule_id'=>$ruleId],'iot');$ticketId=$t['id'];}
            $outcome=$suppressed?'suppressed':'created';$e=$db->prepare('INSERT INTO sd_iot_rule_events(rule_id,device_id,telemetry_id,ticket_id,outcome) VALUES(?,?,?,?,?)');$e->bind_param('isiis',$ruleId,$device,$telemetry,$ticketId,$outcome);$e->execute();$e->close();$db->commit();
            $matched[]=['rule_id'=>$ruleId,'outcome'=>$outcome,'ticket_id'=>$ticketId];
        }catch(Throwable $e){$db->rollback();throw $e;}finally{$release=$db->prepare('SELECT RELEASE_LOCK(?)');$release->bind_param('s',$lockName);$release->execute();$release->close();}
    }
    return $matched;
}

/** SLA do protótipo: minutos corridos; são metas sugeridas, não cláusulas contratuais. */
function sdSlaTargets(string $priority): array {
    $defaults = ['baixa'=>[240,2880],'media'=>[120,1440],'alta'=>[60,480],'critica'=>[15,120]];
    $targets = $defaults[$priority] ?? $defaults['media'];
    $key = strtoupper($priority);
    $response = getenv('SD_SLA_'.$key.'_RESPONSE_MIN');
    $resolution = getenv('SD_SLA_'.$key.'_RESOLUTION_MIN');
    if ($response !== false && ctype_digit($response) && (int)$response > 0) $targets[0] = (int)$response;
    if ($resolution !== false && ctype_digit($resolution) && (int)$resolution > 0) $targets[1] = (int)$resolution;
    return $targets;
}
function sdSlaStatus(array $ticket, ?DateTimeImmutable $now = null): array {
    $now ??= new DateTimeImmutable();
    $opened = new DateTimeImmutable($ticket['created_at']);
    [$responseMinutes,$resolutionMinutes] = sdSlaTargets((string)$ticket['priority']);
    $first = empty($ticket['first_response_at']) ? $now : new DateTimeImmutable($ticket['first_response_at']);
    $end = empty($ticket['resolved_at']) ? $now : new DateTimeImmutable($ticket['resolved_at']);
    $responseElapsed = max(0, ($first->getTimestamp() - $opened->getTimestamp())/60);
    $resolutionElapsed = max(0, ($end->getTimestamp() - $opened->getTimestamp())/60);
    return [
        'response_minutes_target'=>$responseMinutes,
        'resolution_minutes_target'=>$resolutionMinutes,
        'response_breached'=>$responseElapsed > $responseMinutes,
        'resolution_breached'=>$resolutionElapsed > $resolutionMinutes,
        'response_deadline'=>$opened->modify('+'.$responseMinutes.' minutes')->format('Y-m-d H:i:s'),
        'resolution_deadline'=>$opened->modify('+'.$resolutionMinutes.' minutes')->format('Y-m-d H:i:s'),
    ];
}


/** Conjunto de métricas de SLA calculadas sobre tickets completos, inclusive encerrados.
 * Horas corridas; sem substituir contratos reais de SLA. */
function sdSlaSummary(array $tickets, ?DateTimeImmutable $now = null): array {
    $now ??= new DateTimeImmutable();
    $counts=['total'=>0,'overdue_response'=>0,'overdue_resolution'=>0,'within_sla'=>0,
             'pending'=>0,'completed'=>0,'by_priority'=>[],'by_origin'=>[]];
    foreach ($tickets as $ticket) {
        $status=(string)($ticket['status']??'aberto');
        $priority=(string)($ticket['priority']??'media');
        $origin=(string)($ticket['origin']??'manual');
        $counts['total']++;
        $counts['by_priority'][$priority]=($counts['by_priority'][$priority]??0)+1;
        $counts['by_origin'][$origin]=($counts['by_origin'][$origin]??0)+1;
        if(in_array($status,['resolvido','fechado'],true)) $counts['completed']++;
        else $counts['pending']++;
        $sla=sdSlaStatus($ticket,$now);
        if($sla['response_breached'])$counts['overdue_response']++;
        if($sla['resolution_breached'])$counts['overdue_resolution']++;
        if(!$sla['response_breached']&&!$sla['resolution_breached'])$counts['within_sla']++;
    }
    $counts['compliance_percent']=$counts['total']>0?round(100*$counts['within_sla']/$counts['total'],1):null;
    return $counts;
}
