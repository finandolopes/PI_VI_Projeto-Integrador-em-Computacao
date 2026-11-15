<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include_once '../../php/conexao.php';

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

// ── Parâmetros ─────────────────────────────────────────────────────────────
$periodo    = max(7, (int) ($_GET['periodo'] ?? 30));
$dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));

// ── Funil de conversão ─────────────────────────────────────────────────────
$funil = ['total_leads' => 0, 'em_analise' => 0, 'convertido' => 0, 'reprovado' => 0];
$r = $conexao->query(
    "SELECT
        COUNT(*) AS total_leads,
        SUM(CASE WHEN status = 'em_analise'                    THEN 1 ELSE 0 END) AS em_analise,
        SUM(CASE WHEN status IN ('aprovado','finalizado')      THEN 1 ELSE 0 END) AS convertido,
        SUM(CASE WHEN status IN ('reprovado','cancelado')      THEN 1 ELSE 0 END) AS reprovado
     FROM requisicoes
     WHERE data_requisicao >= '$dataInicio'"
);
if ($r) $funil = $r->fetch_assoc();

// ── Tendência semanal ──────────────────────────────────────────────────────
$tendencia = [];
$r = $conexao->query(
    "SELECT
        DATE_FORMAT(data_requisicao,'%Y-%u')        AS semana,
        DATE_FORMAT(MIN(data_requisicao),'%d/%m')   AS label,
        COUNT(*)                                    AS leads,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS conversoes,
        ROUND(SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END)
              / COUNT(*) * 100, 1)                  AS taxa
     FROM requisicoes
     WHERE data_requisicao >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
     GROUP BY semana
     ORDER BY semana ASC"
);
if ($r) while ($row = $r->fetch_assoc()) $tendencia[] = $row;

// ── Segmentação por categoria ──────────────────────────────────────────────
$segmentacao = [];
$r = $conexao->query(
    "SELECT
        categoria,
        COUNT(*) AS quantidade,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS aprovados
     FROM requisicoes
     WHERE categoria IS NOT NULL AND categoria <> ''
     GROUP BY categoria
     ORDER BY quantidade DESC"
);
if ($r) while ($row = $r->fetch_assoc()) $segmentacao[] = $row;

// ── Canais (tipo de requisição) ────────────────────────────────────────────
$canais = [];
$r = $conexao->query(
    "SELECT
        tipo AS origem,
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS conversoes,
        ROUND(SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END)
              / COUNT(*) * 100, 1) AS taxa_conversao
     FROM requisicoes
     WHERE tipo IS NOT NULL AND tipo <> ''
     GROUP BY tipo
     ORDER BY total DESC"
);
if ($r) while ($row = $r->fetch_assoc()) $canais[] = $row;

// ── Melhores horários ──────────────────────────────────────────────────────
$horarios = [];
$r = $conexao->query(
    "SELECT
        HOUR(data_requisicao) AS hora,
        COUNT(*) AS interacoes,
        SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END) AS conversoes,
        ROUND(SUM(CASE WHEN status IN ('aprovado','finalizado') THEN 1 ELSE 0 END)
              / COUNT(*) * 100, 1) AS taxa
     FROM requisicoes
     GROUP BY HOUR(data_requisicao)
     ORDER BY conversoes DESC
     LIMIT 10"
);
if ($r) while ($row = $r->fetch_assoc()) $horarios[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER – Advanced Analytics</title>
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
                        <i class="fa fa-chart-bar text-primary me-2" aria-hidden="true"></i>
                        Advanced Analytics
                    </h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Analytics</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <form method="get" class="d-inline-flex align-items-center gap-2">
                        <label class="text-muted small mb-0" for="sel-periodo">Período:</label>
                        <select id="sel-periodo" name="periodo" class="form-select form-select-sm"
                                style="width:auto" onchange="this.form.submit()">
                            <option value="7"  <?php echo $periodo ==  7 ? 'selected' : ''; ?>>7 dias</option>
                            <option value="30" <?php echo $periodo == 30 ? 'selected' : ''; ?>>30 dias</option>
                            <option value="90" <?php echo $periodo == 90 ? 'selected' : ''; ?>>90 dias</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 dashboard-grid">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-blue"><i class="fa fa-file-signature" aria-hidden="true"></i></span>
                        <small class="text-muted">Período</small>
                    </div>
                    <p class="value"><?php echo number_format((int) $funil['total_leads'], 0, ',', '.'); ?></p>
                    <p class="label">Total requisições</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-orange"><i class="fa fa-search" aria-hidden="true"></i></span>
                        <small class="text-muted">Análise</small>
                    </div>
                    <p class="value"><?php echo (int) $funil['em_analise']; ?></p>
                    <p class="label">Em análise</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-green"><i class="fa fa-check-double" aria-hidden="true"></i></span>
                        <small class="text-muted">Resultado</small>
                    </div>
                    <p class="value"><?php echo (int) $funil['convertido']; ?></p>
                    <p class="label">Convertidas</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-indigo"><i class="fa fa-times-circle" aria-hidden="true"></i></span>
                        <small class="text-muted">Perdas</small>
                    </div>
                    <p class="value"><?php echo (int) $funil['reprovado']; ?></p>
                    <p class="label">Reprovadas / canceladas</p>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mt-1">
            <div class="col-xl-7">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Tendências semanais</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartTendencias" aria-label="Tendências semanais" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Taxa de conversão por canal</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartCanais" aria-label="Conversão por canal" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabelas analíticas -->
        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-tags text-primary me-2" aria-hidden="true"></i>
                            Segmentação por categoria
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-center">Qtd.</th>
                                        <th class="text-center">Aprovadas</th>
                                        <th class="text-center">Taxa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($segmentacao)): ?>
                                    <?php foreach ($segmentacao as $seg):
                                        $pct = $seg['quantidade'] > 0
                                            ? round($seg['aprovados'] / $seg['quantidade'] * 100, 1)
                                            : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($seg['categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center"><?php echo $seg['quantidade']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $seg['aprovados']; ?></span>
                                        </td>
                                        <td class="text-center"><?php echo $pct; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Sem dados no período.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-clock text-warning me-2" aria-hidden="true"></i>
                            Melhores horários para conversão
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th class="text-center">Interações</th>
                                        <th class="text-center">Conversões</th>
                                        <th class="text-center">Taxa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($horarios)): ?>
                                    <?php foreach ($horarios as $h): ?>
                                    <tr>
                                        <td><?php echo str_pad((int) $h['hora'], 2, '0', STR_PAD_LEFT) . ':00'; ?></td>
                                        <td class="text-center"><?php echo $h['interacoes']; ?></td>
                                        <td class="text-center"><?php echo $h['conversoes']; ?></td>
                                        <td class="text-center"><?php echo $h['taxa']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Sem dados disponíveis.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /page-wrapper -->

<?php include '../footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const semanas    = <?php echo json_encode(array_column($tendencia, 'label'),          JSON_UNESCAPED_UNICODE); ?>;
    const leads      = <?php echo json_encode(array_map('intval',   array_column($tendencia, 'leads'))); ?>;
    const taxas      = <?php echo json_encode(array_map('floatval', array_column($tendencia, 'taxa'))); ?>;
    const canalNomes = <?php echo json_encode(array_column($canais, 'origem'),            JSON_UNESCAPED_UNICODE); ?>;
    const canalTaxas = <?php echo json_encode(array_map('floatval', array_column($canais, 'taxa_conversao'))); ?>;

    const ctxT = document.getElementById('chartTendencias');
    if (ctxT) {
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: semanas,
                datasets: [
                    {
                        label: 'Requisições',
                        data: leads,
                        backgroundColor: 'rgba(15,76,129,0.75)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Taxa Conv. %',
                        data: taxas,
                        type: 'line',
                        borderColor: '#2f9e8f',
                        backgroundColor: 'transparent',
                        yAxisID: 'y1',
                        tension: 0.4,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y:  { beginAtZero: true, ticks: { precision: 0 } },
                    y1: { position: 'right', beginAtZero: true, max: 100,
                          grid: { drawOnChartArea: false } }
                }
            }
        });
    }

    const ctxC = document.getElementById('chartCanais');
    if (ctxC) {
        new Chart(ctxC, {
            type: 'bar',
            data: {
                labels: canalNomes,
                datasets: [{
                    label: 'Taxa Conversão (%)',
                    data: canalTaxas,
                    backgroundColor: ['#0f4c81','#2f9e8f','#f0a202','#7b6dff','#e76f51','#8f9fb3']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: { x: { beginAtZero: true, max: 100 } },
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
</body>
</html>



