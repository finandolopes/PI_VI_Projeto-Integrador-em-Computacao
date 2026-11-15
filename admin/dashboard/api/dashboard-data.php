<?php
/**
 * API - Dashboard Inteligente
 * Retorna dados JSON para o dashboard.
 */


header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../../../php/config.php';
require_once __DIR__ . '/../../../php/seguranca.php';

verificarSessao();
require_permissao(['admin', 'gestor', 'operador']);

try {
    $period = (string) ($_GET['period'] ?? '30days');
    $leadStatus = strtoupper(trim((string) ($_GET['lead_status'] ?? '')));

    $dates = getDateRange($period);
    $where = buildClientesWhere($conn, $dates['start'], $dates['end'], $leadStatus);

    $response = [
        'total_leads' => getTotalLeads($conn, $where),
        'leads_change' => getLeadsChange($conn, $period, $leadStatus),
        'hot_count' => getHotCount($conn, $where),
        'hot_conversion' => getHotConversionRate($conn, $where),
        'conversion_rate' => getConversionRate($conn, $where),
        'revenue_month' => getRevenueMonth($conn, $where),
        'revenue_change' => getRevenueChange($conn, $period, $leadStatus),
        'leads_by_status' => getLeadsByStatus($conn, $where),
        'conversion_trend' => getConversionTrend($conn, $period),
        'revenue_data' => getRevenueData($conn, $period),
        'top_leads' => getTopLeads($conn, $where),
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function getDateRange(string $period): array
{
    $end = new DateTimeImmutable();

    switch ($period) {
        case '7days':
            $start = $end->modify('-7 days');
            break;
        case '90days':
            $start = $end->modify('-90 days');
            break;
        case 'all':
            $start = new DateTimeImmutable('2020-01-01 00:00:00');
            break;
        case '30days':
        default:
            $start = $end->modify('-30 days');
            break;
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];
}

function buildClientesWhere(mysqli $conn, string $start, string $end, string $leadStatus): string
{
    $startSql = $conn->real_escape_string($start);
    $endSql = $conn->real_escape_string($end);

    $where = "WHERE c.data_cadastro BETWEEN '$startSql' AND '$endSql'";

    $statusCondition = mapLeadStatusToRequisicaoCondition('r_status', $leadStatus);
    if ($statusCondition !== '') {
        $where .= " AND EXISTS (
            SELECT 1
            FROM requisicoes r_status
            WHERE r_status.id_cliente = c.id_cliente
            AND $statusCondition
        )";
    }

    return $where;
}

function mapLeadStatusToRequisicaoCondition(string $alias, string $leadStatus): string
{
    switch ($leadStatus) {
        case 'HOT':
            return "$alias.status = 'aprovado'";
        case 'WARM':
            return "$alias.status IN ('pendente', 'em_analise')";
        case 'COLD':
            return "$alias.status IN ('reprovado', 'cancelado')";
        default:
            return '';
    }
}

function percentage(float $current, float $previous): float
{
    if ($previous <= 0.0) {
        return $current > 0.0 ? 100.0 : 0.0;
    }

    return (($current - $previous) / $previous) * 100;
}

function getTotalLeads(mysqli $conn, string $where): int
{
    $query = "SELECT COUNT(*) AS total FROM clientes c $where";
    $result = $conn->query($query);
    return (int) (($result && ($row = $result->fetch_assoc())) ? ($row['total'] ?? 0) : 0);
}

function getLeadsChange(mysqli $conn, string $period, string $leadStatus): float
{
    $dates = getDateRange($period);
    $start = new DateTimeImmutable($dates['start']);
    $end = new DateTimeImmutable($dates['end']);

    $intervalDays = max(1, (int) $start->diff($end)->days);
    $prevStart = $start->modify("-{$intervalDays} days");
    $prevEnd = $start;

    $currWhere = buildClientesWhere($conn, $dates['start'], $dates['end'], $leadStatus);
    $prevWhere = buildClientesWhere($conn, $prevStart->format('Y-m-d H:i:s'), $prevEnd->format('Y-m-d H:i:s'), $leadStatus);

    $curr = getTotalLeads($conn, $currWhere);
    $prev = getTotalLeads($conn, $prevWhere);

    return round(percentage((float) $curr, (float) $prev), 1);
}

function getHotCount(mysqli $conn, string $where): int
{
    $query = "SELECT COUNT(DISTINCT c.id_cliente) AS total
              FROM clientes c
              JOIN requisicoes r ON r.id_cliente = c.id_cliente
              $where AND r.status = 'aprovado'";
    $result = $conn->query($query);
    return (int) (($result && ($row = $result->fetch_assoc())) ? ($row['total'] ?? 0) : 0);
}

function getHotConversionRate(mysqli $conn, string $where): float
{
    $query = "SELECT
                COUNT(DISTINCT CASE WHEN r.status = 'aprovado' THEN c.id_cliente END) AS conversoes,
                COUNT(DISTINCT c.id_cliente) AS total
              FROM clientes c
              JOIN requisicoes r ON r.id_cliente = c.id_cliente
              $where AND r.status IN ('pendente', 'em_analise', 'aprovado')";

    $result = $conn->query($query);
    $data = $result ? $result->fetch_assoc() : ['conversoes' => 0, 'total' => 0];

    $total = (float) ($data['total'] ?? 0);
    $conversoes = (float) ($data['conversoes'] ?? 0);

    return $total > 0 ? round(($conversoes / $total) * 100, 1) : 0.0;
}

function getConversionRate(mysqli $conn, string $where): float
{
    $query = "SELECT
                COUNT(CASE WHEN r.status = 'aprovado' THEN 1 END) AS conversoes,
                COUNT(r.id_requisicao) AS total
              FROM clientes c
              LEFT JOIN requisicoes r ON r.id_cliente = c.id_cliente
              $where";

    $result = $conn->query($query);
    $data = $result ? $result->fetch_assoc() : ['conversoes' => 0, 'total' => 0];

    $total = (float) ($data['total'] ?? 0);
    $conversoes = (float) ($data['conversoes'] ?? 0);

    return $total > 0 ? round(($conversoes / $total) * 100, 1) : 0.0;
}

function getRevenueMonth(mysqli $conn, string $where): float
{
    $query = "SELECT SUM(r.valor_solicitado) AS total
              FROM requisicoes r
              JOIN clientes c ON r.id_cliente = c.id_cliente
              $where AND r.status = 'aprovado'";

    $result = $conn->query($query);
    $data = $result ? $result->fetch_assoc() : ['total' => 0];

    return (float) ($data['total'] ?? 0);
}

function getRevenueChange(mysqli $conn, string $period, string $leadStatus): float
{
    $dates = getDateRange($period);
    $start = new DateTimeImmutable($dates['start']);
    $end = new DateTimeImmutable($dates['end']);

    $intervalDays = max(1, (int) $start->diff($end)->days);
    $prevStart = $start->modify("-{$intervalDays} days");
    $prevEnd = $start;

    $currWhere = buildClientesWhere($conn, $dates['start'], $dates['end'], $leadStatus);
    $prevWhere = buildClientesWhere($conn, $prevStart->format('Y-m-d H:i:s'), $prevEnd->format('Y-m-d H:i:s'), $leadStatus);

    $curr = getRevenueMonth($conn, $currWhere);
    $prev = getRevenueMonth($conn, $prevWhere);

    return round(percentage($curr, $prev), 1);
}

function getLeadsByStatus(mysqli $conn, string $where): array
{
    $query = "SELECT
                COUNT(DISTINCT CASE WHEN r.status = 'aprovado' THEN c.id_cliente END) AS hot,
                COUNT(DISTINCT CASE WHEN r.status IN ('pendente', 'em_analise') THEN c.id_cliente END) AS warm,
                COUNT(DISTINCT CASE WHEN r.status IN ('reprovado', 'cancelado') THEN c.id_cliente END) AS cold,
                COUNT(DISTINCT CASE WHEN r.id_requisicao IS NULL THEN c.id_cliente END) AS sem_requisicao
              FROM clientes c
              LEFT JOIN requisicoes r ON r.id_cliente = c.id_cliente
              $where";

    $result = $conn->query($query);
    $row = $result ? $result->fetch_assoc() : [];

    $hot = (int) ($row['hot'] ?? 0);
    $warm = (int) ($row['warm'] ?? 0);
    $cold = (int) ($row['cold'] ?? 0) + (int) ($row['sem_requisicao'] ?? 0);

    return ['hot' => $hot, 'warm' => $warm, 'cold' => $cold];
}

function getConversionTrend(mysqli $conn, string $period): array
{
    $days = $period === '7days' ? 7 : 30;
    $labels = [];
    $values = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = new DateTimeImmutable("-{$i} days");
        $labels[] = $date->format('d/m');

        $start = $date->format('Y-m-d 00:00:00');
        $end = $date->format('Y-m-d 23:59:59');

        $query = "SELECT
                    COUNT(CASE WHEN status = 'aprovado' THEN 1 END) AS conversoes,
                    COUNT(*) AS total
                  FROM requisicoes
                  WHERE data_hora BETWEEN '$start' AND '$end'";

        $result = $conn->query($query);
        $data = $result ? $result->fetch_assoc() : ['conversoes' => 0, 'total' => 0];
        $total = (float) ($data['total'] ?? 0);
        $conversoes = (float) ($data['conversoes'] ?? 0);

        $values[] = $total > 0 ? round(($conversoes / $total) * 100, 1) : 0;
    }

    return ['labels' => $labels, 'values' => $values];
}

function getRevenueData(mysqli $conn, string $period): array
{
    $days = $period === '7days' ? 7 : 30;
    $labels = [];
    $revenue = [];
    $volume = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = new DateTimeImmutable("-{$i} days");
        $labels[] = $date->format('d/m');

        $start = $date->format('Y-m-d 00:00:00');
        $end = $date->format('Y-m-d 23:59:59');

        $query = "SELECT
                    SUM(valor_solicitado) AS total_valor,
                    COUNT(*) AS total_volume
                  FROM requisicoes
                  WHERE data_hora BETWEEN '$start' AND '$end'
                  AND status = 'aprovado'";

        $result = $conn->query($query);
        $data = $result ? $result->fetch_assoc() : ['total_valor' => 0, 'total_volume' => 0];

        $revenue[] = (float) ($data['total_valor'] ?? 0);
        $volume[] = (int) ($data['total_volume'] ?? 0);
    }

    return ['labels' => $labels, 'revenue' => $revenue, 'volume' => $volume];
}

function getTopLeads(mysqli $conn, string $where): array
{
    $query = "SELECT
                c.id_cliente AS id,
                c.nome,
                c.responsavel,
                c.data_cadastro,
                COUNT(r.id_requisicao) AS total_requisicoes,
                SUM(CASE WHEN r.status = 'aprovado' THEN 1 ELSE 0 END) AS aprovadas,
                MAX(COALESCE(r.valor_solicitado, 0)) AS valor
              FROM clientes c
              LEFT JOIN requisicoes r ON r.id_cliente = c.id_cliente
              $where
              GROUP BY c.id_cliente, c.nome, c.responsavel, c.data_cadastro
              ORDER BY aprovadas DESC, total_requisicoes DESC, c.data_cadastro DESC
              LIMIT 10";

    $result = $conn->query($query);
    $leads = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $aprovadas = (int) ($row['aprovadas'] ?? 0);
            $totalReq = (int) ($row['total_requisicoes'] ?? 0);
            $score = min(100, ($aprovadas * 40) + ($totalReq * 10));

            if ($aprovadas > 0) {
                $status = 'HOT';
                $proximaAcao = 'Contato para formalização';
            } elseif ($totalReq > 0) {
                $status = 'WARM';
                $proximaAcao = 'Acompanhamento da análise';
            } else {
                $status = 'COLD';
                $proximaAcao = 'Primeiro contato comercial';
            }

            $leads[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'score' => $score,
                'status' => $status,
                'valor' => (float) ($row['valor'] ?? 0),
                'probabilidade' => min(95, $score),
                'proxima_acao' => $proximaAcao,
            ];
        }
    }

    return $leads;
}

