<?php
/** Cron V7: verifica banco e registra heartbeat. Ex.: */
if(PHP_SAPI!=='cli')exit('CLI only');
require_once __DIR__.'/../php/v7_core.php';
$start=microtime(true);
try{
 $db=cfDb();$db->query('SELECT 1');
 $duration=(int)round((microtime(true)-$start)*1000);$job='v7_heartbeat';$status='ok';$summary='Banco respondeu';
 $s=$db->prepare('INSERT INTO cf_job_runs_v7(job_name,outcome,duration_ms,summary) VALUES(?,?,?,?)');$s->bind_param('ssis',$job,$status,$duration,$summary);$s->execute();$s->close();
 echo "OK: heartbeat registrado\n";
}catch(Throwable $e){error_log('Cron health V7: '.$e->getMessage());fwrite(STDERR,"Falha ao monitorar banco; confira conexões e migrações.\n");exit(1);}
