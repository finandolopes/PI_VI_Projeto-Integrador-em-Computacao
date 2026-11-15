<?php
/**
 * php/iot_checkin_confirmar.php
 * Admin confirma a chegada de um check-in
 */
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once 'conexao.php';

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'erro' => 'Não autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$token = preg_replace('/[^A-Z0-9]/', '', strtoupper($input['token'] ?? ''));

if (!$token) {
    echo json_encode(['ok' => false, 'erro' => 'Token inválido']);
    exit();
}

$stmt = $conexao->prepare("UPDATE iot_checkins SET confirmado = 1, confirmado_em = NOW() WHERE token = ?");
$stmt->bind_param('s', $token);
$ok = $stmt->execute();

echo json_encode(['ok' => (bool)$ok, 'token' => $token]);