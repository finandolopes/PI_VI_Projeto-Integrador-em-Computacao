<?php
/** CLI bootstrap seguro: cria admin usando password_hash; nunca aceita senha em argumento de linha de comando. */
if(PHP_SAPI!=='cli')exit('CLI only');
require_once __DIR__.'/../php/bootstrap_env.php';
if(!extension_loaded('mysqli'))exit("Instale mysqli\n");
$name=trim((string)($argv[1]??''));$login=trim((string)($argv[2]??''));$email=trim((string)($argv[3]??''));
if(strlen($name)<3||!preg_match('/^[A-Za-z0-9_.-]{3,60}$/',$login)||!filter_var($email,FILTER_VALIDATE_EMAIL)){
fwrite(STDERR,"Uso: php scripts/create_admin_v7.php 'Nome' login email@dominio\n");exit(2);
}
fwrite(STDERR,"Senha forte (não ecoada se terminal suporta stty): ");
if(function_exists('shell_exec')&&stream_isatty(STDIN)) @shell_exec('stty -echo');
$pass=trim((string)fgets(STDIN));
if(function_exists('shell_exec')&&stream_isatty(STDIN)) @shell_exec('stty echo');
fwrite(STDERR,"\n");
if(strlen($pass)<14)exit("Senha deve ter pelo menos 14 caracteres.\n");
$db=new mysqli(getenv('DB_HOST')?:'127.0.0.1',getenv('DB_USER')?:'',getenv('DB_PASS')?:'',getenv('DB_NAME')?:'',(int)(getenv('DB_PORT')?:3306));$db->set_charset('utf8mb4');
$hash=password_hash($pass,PASSWORD_DEFAULT);unset($pass);
$s=$db->prepare("INSERT INTO usuarios(nome,usuario,email,senha,perfil,status) VALUES(?,?,?,?,'admin','ativo')");$s->bind_param('ssss',$name,$login,$email,$hash);$s->execute();echo "Administrador criado com hash forte.\n";
