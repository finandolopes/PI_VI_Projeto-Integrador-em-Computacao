<?php
require_once __DIR__.'/v7_core.php';
function cfHealthConnection(): ?mysqli {
 if(!extension_loaded('mysqli'))return null;
 try {mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
 $db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USER')?:'',getenv('DB_PASS')?:'',getenv('DB_NAME')?:'',(int)(getenv('DB_PORT')?:3306));$db->set_charset('utf8mb4');return $db;}
 catch(Throwable $e){error_log('V7 health DB indisponível');return null;}
}
function cfHealthSnapshot(?mysqli $db): array {
 $checks=[];
 $required=['mysqli','mbstring','openssl','json'];
 foreach($required as $ext)$checks[]=['name'=>'Extensão '.$ext,'ok'=>extension_loaded($ext),'detail'=>extension_loaded($ext)?'Disponível':'Ausente'];
 $checks[]=['name'=>'Modo demonstração','ok'=>!filter_var(getenv('ALLOW_DEMO_MODE')?:'0',FILTER_VALIDATE_BOOLEAN),'detail'=>filter_var(getenv('ALLOW_DEMO_MODE')?:'0',FILTER_VALIDATE_BOOLEAN)?'Ativado — não adequado a homologação':'Desligado'];
 if($db===null){$checks[]=['name'=>'MySQL','ok'=>false,'detail'=>'Sem conexão disponível'];return $checks;}
 try{$db->query('SELECT 1');$checks[]=['name'=>'MySQL','ok'=>true,'detail'=>'Conexão ativa'];}catch(Throwable $e){$checks[]=['name'=>'MySQL','ok'=>false,'detail'=>'Consulta falhou'];return $checks;}
 foreach(['iot_devices','iot_telemetry','sd_tickets','sd_iot_rule_events','sd_notifications','crm_activity_v7','finance_entries_v7','cf_schema_migrations_v7'] as $table){$checks[]=['name'=>'Tabela '.$table,'ok'=>cfHasTable($db,$table),'detail'=>cfHasTable($db,$table)?'Presente':'Não instalada'];}
 if(cfHasTable($db,'cf_job_runs_v7')){
     $s=$db->query("SELECT created_at,outcome FROM cf_job_runs_v7 WHERE job_name='v7_heartbeat' ORDER BY id DESC LIMIT 1");$last=$s->fetch_assoc();
     $ttl=max(60,(int)(getenv('CF_MONITOR_ALERT_SECONDS')?:900));
     $age=$last?time()-strtotime($last['created_at']):PHP_INT_MAX;
     $checks[]=['name'=>'Heartbeat agendado','ok'=>$last && $last['outcome']==='ok' && $age<=$ttl,'detail'=>$last?'Última execução '.$last['created_at']:'Sem execução registrada'];
 }
 return $checks;
}
