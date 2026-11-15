<?php
/**
 * php/iot_fila_status.php
 * Retorna estado atual da fila (polling do totem)
 */
header('Content-Type: application/json');
include_once 'conexao.php';

$sql = "SELECT numero_chamado, total_espera, tempo_medio FROM iot_fila ORDER BY id DESC LIMIT 1";
$res = mysqli_query($conexao, $sql);
$row = $res ? mysqli_fetch_assoc($res) : null;

echo json_encode([
    'numero_chamado' => (int)  ($row['numero_chamado'] ?? 0),
    'total_espera'   => (int)  ($row['total_espera']   ?? 0),
    'tempo_medio'    => (int)  ($row['tempo_medio']    ?? 5),
    'ts'             => time()
]);