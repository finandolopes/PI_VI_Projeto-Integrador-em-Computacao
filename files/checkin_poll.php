<?php
/**
 * admin/checkin_poll.php
 * Retorna novos check-ins desde o último id visto pelo admin (live update)
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json');
include_once '../php/conexao.php';

// Segurança: só admin logado
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    echo json_encode(['novos' => []]);
    exit();
}

$ultimoId = (int) ($_GET['ultimo_id'] ?? 0);

$stmt = $conexao->prepare("
    SELECT id, token, nome, cpf, finalidade,
           DATE_FORMAT(criado_em, '%H:%i') AS hora
    FROM iot_checkins
    WHERE id > ?
    ORDER BY id DESC
    LIMIT 20
");
$stmt->bind_param('i', $ultimoId);
$stmt->execute();
$res = $stmt->get_result();
$novos = [];
if ($res) while ($r = $res->fetch_assoc()) $novos[] = $r;

echo json_encode(['novos' => $novos]);