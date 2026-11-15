<?php
/** Instalador versionado, exclusivamente CLI, nunca executa DROP nem dados de demonstração. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__.'/../php/bootstrap_env.php';
if (!extension_loaded('mysqli')) {fwrite(STDERR,"ERRO: instale php-mysqli.\n");exit(2);}
if (in_array('--help',$argv,true)) {echo "Uso: php scripts/migrate_v7.php [--dry-run] [--status]\n";exit(0);}
$host=getenv('DB_HOST')?:'127.0.0.1';$dbName=getenv('DB_NAME')?:'';$user=getenv('DB_USER')?:'';
if ($dbName===''||$user===''){fwrite(STDERR,"Configure DB_NAME e DB_USER em .env.\n");exit(2);}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
try { $db=new mysqli($host,$user,getenv('DB_PASS')?:'',$dbName,(int)(getenv('DB_PORT')?:3306));$db->set_charset('utf8mb4'); }
catch(Throwable $e){fwrite(STDERR,"Falha ao conectar; confira DB_HOST/DB_USER.\n");exit(2);}
$status=in_array('--status',$argv,true);
$dry=in_array('--dry-run',$argv,true);
$exists=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cf_schema_migrations_v7'")->num_rows > 0;
if (!$exists && !$status && !$dry) {
    $db->query("CREATE TABLE IF NOT EXISTS cf_schema_migrations_v7 (version VARCHAR(80) PRIMARY KEY, checksum CHAR(64) NOT NULL, installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $exists=true;
}
$locked=false;
if (!$status && !$dry) {
    $lock=$db->query("SELECT GET_LOCK('confinter:v7:migrations',10) AS acquired")->fetch_assoc();
    if ((int)($lock['acquired']??0)!==1) {fwrite(STDERR,"Não foi possível obter bloqueio de migrações.\n");exit(3);}
    $locked=true;
}
try {
  foreach(glob(__DIR__.'/../sql/v7/[0-9]*.sql') as $file){
    $ver=basename($file,'.sql');$source=file_get_contents($file);$hash=hash('sha256',$source);
    $previous=null;
    if ($exists) {
        $q=$db->prepare('SELECT checksum FROM cf_schema_migrations_v7 WHERE version=?');
        $q->bind_param('s',$ver);$q->execute();$previous=$q->get_result()->fetch_assoc();$q->close();
    }
    if($previous){if(!hash_equals($previous['checksum'],$hash))throw new RuntimeException('Migração já instalada foi alterada: '.$ver);echo "OK instalada: $ver\n";continue;}
    echo ($status?'PENDENTE':($dry?'DRY-RUN':'APLICANDO')).": $ver\n";
    if($status||$dry)continue;
    $stmts=preg_split('/^-- \+statement\s*$/m',$source)?:[];
    foreach($stmts as $stmt){$stmt=trim($stmt);if($stmt==='')continue;$stmt=preg_replace('/\A(?:\s*--[^\n]*\n)+/','',$stmt);$stmt=trim($stmt);
      if($stmt==='')continue;
      if(!preg_match('/^CREATE TABLE IF NOT EXISTS\s+/i',$stmt))throw new RuntimeException('DDL não permitido em '.$ver);
      $db->query($stmt);
    }
    $q=$db->prepare('INSERT INTO cf_schema_migrations_v7(version,checksum) VALUES(?,?)');$q->bind_param('ss',$ver,$hash);$q->execute();$q->close();
  }
} catch(Throwable $e){fwrite(STDERR,'Falha: '.$e->getMessage()."\nDDL pode ter sido aplicado parcialmente: reexecute após revisar a causa.\n");exit(1);} finally {if($locked){$db->query("SELECT RELEASE_LOCK('confinter:v7:migrations')");}}
