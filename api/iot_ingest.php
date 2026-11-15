<?php
header('Content-Type: application/json; charset=utf-8');
// A API não precisa de CORS público; dispositivos enviam requisições servidor a servidor.
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-IoT-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido']);
    exit();
}

require_once __DIR__.'/../php/bootstrap_env.php';
require_once __DIR__.'/../php/access_policy_v7.php';

$expectedKey = getenv('IOT_API_KEY') ?: '';
$providedKey = (string) ($_SERVER['HTTP_X_IOT_KEY'] ?? ($_POST['api_key'] ?? ''));

if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'API key inválida']);
    exit();
}

if ((int)($_SERVER['CONTENT_LENGTH']??0)>32768) {http_response_code(413);echo json_encode(['ok'=>false,'error'=>'Corpo excede o limite de 32 KiB']);exit;}
require_once __DIR__.'/../php/conexao.php';
require_once __DIR__.'/../php/iot_service.php';
if (!($conexao instanceof mysqli)) {http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Banco indisponível']);exit;}
$requestId = bin2hex(random_bytes(8));
$raw = file_get_contents('php://input');
if (strlen((string)$raw)>32768){http_response_code(413);echo json_encode(['ok'=>false,'error'=>'Corpo excede o limite de 32 KiB']);exit;}
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

try {
    $saved = ingestIotPayload($conexao, $data);
    require_once('../php/sd_service.php');
    $incidents = sdEvalIotRules($conexao, $saved);
    echo json_encode([
        'incidents' => $incidents,
        'ok' => true,
        'message' => 'Telemetria recebida com sucesso',
        'data' => $saved,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    error_log('IoT ingest ['.$requestId.']: '.$e->getMessage());
    try { $s=$conexao->prepare('INSERT INTO cf_api_errors_v7(component,category,request_id) VALUES(?,?,?)');
       $component='iot_ingest';$kind=$e instanceof InvalidArgumentException?'validacao':'falha_regra';
       $s->bind_param('sss',$component,$kind,$requestId);$s->execute();$s->close(); } catch(Throwable $ignored){}
    echo json_encode([
        'ok' => false,
        'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Falha no processamento da telemetria ou das regras.',
    ], JSON_UNESCAPED_UNICODE);
}
