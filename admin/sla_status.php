<?php
/**
 * admin/sla_status.php
 * Retorna métricas SLA atuais para polling do painel admin
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json');
include_once '../php/conexao.php';

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false]);
    exit();
}

$SLA_CONCLUSAO_H = 72;
$SLA_ALERTA_PCT  = 80;

$sql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) AS pendentes,
        AVG(TIMESTAMPDIFF(HOUR, data_requisicao, IFNULL(data_conclusao, NOW()))) AS media_horas,
        MAX(TIMESTAMPDIFF(HOUR, data_requisicao, NOW())) AS max_horas
    FROM requisicoes
    WHERE DATE(data_requisicao) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$res = $conexao->query($sql);
$row = $res ? $res->fetch_assoc() : [];

$maxHoras = (int) ($row['max_horas'] ?? 0);
$alerta   = $maxHoras > ($SLA_CONCLUSAO_H * $SLA_ALERTA_PCT / 100);

echo json_encode([
    'ok'         => true,
    'alerta'     => $alerta,
    'max_horas'  => $maxHoras,
    'media_horas'=> round((float)($row['media_horas'] ?? 0), 1),
    'pendentes'  => (int) ($row['pendentes'] ?? 0),
    'ts'         => time(),
]);
