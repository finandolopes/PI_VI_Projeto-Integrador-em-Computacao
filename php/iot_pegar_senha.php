<?php
/**
 * php/iot_pegar_senha.php
 * Registra no histórico quando cliente retira senha no totem
 */
header('Content-Type: application/json');
include_once 'conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$senha = (int) ($input['senha'] ?? 0);

if ($senha <= 0) {
    echo json_encode(['ok' => false, 'erro' => 'Senha inválida']);
    exit();
}

// Atualiza total_espera
$conexao->query("UPDATE iot_fila SET total_espera = total_espera + 1 WHERE id = 1");

// Registra no histórico
$stmt = $conexao->prepare("INSERT INTO iot_fila_historico (numero_senha, evento) VALUES (?, 'senha_retirada')");
$stmt->bind_param('i', $senha);
$ok = $stmt->execute();

echo json_encode(['ok' => (bool)$ok, 'senha' => $senha]);
