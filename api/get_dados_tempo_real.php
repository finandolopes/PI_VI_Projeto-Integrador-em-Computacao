<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

include_once('../php/conexao.php');
include_once('../php/iot_service.php');

function queryScalarInt(mysqli $conexao, string $sql): int
{
    $result = mysqli_query($conexao, $sql);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function queryScalarString(mysqli $conexao, string $sql, string $field): string
{
    $result = mysqli_query($conexao, $sql);
    if (!$result) {
        return '';
    }

    $row = mysqli_fetch_assoc($result);
    return (string) ($row[$field] ?? '');
}

function getVisitasPorHora(mysqli $conexao): array
{
    $sql = "SELECT HOUR(data_visita) AS hora, COUNT(*) AS visitas
            FROM contador_visitas
            WHERE DATE(data_visita) = CURDATE()
            GROUP BY HOUR(data_visita)
            ORDER BY hora";

    $result = mysqli_query($conexao, $sql);
    $visitas = array_fill(0, 24, 0);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $hora = (int) ($row['hora'] ?? 0);
            if ($hora >= 0 && $hora <= 23) {
                $visitas[$hora] = (int) ($row['visitas'] ?? 0);
            }
        }
    }

    return $visitas;
}

$visitasHoje = queryScalarInt($conexao, "SELECT COUNT(*) AS total FROM contador_visitas WHERE DATE(data_visita) = CURDATE()");
$visitasUltimaHora = queryScalarInt($conexao, "SELECT COUNT(*) AS total FROM contador_visitas WHERE data_visita >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$requisicoesHoje = queryScalarInt($conexao, "SELECT COUNT(*) AS total FROM requisicoes WHERE DATE(data_requisicao) = CURDATE()");
$depoimentosPendentes = queryScalarInt($conexao, "SELECT COUNT(*) AS total FROM depoimentos WHERE status_mod = 'pendente' OR status = 'pendente'");
$tempoMedio = queryScalarString($conexao, "SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo))) AS media FROM contador_visitas", 'media');
$tempoMedio = $tempoMedio !== '' ? $tempoMedio : '00:00:00';

$taxaConversao = $visitasHoje > 0 ? round(($requisicoesHoje / $visitasHoje) * 100, 1) : 0;
$visitasPorHora = getVisitasPorHora($conexao);

$alertas = [];
if ($visitasUltimaHora > 10) {
    $alertas[] = "Pico de visitas: {$visitasUltimaHora} acessos na última hora.";
}

$horaAtual = (int) date('H');
$visitasHoraAtual = (int) ($visitasPorHora[$horaAtual] ?? 0);
if ($visitasHoraAtual > 15) {
    $alertas[] = "Hora de pico detectada: {$visitasHoraAtual} visitas na hora atual.";
}

$iot = [
    'devices_online' => 0,
    'devices_total' => 0,
    'readings_last_hour' => 0,
    'alerts_window' => 0,
    'latest' => [],
];

try {
    $iotMetrics = getIotMetrics($conexao, 24);
    $iot = [
        'devices_online' => (int) ($iotMetrics['devices_online'] ?? 0),
        'devices_total' => (int) ($iotMetrics['devices_total'] ?? 0),
        'readings_last_hour' => (int) ($iotMetrics['readings_last_hour'] ?? 0),
        'alerts_window' => (int) ($iotMetrics['alerts_window'] ?? 0),
        'latest' => $iotMetrics['latest'] ?? [],
    ];

    if ($iot['alerts_window'] > 0) {
        $alertas[] = 'IoT: há leituras em alerta nas últimas 24 horas.';
    }
} catch (Throwable $e) {
    // Mantém API principal funcionando mesmo sem módulo IoT.
}

$dados = [
    'visitas_hoje' => $visitasHoje,
    'visitas_ultima_hora' => $visitasUltimaHora,
    'requisicoes_hoje' => $requisicoesHoje,
    'depoimentos_pendentes' => $depoimentosPendentes,
    'tempo_medio' => $tempoMedio,
    'taxa_conversao' => $taxaConversao,
    'visitas_por_hora' => $visitasPorHora,
    'alertas' => $alertas,
    'iot' => $iot,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($dados, JSON_UNESCAPED_UNICODE);
