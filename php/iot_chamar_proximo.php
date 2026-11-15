<?php
/**
 * php/iot_chamar_proximo.php
 * Admin chama o próximo número da fila
 */
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once 'conexao.php';

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'erro' => 'Não autorizado']);
    exit();
}

// Busca o próximo número a chamar
$res = mysqli_query($conexao, "SELECT numero_chamado, total_espera, tempo_medio FROM iot_fila WHERE id = 1");
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
    echo json_encode(['ok' => false, 'erro' => 'Fila não inicializada']);
    exit();
}

$proximo = (int)$row['numero_chamado'] + 1;
$espera  = max(0, (int)$row['total_espera'] - 1);

// Atualiza o número chamado e reduz a fila
$stmt = $conexao->prepare("UPDATE iot_fila SET numero_chamado = ?, total_espera = ? WHERE id = 1");
$stmt->bind_param('ii', $proximo, $espera);
$ok = $stmt->execute();

// Registra no histórico
$stmt2 = $conexao->prepare("INSERT INTO iot_fila_historico (numero_senha, evento) VALUES (?, 'chamado')");
$stmt2->bind_param('i', $proximo);
$stmt2->execute();

echo json_encode([
    'ok'             => (bool)$ok,
    'numero_chamado' => $proximo,
    'total_espera'   => $espera,
    'ts'             => time()
]);