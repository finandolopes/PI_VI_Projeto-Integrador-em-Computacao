<?php
/**
 * php/iot_checkin_registrar.php
 * Registra novo check-in do terminal público
 */
header('Content-Type: application/json');
include_once 'conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$token     = preg_replace('/[^A-Z0-9]/', '', strtoupper($input['token']     ?? ''));
$nome      = substr(strip_tags($input['nome']      ?? ''), 0, 150);
$cpf       = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');
$finalidade= substr(strip_tags($input['finalidade'] ?? ''), 0, 100);

if (!$token || !$nome || strlen($cpf) < 11 || !$finalidade) {
    echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
    exit();
}

$stmt = $conexao->prepare(
    "INSERT IGNORE INTO iot_checkins (token, nome, cpf, finalidade) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('ssss', $token, $nome, $cpf, $finalidade);
$ok = $stmt->execute();

echo json_encode(['ok' => (bool)$ok, 'token' => $token]);
