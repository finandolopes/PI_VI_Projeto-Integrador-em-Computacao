<?php
/** Homologação destructiva EXCLUSIVAMENTE em banco com nome *_test. */
if(PHP_SAPI!=='cli')exit('CLI only');
require_once __DIR__.'/../php/bootstrap_env.php';
if(!str_ends_with((string)getenv('DB_NAME'),'_test')||getenv('V7_TEST_ALLOW_DB_MUTATION')!=='yes'){
fwrite(STDERR,"RECUSADO: use banco *_test e V7_TEST_ALLOW_DB_MUTATION=yes\n");exit(3);}
require_once __DIR__.'/../php/crm_service_v7.php';require_once __DIR__.'/../php/finance_service_v7.php';require_once __DIR__.'/../php/iot_service.php';require_once __DIR__.'/../php/sd_service.php';
$db=cfDb();$db->query('SET FOREIGN_KEY_CHECKS=1');
$tag='v7qa_'.bin2hex(random_bytes(5));$device='esp32_'.$tag;$clientId=null;$requestId=null;$ticketId=null;$ruleId=null;$financeId=null;
function qa(bool $okay,string $label):void{if(!$okay)throw new RuntimeException('Falhou: '.$label);echo "OK: $label\n";}
try {
 qa(cfEngine($db,'clientes')==='innodb'&&cfEngine($db,'requisicoes')==='innodb','Banco teste com clientes/requisicoes InnoDB');
 $name='Cliente QA '.$tag;$mail=$tag.'@example.invalid';
 $s=$db->prepare("INSERT INTO clientes(nome,email,status) VALUES(?,?,'ativo')");$s->bind_param('ss',$name,$mail);$s->execute();$clientId=$db->insert_id;$s->close();
 $type='Consignado teste';$category='Aposentado';$date=date('Y-m-d');$time='09:00:00';
 $s=$db->prepare("INSERT INTO requisicoes(id_cliente,horario_contato,cotacao,contratacao,tipo,categoria,data_requisicao,status) VALUES(?,?,'Sim','Não',?,?,?,'pendente')");$s->bind_param('issss',$clientId,$time,$type,$category,$date);$s->execute();$requestId=$db->insert_id;$s->close();
 qa(cfCrmDetail($db,$requestId)!==null,'Cliente vinculado à solicitação');
 putenv('CRM_V7_WRITE_ENABLED=1');$r=cfCrmSetStage($db,$requestId,'em_analise','Verificação integrada',$tag);qa($r['status']==='em_analise','Mudança de CRM + histórico');
 try{cfCrmSetStage($db,$requestId,'aprovado','Teste simulado',$tag);qa(true,'Mudança para estado aprovado');}catch(Throwable $e){throw $e;}
 try{cfCrmSetStage($db,$requestId,'pendente','Não permitido',$tag);throw new RuntimeException('Transição ilegal aceita');}catch(InvalidArgumentException $e){qa(true,'Rejeição de transição ilegal');}
 putenv('FINANCE_V7_WRITE_ENABLED=1');$key=hash('sha256',$tag);
 $financeId=cfFinanceCreateDraft($db,['idempotency_key'=>$key,'entry_type'=>'receber','description'=>'Rascunho teste','amount'=>'34.25','client_id'=>$clientId],$tag);
 qa($financeId>0,'Rascunho financeiro criado');try{cfFinanceCreateDraft($db,['idempotency_key'=>$key,'entry_type'=>'receber','description'=>'Rascunho teste','amount'=>'34.25','client_id'=>$clientId],$tag);throw new RuntimeException('Duplicidade aceita');}catch(DomainException $e){qa(true,'Idempotência financeira');}
 $metric='temperatura';$op='gt';$limit=30.0;$priority='alta';$cooldown=600;$rname='Regra QA '.$tag;
 $s=$db->prepare('INSERT INTO sd_iot_rules(name,device_id,metric,operator,threshold,priority,cooldown_seconds,enabled) VALUES(?,?,?,?,?,?,?,1)');$s->bind_param('ssssdsi',$rname,$device,$metric,$op,$limit,$priority,$cooldown);$s->execute();$ruleId=$db->insert_id;$s->close();
 $reading=ingestIotPayload($db,['device_id'=>$device,'metric'=>$metric,'value'=>40,'min'=>0,'max'=>30]);
 $events=sdEvalIotRules($db,$reading);qa(count($events)===1&&$events[0]['outcome']==='created','Alerta gera chamado');$ticketId=(int)$events[0]['ticket_id'];
 $reading2=ingestIotPayload($db,['device_id'=>$device,'metric'=>$metric,'value'=>42]);$events2=sdEvalIotRules($db,$reading2);qa(count($events2)===1&&$events2[0]['outcome']==='suppressed','Cooldown suprime duplicidade');
 sdUpdate($db,$ticketId,'em_atendimento',$tag,'Assumi para verificar equipamento',$tag);
 sdUpdate($db,$ticketId,'resolvido',$tag,'Equipamento revisado e restabelecido',$tag);
 $s=$db->prepare('SELECT status FROM sd_tickets WHERE id=?');$s->bind_param('i',$ticketId);$s->execute();$status=$s->get_result()->fetch_assoc()['status'];$s->close();qa($status==='resolvido','Atendimento e resolução registrados');
 $s=$db->prepare('SELECT COUNT(*) n FROM sd_ticket_history WHERE ticket_id=?');$s->bind_param('i',$ticketId);$s->execute();$count=(int)$s->get_result()->fetch_assoc()['n'];$s->close();qa($count>=3,'Trilha auditável do chamado');
} finally {
 // Limpa apenas registros QA criados neste teste, protegidos por IDs.
 foreach([
  ['DELETE FROM sd_iot_rule_events WHERE rule_id=?',$ruleId],
  ['DELETE FROM sd_tickets WHERE id=?',$ticketId],
  ['DELETE FROM sd_iot_rules WHERE id=?',$ruleId],
  ['DELETE FROM iot_telemetry WHERE device_id=?',$device],
  ['DELETE FROM iot_devices WHERE device_id=?',$device],
  ['DELETE FROM crm_activity_v7 WHERE request_id=?',$requestId],
  ['DELETE FROM cf_operational_events_v7 WHERE actor=?',$tag],
  ['DELETE FROM finance_entries_v7 WHERE id=?',$financeId],
  ['DELETE FROM requisicoes WHERE id_requisicao=?',$requestId],
  ['DELETE FROM clientes WHERE id_cliente=?',$clientId]
 ] as [$sql,$val]){if($val===null)continue;try{$s=$db->prepare($sql);$s->bind_param(is_int($val)?'i':'s',$val);$s->execute();$s->close();}catch(Throwable $e){fwrite(STDERR,'Limpeza QA parcial: '.$e->getMessage()."\n");}}
}
echo "TESTE INTEGRADO V7 APROVADO (banco teste)\n";
