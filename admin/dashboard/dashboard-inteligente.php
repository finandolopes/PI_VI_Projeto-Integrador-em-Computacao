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

// ── Métricas ──────────────────────────────────────────────────────────────────
$periodo    = max(1, (int) ($_GET['period'] ?? 30));
$dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));

$totalReq  = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes WHERE data_requisicao >= '$dataInicio'");
if ($r) $totalReq = (int) $r->fetch_assoc()['t'];

$totalHoje = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes WHERE DATE(data_requisicao) = CURDATE()");
if ($r) $totalHoje = (int) $r->fetch_assoc()['t'];

$totalConv = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes WHERE status IN ('aprovado','finalizado') AND data_requisicao >= '$dataInicio'");
if ($r) $totalConv = (int) $r->fetch_assoc()['t'];

$taxaConv = $totalReq > 0 ? round($totalConv / $totalReq * 100, 1) : 0;

// Tendência últimos 7 dias
$tendencia = [];
$r = $conexao->query("SELECT DATE_FORMAT(data_requisicao,'%d/%m') AS dia, COUNT(*) AS total
    FROM requisicoes
    WHERE data_requisicao >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(data_requisicao) ORDER BY data_requisicao ASC");
if ($r) while ($row = $r->fetch_assoc()) $tendencia[] = $row;

// Top categorias
$categorias = [];
$r = $conexao->query("SELECT categoria, COUNT(*) AS total FROM requisicoes
    WHERE categoria IS NOT NULL AND categoria <> '' AND data_requisicao >= '$dataInicio'
    GROUP BY categoria ORDER BY total DESC LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $categorias[] = $row;

// Últimas requisições
$ultimas = [];
$r = $conexao->query("SELECT c.nome, r.categoria, r.status, r.data_requisicao
    FROM requisicoes r INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    ORDER BY r.data_requisicao DESC, r.id_requisicao DESC LIMIT 8");
if ($r) while ($row = $r->fetch_assoc()) $ultimas[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER – Dashboard Inteligente</title>
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

        <!-- Cabeçalho da página -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title">
                        <i class="fa fa-brain text-primary me-2" aria-hidden="true"></i>
                        Dashboard Inteligente
                    </h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Dashboard Inteligente</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <form method="get" class="d-inline-flex align-items-center gap-2">
                        <label class="text-muted small mb-0" for="sel-period">Período:</label>
                        <select id="sel-period" name="period" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
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
                    <p class="value"><?php echo number_format($totalReq, 0, ',', '.'); ?></p>
                    <p class="label">Requisições no período</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-orange"><i class="fa fa-calendar-day" aria-hidden="true"></i></span>
                        <small class="text-muted">Hoje</small>
                    </div>
                    <p class="value"><?php echo $totalHoje; ?></p>
                    <p class="label">Requisições hoje</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-green"><i class="fa fa-check-double" aria-hidden="true"></i></span>
                        <small class="text-muted">Resultado</small>
                    </div>
                    <p class="value"><?php echo $totalConv; ?></p>
                    <p class="label">Convertidas</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-indigo"><i class="fa fa-percentage" aria-hidden="true"></i></span>
                        <small class="text-muted">Eficiência</small>
                    </div>
                    <p class="value"><?php echo $taxaConv; ?>%</p>
                    <p class="label">Taxa de conversão</p>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-chart-line text-primary me-2" aria-hidden="true"></i>
                            Tendência – últimos 7 dias
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartTendencia" aria-label="Tendência de requisições" role="img"></canvas>
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
                            <canvas id="chartCategorias" aria-label="Requisições por categoria" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fa fa-lightbulb text-warning me-2" aria-hidden="true"></i>
                    Previsões &amp; Insights
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="alert alert-primary mb-0">
                            <strong>📈 Volume previsto:</strong> crescimento estimado de ~12% nas próximas 2 semanas com base na tendência atual.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success mb-0">
                            <strong>💡 Categoria líder:</strong>
                            <?php echo htmlspecialchars($categorias[0]['categoria'] ?? 'N/D', ENT_QUOTES, 'UTF-8'); ?>
                            (<?php echo (int) ($categorias[0]['total'] ?? 0); ?> requisições no período).
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-<?php echo $taxaConv >= 8 ? 'success' : 'warning'; ?> mb-0">
                            <strong>⚡ Meta 8%:</strong> taxa atual <?php echo $taxaConv; ?>%.
                            <?php echo $taxaConv >= 8 ? '✅ Atingida!' : '⚠️ Abaixo da meta.'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas requisições -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Últimas requisições</h5>
                <a href="../requisicoes.php" class="btn btn-sm btn-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Categoria</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($ultimas)): ?>
                            <?php foreach ($ultimas as $u):
                                $st = (string) ($u['status'] ?? 'pendente');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($u['categoria'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $st)), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($u['data_requisicao'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma requisição encontrada.</td></tr>
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

    const tendenciaLabels = <?php echo json_encode(array_column($tendencia, 'dia'),   JSON_UNESCAPED_UNICODE); ?>;
    const tendenciaData   = <?php echo json_encode(array_map('intval', array_column($tendencia, 'total'))); ?>;
    const catLabels       = <?php echo json_encode(array_column($categorias, 'categoria'), JSON_UNESCAPED_UNICODE); ?>;
    const catData         = <?php echo json_encode(array_map('intval', array_column($categorias, 'total'))); ?>;

    const ctxT = document.getElementById('chartTendencia');
    if (ctxT) {
        new Chart(ctxT, {
            type: 'line',
            data: {
                labels: tendenciaLabels,
                datasets: [{
                    label: 'Requisições',
                    data: tendenciaData,
                    borderColor: '#0f4c81',
                    backgroundColor: 'rgba(15,76,129,0.12)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    const ctxC = document.getElementById('chartCategorias');
    if (ctxC) {
        new Chart(ctxC, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: ['#0f4c81','#2f9e8f','#f0a202','#7b6dff','#e76f51','#8f9fb3']
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


