<?php
/** Persistência de alertas: migração sql/pi_vi_alertas_persistentes.sql obrigatória. */
require_once __DIR__.'/sd_service.php';
function sdNotifyEsc($s): string { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function sdNotifyHistory(mysqli $db,int $id,string $action,string $actor,?string $note=null): void {
 $s=$db->prepare('INSERT INTO sd_notification_history(notification_id,action,actor,note) VALUES(?,?,?,?)');
 $s->bind_param('isss',$id,$action,$actor,$note);$s->execute();$s->close();
}
function sdNotifySync(mysqli $db,array $alerts): array {
 $db->begin_transaction();$created=0;$reopened=0;$resolved=0;
 try {
  $active=[];
  $sel=$db->prepare('SELECT id,resolved_at FROM sd_notifications WHERE alert_key=? FOR UPDATE');
  $ins=$db->prepare('INSERT INTO sd_notifications(alert_key,kind,reference_id,severity,title,detail) VALUES(?,?,?,?,?,?)');
  $upd=$db->prepare('UPDATE sd_notifications SET last_detected_at=NOW(),resolved_at=NULL,acknowledged_at=NULL,acknowledged_by=NULL,severity=?,title=?,detail=? WHERE id=?');
  $touch=$db->prepare('UPDATE sd_notifications SET last_detected_at=NOW(),severity=?,title=?,detail=? WHERE id=?');
  foreach($alerts as $a){
   $key=(string)$a['key'];$active[$key]=true;
   $sel->bind_param('s',$key);$sel->execute();$row=$sel->get_result()->fetch_assoc();
   if(!$row){$ins->bind_param('ssssss',$key,$a['kind'],$a['ref'],$a['severity'],$a['title'],$a['detail']);$ins->execute();sdNotifyHistory($db,(int)$db->insert_id,'created','system:scheduler');$created++;}
   elseif($row['resolved_at']!==null){$id=(int)$row['id'];$upd->bind_param('sssi',$a['severity'],$a['title'],$a['detail'],$id);$upd->execute();sdNotifyHistory($db,$id,'reopened','system:scheduler');$reopened++;}
   else {$id=(int)$row['id'];$touch->bind_param('sssi',$a['severity'],$a['title'],$a['detail'],$id);$touch->execute();}
  }
  $sel->close();$ins->close();$upd->close();$touch->close();
  // Resolva somente as categorias avaliadas por esta execução.
  $q=$db->query('SELECT id,alert_key FROM sd_notifications WHERE resolved_at IS NULL FOR UPDATE');
  $close=$db->prepare('UPDATE sd_notifications SET resolved_at=NOW() WHERE id=? AND resolved_at IS NULL');
  while($r=$q->fetch_assoc()){
   if(isset($active[$r['alert_key']]))continue;
   $id=(int)$r['id'];$close->bind_param('i',$id);$close->execute();
   sdNotifyHistory($db,$id,'resolved','system:scheduler');$resolved++;
  }$close->close();$db->commit();return compact('created','reopened','resolved');
 }catch(Throwable $e){$db->rollback();throw $e;}
}
function sdNotificationCandidates(mysqli $db,int $inactiveMinutes=30): array {
 $out=[];$s=$db->query("SELECT id,protocol,priority,status,created_at,first_response_at,resolved_at FROM sd_tickets WHERE status NOT IN ('resolvido','fechado')");
 $now=new DateTimeImmutable();
 while($t=$s->fetch_assoc()){
  $sla=sdSlaStatus($t,$now);
  foreach(['resposta'=>'response','resolucao'=>'resolution'] as $pt=>$en){
   // Resposta já prestada não gera alertas pendentes; SLA histórico é conservado nos relatórios.
   if($en==='response' && !empty($t['first_response_at']))continue;
   $deadline=new DateTimeImmutable($sla[$en.'_deadline']);$mins=($deadline->getTimestamp()-$now->getTimestamp())/60;
   if($mins>30)continue;
   $severity=$mins<0?'critico':'aviso';$kind='sla_'.$pt;
   $out[]=['key'=>$kind.':'.$t['id'],'kind'=>$kind,'ref'=>(string)$t['id'],'severity'=>$severity,'title'=>($mins<0?'SLA vencido: ':'SLA próximo: ').$t['protocol'],'detail'=>ucfirst($pt).' até '.$sla[$en.'_deadline'].'; prioridade '.$t['priority']];
  }
 }
 $st=$db->prepare('SELECT device_id,name,last_seen FROM iot_devices WHERE last_seen IS NULL OR last_seen < DATE_SUB(NOW(),INTERVAL ? MINUTE)');
 $st->bind_param('i',$inactiveMinutes);$st->execute();$devices=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
 foreach($devices as $d){$out[]=['key'=>'dispositivo_inativo:'.hash('sha256',$d['device_id']),'kind'=>'dispositivo_inativo','ref'=>$d['device_id'],'severity'=>'critico','title'=>'Sem comunicação: '.mb_substr((string)$d['name'],0,140),'detail'=>'Dispositivo '.$d['device_id'].'; última comunicação '.($d['last_seen']??'nunca')];}
 return $out;
}
