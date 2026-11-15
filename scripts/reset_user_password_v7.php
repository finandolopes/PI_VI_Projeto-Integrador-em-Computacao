<?php
/** Recuperação administrativa controlada, sem gravar senha em argv/log. */
if(PHP_SAPI!=='cli')exit('CLI only');
require_once __DIR__.'/../php/bootstrap_env.php';
if(!extension_loaded('mysqli'))exit("mysqli indisponível\n");
$table=(string)($argv[1]??'');$login=(string)($argv[2]??'');
if(!in_array($table,['usuarios','adm'],true)||!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$login))exit("Uso: php scripts/reset_user_password_v7.php usuarios|adm login\n");
fwrite(STDERR,"Nova senha (não ecoada se terminal suporta stty): ");
if(function_exists('shell_exec')&&stream_isatty(STDIN))@shell_exec('stty -echo');
$pwd=trim((string)fgets(STDIN));
if(function_exists('shell_exec')&&stream_isatty(STDIN))@shell_exec('stty echo');
fwrite(STDERR,"\n");
if(strlen($pwd)<14)exit("Senha curta: mínimo 14 caracteres.\n");
$hash=password_hash($pwd,PASSWORD_DEFAULT);unset($pwd);
$db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USER')?:'',getenv('DB_PASS')?:'',getenv('DB_NAME')?:'',(int)(getenv('DB_PORT')?:3306));
$s=$db->prepare("UPDATE `$table` SET senha=? WHERE usuario=?");$s->bind_param('ss',$hash,$login);$s->execute();$affected=$s->affected_rows;$s->close();
echo $affected?"Senha atualizada com hash forte.\n":"Nenhum registro atualizado.\n";
