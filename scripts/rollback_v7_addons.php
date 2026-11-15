<?php
/** Rollback V7 opcional, apenas tabelas auxiliares e SOMENTE vazias. */
if(PHP_SAPI!=='cli')exit('CLI only');
require_once __DIR__.'/../php/bootstrap_env.php';
if(!in_array('--confirm-empty-only',$argv,true)){fwrite(STDERR,"Uso: php scripts/rollback_v7_addons.php --confirm-empty-only\n");exit(2);}
if(!extension_loaded('mysqli')){fwrite(STDERR,"mysqli indisponível\n");exit(2);}
$db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USER')?:'',getenv('DB_PASS')?:'',getenv('DB_NAME')?:'',(int)(getenv('DB_PORT')?:3306));
$tables=['crm_activity_v7','finance_entries_v7','cf_operational_events_v7','cf_job_runs_v7','cf_api_errors_v7','cf_user_prefs_v7'];
foreach($tables as $table){$r=$db->query("SELECT COUNT(*) n FROM `$table`");if((int)$r->fetch_assoc()['n']!==0){fwrite(STDERR,"Rollback recusado: $table contém dados.\n");exit(3);}}
foreach(array_reverse($tables) as $table){$db->query("DROP TABLE IF EXISTS `$table`");echo "Removida tabela vazia $table\n";}
$db->query("DELETE FROM cf_schema_migrations_v7 WHERE version='020_v7_operations'");
echo "Dados de usuários, IoT, Service Desk e esquema-base preservados.\n";
