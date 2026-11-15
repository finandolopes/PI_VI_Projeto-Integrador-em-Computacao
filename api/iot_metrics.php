<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autenticado']);
    exit();
}

require_once('../php/conexao.php');
require_once('../php/iot_service.php');

$window = isset($_GET['window']) && is_numeric($_GET['window']) ? (int) $_GET['window'] : 24;

try {
    $metrics = getIotMetrics($conexao, $window);
    echo json_encode([
        'ok' => true,
        'window_hours' => $window,
        'metrics' => $metrics,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro ao carregar métricas IoT',
        'details' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
