<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once '../php/conexao.php';
include_once '../php/metrics.php';

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Faça login para acessar o painel administrativo.';
    header('Location: ../index.php');
    exit();
}

$metrics = getDashboardMetrics($conexao);
$totalVisitas = (int) ($metrics['total_visitas'] ?? 0);
$totalDepoimentos = (int) ($metrics['total_depoimentos'] ?? 0);
$totalRequisicoes = (int) ($metrics['total_requisicoes'] ?? 0);
$taxaRequisicoes = (float) ($metrics['taxa_requisicoes'] ?? 0);
$mediaTempo = (string) ($metrics['media_tempo'] ?? '00:00:00');
$taxaConversao = (float) ($metrics['taxa_conversao'] ?? 0);

$recentes = [];
$sqlRecentes = "SELECT c.nome, c.email, c.telefone, r.id_requisicao, r.categoria, r.status, r.data_requisicao
                FROM requisicoes r
                INNER JOIN clientes c ON c.id_cliente = r.id_cliente
                ORDER BY r.data_requisicao DESC, r.id_requisicao DESC
                LIMIT 10";
$resultRecentes = $conexao->query($sqlRecentes);
if ($resultRecentes) {
    while ($row = $resultRecentes->fetch_assoc()) {
        $recentes[] = $row;
    }
}

$labelsDias = [];
$dadosVisitas = [];
$mapVisitas = [];
$sqlVisitas = "SELECT DATE(data_visita) AS dia, COUNT(*) AS total
               FROM contador_visitas
               WHERE data_visita >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               GROUP BY DATE(data_visita)
               ORDER BY dia ASC";
$resultVisitas = $conexao->query($sqlVisitas);
if ($resultVisitas) {
    while ($row = $resultVisitas->fetch_assoc()) {
        $mapVisitas[(string) $row['dia']] = (int) $row['total'];
    }
}

for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-{$i} day"));
    $labelsDias[] = date('d/m', strtotime($dia));
    $dadosVisitas[] = $mapVisitas[$dia] ?? 0;
}

$categoriaMap = [];
$sqlCategorias = "SELECT categoria FROM requisicoes WHERE categoria IS NOT NULL AND categoria <> ''";
$resultCategorias = $conexao->query($sqlCategorias);
if ($resultCategorias) {
    while ($row = $resultCategorias->fetch_assoc()) {
        $raw = (string) ($row['categoria'] ?? '');
        if ($raw === '') {
            continue;
        }
        foreach (explode(',', $raw) as $item) {
            $cat = trim($item);
            if ($cat === '') {
                continue;
            }
            if (!isset($categoriaMap[$cat])) {
                $categoriaMap[$cat] = 0;
            }
            $categoriaMap[$cat]++;
        }
    }
}

$categorias = ['Sem dados'];
$dadosCategorias = [0];
if (!empty($categoriaMap)) {
    arsort($categoriaMap);
    $categoriaMap = array_slice($categoriaMap, 0, 6, true);
    $categorias = array_keys($categoriaMap);
    $dadosCategorias = array_values($categoriaMap);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Painel administrativo">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Painel Administrativo</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/bootstrap5-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title">Dashboard administrativo</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item active">Visão geral operacional</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <span class="badge bg-light text-dark">Atualizado em <?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 dashboard-grid">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-blue"><i class="fa fa-chart-line" aria-hidden="true"></i></span>
                        <small class="text-muted">Tráfego</small>
                    </div>
                    <p class="value" id="metric-total-visitas"><?php echo number_format($totalVisitas, 0, ',', '.'); ?></p>
                    <p class="label">Total de visitas</p>
                </div>
            </div>

            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-green"><i class="fa fa-file-signature" aria-hidden="true"></i></span>
                        <small class="text-muted">Captação</small>
                    </div>
                    <p class="value" id="metric-total-requisicoes"><?php echo number_format($totalRequisicoes, 0, ',', '.'); ?></p>
                    <p class="label">Requisições de crédito</p>
                </div>
            </div>

            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-orange"><i class="fa fa-comment-dots" aria-hidden="true"></i></span>
                        <small class="text-muted">Prova social</small>
                    </div>
                    <p class="value" id="metric-total-depoimentos"><?php echo number_format($totalDepoimentos, 0, ',', '.'); ?></p>
                    <p class="label">Depoimentos cadastrados</p>
                </div>
            </div>

            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-indigo"><i class="fa fa-percentage" aria-hidden="true"></i></span>
                        <small class="text-muted">Eficiência</small>
                    </div>
                    <p class="value"><?php echo number_format($taxaConversao, 2, ',', '.'); ?>%</p>
                    <p class="label">Taxa de conversão</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="card chart-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Visitas dos últimos 7 dias</h5>
                        <small class="text-muted">Tempo médio no site: <?php echo htmlspecialchars($mediaTempo, ENT_QUOTES, 'UTF-8'); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartVisitas" aria-label="Gráfico de visitas" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card chart-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Categorias de requisição</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-canvas">
                            <canvas id="chartCategorias" aria-label="Categorias de requisição" role="img"></canvas>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">Taxa de requisições por cliente: <?php echo number_format($taxaRequisicoes, 1, ',', '.'); ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Requisições recentes</h5>
                <a href="requisicoes.php" class="btn btn-sm btn-primary">Ver todas</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Categoria</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($recentes)): ?>
                            <?php foreach ($recentes as $row): ?>
                                <?php $status = (string) ($row['status'] ?? 'pendente'); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['telefone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['categoria'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="status-pill status-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) ucfirst(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) ($row['data_requisicao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end">
                                        <a href="requisicoes.php" class="btn btn-sm btn-outline-primary" aria-label="Ver requisição">
                                            <i class="fa fa-eye" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nenhuma requisição encontrada.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const visitasCtx = document.getElementById('chartVisitas');
    const categoriasCtx = document.getElementById('chartCategorias');

    if (visitasCtx) {
        new Chart(visitasCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labelsDias, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'Visitas',
                    data: <?php echo json_encode($dadosVisitas); ?>,
                    borderColor: '#0f4c81',
                    backgroundColor: 'rgba(15, 76, 129, 0.15)',
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

    if (categoriasCtx) {
        new Chart(categoriasCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categorias, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($dadosCategorias); ?>,
                    backgroundColor: ['#0f4c81', '#2f9e8f', '#f0a202', '#7b6dff', '#e76f51', '#8f9fb3']
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
