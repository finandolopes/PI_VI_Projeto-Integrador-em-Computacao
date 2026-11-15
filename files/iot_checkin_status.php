<?php
/**
 * php/iot_checkin_status.php
 * Retorna status de um token (polling do terminal público)
 */
header('Content-Type: application/json');
include_once 'conexao.php';

$token = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['token'] ?? ''));
if (!$token) { echo json_encode(['status'=>'nao_encontrado']); exit(); }

$stmt = $conexao->prepare(
    "SELECT status, atendente, hora_confirmacao FROM iot_checkins WHERE token = ?"
);
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;

if (!$row) { echo json_encode(['status' => 'nao_encontrado']); exit(); }

echo json_encode([
    'status'    => $row['status'],
    'atendente' => $row['atendente']         ?? '',
    'hora'      => $row['hora_confirmacao']  ?? '',
]);