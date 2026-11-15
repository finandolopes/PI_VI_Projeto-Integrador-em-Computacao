<?php
/** Execute somente via CLI, preferencialmente a cada 5 minutos. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Somente CLI');}
require_once __DIR__.'/../php/conexao.php';
require_once __DIR__.'/../php/sd_notifications.php';
$lock=null;
$jobStart=microtime(true);
try {
 sdSchema($conexao);
 $q=$conexao->query("SELECT GET_LOCK('confinter:sd_alerts',0) AS acquired");$lock=(int)$q->fetch_assoc()['acquired'];
 if($lock!==1){fwrite(STDERR,"Já existe uma verificação ativa.\n");exit(0);}
 $minutes=(int)(getenv('SD_INACTIVE_MINUTES')?:30);$minutes=max(5,min(10080,$minutes));
 $result=sdNotifySync($conexao,sdNotificationCandidates($conexao,$minutes));
 try {$ms=(int)round((microtime(true)-$jobStart)*1000);$jobName='sd_notifications';$jobStatus='ok';$jobText='Alertas verificados';$run=$conexao->prepare('INSERT INTO cf_job_runs_v7(job_name,outcome,duration_ms,summary) VALUES(?,?,?,?)');$run->bind_param('ssis',$jobName,$jobStatus,$ms,$jobText);$run->execute();$run->close();}catch(Throwable $logError){error_log('cron history: '.$logError->getMessage());}
 echo json_encode(['success'=>true,'processed_at'=>date(DATE_ATOM)]+$result,JSON_UNESCAPED_UNICODE).PHP_EOL;
}catch(Throwable $e){error_log('scheduled notifications: '.$e->getMessage());fwrite(STDERR,"Falha na verificação; confira banco, extensões e migração.\n");exit(1);
}finally{if($lock===1)$conexao->query("SELECT RELEASE_LOCK('confinter:sd_alerts')");}
