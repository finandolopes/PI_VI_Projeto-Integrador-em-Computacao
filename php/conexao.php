<?php
/**
 * Conexão principal. Quando o banco não estiver configurado, habilita modo de demonstração
 * para permitir navegação institucional e geração de ilustrações do projeto.
 */
require_once __DIR__ . '/bootstrap_env.php';
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$database = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$environment = strtolower((string)(getenv('APP_ENV') ?: getenv('ENVIRONMENT') ?: 'production'));
$allowDemo = $environment === 'development' && filter_var(getenv('ALLOW_DEMO_MODE') ?: '0', FILTER_VALIDATE_BOOLEAN);
$conexao = null;

$activateDemo = static function (string $reason) use (&$conexao): void {
    if (!defined('CONFINTER_DEMO_MODE')) {
        define('CONFINTER_DEMO_MODE', true);
    }
    if (!defined('CONFINTER_DB_READY')) {
        define('CONFINTER_DB_READY', false);
    }
    $conexao = null;
    error_log('CONFINTER DEMO MODE: ' . $reason);
};

if ($database === '' || $user === '') {
    if ($allowDemo) {
        $activateDemo('DB_NAME/DB_USER ausentes');
        return;
    }
    error_log('CONFINTER: DB_NAME/DB_USER ausentes');
    http_response_code(503);
    exit('Banco de dados não configurado.');
}

if (!extension_loaded('mysqli')) {
    if ($allowDemo) { $activateDemo('extensão mysqli ausente'); return; }
    http_response_code(503);
    error_log('CONFINTER: extensão mysqli não instalada');
    exit('Serviço de banco indisponível.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexao = new mysqli($host, $user, $pass, $database, $port);
    $conexao->set_charset('utf8mb4');
    $dbTz=getenv('DB_TIMEZONE')?:'-03:00';
    if(!preg_match('/^[+-](?:0[0-9]|1[0-4]):[0-5][0-9]$/',$dbTz))throw new RuntimeException('DB_TIMEZONE inválido');
    $conexao->query("SET time_zone='".$dbTz."'");
    if (!defined('CONFINTER_DB_READY')) {
        define('CONFINTER_DB_READY', true);
    }
    if (!defined('CONFINTER_DEMO_MODE')) {
        define('CONFINTER_DEMO_MODE', false);
    }
} catch (Throwable $error) {
    if ($allowDemo) {
        $activateDemo('falha na conexão: ' . $error->getMessage());
        return;
    }
    error_log('CONFINTER: falha na conexão: ' . $error->getMessage());
    http_response_code(503);
    exit('Serviço temporariamente indisponível.');
}
