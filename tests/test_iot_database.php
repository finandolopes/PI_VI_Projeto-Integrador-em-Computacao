<?php
/** Executar exclusivamente em banco de testes: DB_* e IOT_TEST_DB=1 */
if(getenv('IOT_TEST_DB')!=='1'){fwrite(STDERR,"Defina IOT_TEST_DB=1 somente em banco descartável de homologação\n");exit(2);}
require_once __DIR__.'/../php/sd_service.php';
require_once __DIR__.'/../php/iot_service.php';
$db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',getenv('DB_NAME')?:'',(int)(getenv('DB_PORT')?:3306));
if($db->connect_error)throw new RuntimeException('Falha banco de testes');
$db->set_charset('utf8mb4');sdSchema($db);
$device='pi6-test-'.bin2hex(random_bytes(4));$metric='temperatura';
$r=$db->prepare("INSERT INTO sd_iot_rules(name,device_id,metric,operator,threshold,priority,cooldown_seconds,enabled) VALUES('Teste E2E',?,'temperatura','gt',30,'alta',900,1)");$r->bind_param('s',$device);$r->execute();$rid=$db->insert_id;$r->close();
try {
 $low=ingestIotPayload($db,['device_id'=>$device,'metric'=>$metric,'value'=>20]);
 $a=sdEvalIotRules($db,$low);if(count($a)!==0)throw new RuntimeException('Gerou falso positivo');
 $high=ingestIotPayload($db,['device_id'=>$device,'metric'=>$metric,'value'=>45]);
 $b=sdEvalIotRules($db,$high);if(count($b)!==1||$b[0]['outcome']!=='created')throw new RuntimeException('Não criou incidente');
 $again=ingestIotPayload($db,['device_id'=>$device,'metric'=>$metric,'value'=>46]);
 $c=sdEvalIotRules($db,$again);if(count($c)!==1||$c[0]['outcome']!=='suppressed')throw new RuntimeException('Cooldown falhou');
 $id=(int)$b[0]['ticket_id']; sdUpdate($db,$id,'em_atendimento','Técnico de Teste','Atendimento iniciado','test:technician');
 sdUpdate($db,$id,'resolvido','Técnico de Teste','Incidente resolvido','test:technician');
 $q=$db->prepare('SELECT COUNT(*) n FROM sd_ticket_history WHERE ticket_id=?');$q->bind_param('i',$id);$q->execute();$n=$q->get_result()->fetch_assoc()['n'];$q->close();
 if($n<3)throw new RuntimeException('Histórico insuficiente');
 echo "PASSOU integração IoT→chamado→cooldown→atendimento→auditoria\n";
} finally {
 // Cleanup in testing DB, honoring foreign keys.
 $q=$db->prepare('DELETE FROM sd_iot_rule_events WHERE rule_id=?');$q->bind_param('i',$rid);$q->execute();$q->close();
 $q=$db->prepare('DELETE FROM sd_ticket_history WHERE ticket_id IN (SELECT id FROM sd_tickets WHERE device_id=?)');$q->bind_param('s',$device);$q->execute();$q->close();
 $q=$db->prepare('DELETE FROM sd_tickets WHERE device_id=?');$q->bind_param('s',$device);$q->execute();$q->close();
 $q=$db->prepare('DELETE FROM sd_iot_rules WHERE id=?');$q->bind_param('i',$rid);$q->execute();$q->close();
 $q=$db->prepare('DELETE FROM iot_telemetry WHERE device_id=?');$q->bind_param('s',$device);$q->execute();$q->close();
 $q=$db->prepare('DELETE FROM iot_devices WHERE device_id=?');$q->bind_param('s',$device);$q->execute();$q->close();
}
