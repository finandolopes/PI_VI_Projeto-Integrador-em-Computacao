<?php
/** Executar no CLI: php tests/diagnostico_ambiente.php. Não expõe segredos. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=dirname(__DIR__);
$checks=[
'PHP >= 8.1'=>PHP_VERSION_ID>=80100,
'Extensão mysqli'=>extension_loaded('mysqli'),
'Extensão mbstring'=>extension_loaded('mbstring'),
'Extensão openssl'=>extension_loaded('openssl'),
'Extensão fileinfo'=>extension_loaded('fileinfo'),
'Banco configurado'=>getenv('DB_NAME')!==false && getenv('DB_USER')!==false,
'Chave API IoT definida'=>getenv('IOT_API_KEY')!==false && strlen((string)getenv('IOT_API_KEY'))>=24,
'Migração Service Desk presente'=>is_file($base.'/sql/pi_vi_service_desk.sql'),
'Proteção administrativa presente'=>is_file($base.'/php/sd_authorization.php'),
'Testes de integração disponíveis'=>is_file($base.'/tests/test_iot_database.php'),
];
foreach($checks as $label=>$pass)echo ($pass?'OK':'PENDENTE')." | $label\n";
echo "Observação: esta inspeção não confirma conexão, migração nem funcionamento do ESP32.\n";
