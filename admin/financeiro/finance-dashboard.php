<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include_once '../../php/conexao.php';
require_once __DIR__ . '/../../php/seguranca.php';
verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Faça login para acessar o painel administrativo.';
    header('Location: ../../index.php');
    exit();
}

// ── KPIs ──────────────────────────────────────────────────────────────────
$hoje = date('Y-m-d');
$mes  = date('Y-m');

$resumo = ['total_hoje' => 0, 'total_mes' => 0, 'aprovadas_hoje' => 0, 'pendentes' => 0];
$r = $conexao->query(
    "SELECT
        SUM(CASE WHEN DATE(data_requisicao) = '$hoje' THEN 1 ELSE 0 END)                                         AS total_hoje,
        SUM(CASE WHEN DATE_FORMAT(data_requisicao,'%Y-%m') = '$mes' THEN 1 ELSE 0 END)                           AS total_mes,
        SUM(CASE WHEN DATE(data_requisicao) = '$hoje' AND status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS aprovadas_hoje,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END)                                                     AS pendentes
     FROM requisicoes"
);
if ($r) $resumo = array_map('intval', $r->fetch_assoc());

// ── Tendência 30 dias ─────────────────────────────────────────────────────
$tendencia = [];
$r = $conexao->query(
    "SELECT
        DATE_FORMAT(data_requisicao,'%d/%m') AS data,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS aprovadas
     FROM requisicoes
     WHERE data_requisicao >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(data_requisicao)
     ORDER BY data_requisicao ASC"
);
if ($r) while ($row = $r->fetch_assoc()) $tendencia[] = $row;

// ── Por tipo ──────────────────────────────────────────────────────────────
$porTipo = [];
$r = $conexao->query(
    "SELECT
        tipo,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS aprovadas
     FROM requisicoes
     WHERE tipo IS NOT NULL AND tipo <> ''
     GROUP BY tipo
     ORDER BY total DESC"
);
if ($r) while ($row = $r->fetch_assoc()) $porTipo[] = $row;

// ── Por categoria ─────────────────────────────────────────────────────────
$porCategoria = [];
$r = $conexao->query(
    "SELECT
        categoria,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS aprovadas
     FROM requisicoes
     WHERE categoria IS NOT NULL AND categoria <> ''
     GROUP BY categoria
     ORDER BY total DESC
     LIMIT 8"
);
if ($r) while ($row = $r->fetch_assoc()) $porCategoria[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER – Finance Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap5-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>

<?php include '../navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">

        <!-- Cabeçalho -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title">
                        <i class="fa fa-chart-line text-success me-2" aria-hidden="true"></i>
                        Finance Dashboard
                    </h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Finance Dashboard</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <span class="badge bg-light text-dark">
                        Atualizado em <?php echo date('d/m/Y H:i'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 dashboard-grid">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-green"><i class="fa fa-calendar-day" aria-hidden="true"></i></span>
                        <small class="text-muted">Hoje</small>
                    </div>
                    <p class="value"><?php echo $resumo['total_hoje']; ?></p>
                    <p class="label">Requisições hoje</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-blue"><i class="fa fa-calendar-alt" aria-hidden="true"></i></span>
                        <small class="text-muted">Mês</small>
                    </div>
                    <p class="value"><?php echo number_format($resumo['total_mes'], 0, ',', '.'); ?></p>
                    <p class="label">Requisições no mês</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-indigo"><i class="fa fa-check-circle" aria-hidden="true"></i></span>
                        <small class="text-muted">Aprovadas</small>
                    </div>
                    <p class="value"><?php echo $resumo['aprovadas_hoje']; ?></p>
                    <p class="label">Aprovadas hoje</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-orange"><i class="fa fa-clock" aria-hidden="true"></i></span>
                        <small class="text-muted">Fila</small>
                    </div>
                    <p class="value"><?php echo $resumo['pendentes']; ?></p>
                    <p class="label">Pendentes</p>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-chart-area text-primary me-2" aria-hidden="true"></i>
                            Volume – últimos 30 dias
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartTendencia" aria-label="Volume últimos 30 dias" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-chart-pie text-success me-2" aria-hidden="true"></i>
                            Por categoria
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartCategoria" aria-label="Requisições por categoria" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela por tipo -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fa fa-table text-primary me-2" aria-hidden="true"></i>
                    Desempenho por tipo de requisição
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Aprovadas</th>
                                <th class="text-center">Pendentes</th>
                                <th>Taxa de aprovação</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($porTipo)): ?>
                            <?php foreach ($porTipo as $t):
                                $pct   = $t['total'] > 0 ? round($t['aprovadas'] / $t['total'] * 100) : 0;
                                $pend  = $t['total'] - $t['aprovadas'];
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $t['tipo'])), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="text-center"><?php echo $t['total']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $t['aprovadas']; ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?php echo $pend; ?></span>
                                </td>
                                <td style="min-width:180px">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px">
                                            <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?php echo $pct; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Nenhum dado disponível.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /page-wrapper -->

<?php include '../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const dias      = <?php echo json_encode(array_column($tendencia, 'data'),             JSON_UNESCAPED_UNICODE); ?>;
    const totais    = <?php echo json_encode(array_map('intval',   array_column($tendencia, 'total'))); ?>;
    const aprovadas = <?php echo json_encode(array_map('intval',   array_column($tendencia, 'aprovadas'))); ?>;
    const catLabels = <?php echo json_encode(array_column($porCategoria, 'categoria'),     JSON_UNESCAPED_UNICODE); ?>;
    const catTotais = <?php echo json_encode(array_map('intval',   array_column($porCategoria, 'total'))); ?>;

    const ctxT = document.getElementById('chartTendencia');
    if (ctxT) {
        new Chart(ctxT, {
            type: 'line',
            data: {
                labels: dias,
                datasets: [
                    {
                        label: 'Total',
                        data: totais,
                        borderColor: '#0f4c81',
                        backgroundColor: 'rgba(15,76,129,0.10)',
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Aprovadas',
                        data: aprovadas,
                        borderColor: '#2f9e8f',
                        backgroundColor: 'rgba(47,158,143,0.10)',
                        fill: true,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    const ctxC = document.getElementById('chartCategoria');
    if (ctxC) {
        new Chart(ctxC, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catTotais,
                    backgroundColor: ['#0f4c81','#2f9e8f','#f0a202','#7b6dff','#e76f51','#8f9fb3','#0dcaf0','#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});
</script>
</body>
</html>



