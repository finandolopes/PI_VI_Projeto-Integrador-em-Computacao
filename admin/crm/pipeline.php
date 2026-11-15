<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../php/access_policy_v7.php';
cfRequirePermission('crm.view');

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

// ── Estágios mapeados para status reais de requisicoes ─────────────────────
$stages = [
    'pendente'   => ['label' => 'Pendente',   'color' => '#0f4c81', 'icon' => 'fa-clock'],
    'em_analise' => ['label' => 'Em Análise', 'color' => '#f0a202', 'icon' => 'fa-search'],
    'aprovado'   => ['label' => 'Aprovado',   'color' => '#2f9e8f', 'icon' => 'fa-check'],
    'reprovado'  => ['label' => 'Reprovado',  'color' => '#e76f51', 'icon' => 'fa-times'],
    'cancelado'  => ['label' => 'Cancelado',  'color' => '#8f9fb3', 'icon' => 'fa-ban'],
];

// ── Carregar cards por status ──────────────────────────────────────────────
$pipelineData = [];
foreach (array_keys($stages) as $status) {
    $s = $conexao->prepare(
        "SELECT r.id_requisicao, c.nome, r.categoria, r.tipo, r.data_requisicao
         FROM requisicoes r
         INNER JOIN clientes c ON r.id_cliente = c.id_cliente
         WHERE r.status = ?
         ORDER BY r.data_requisicao DESC
         LIMIT 20"
    );
    if ($s) {
        $s->bind_param('s', $status);
        $s->execute();
        $res = $s->get_result();
        $pipelineData[$status] = [];
        while ($row = $res->fetch_assoc()) $pipelineData[$status][] = $row;
        $s->close();
    }
}

// ── KPIs ──────────────────────────────────────────────────────────────────
$totalLeads = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes");
if ($r) $totalLeads = (int) $r->fetch_assoc()['t'];

$totalHoje = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes WHERE DATE(data_requisicao) = CURDATE()");
if ($r) $totalHoje = (int) $r->fetch_assoc()['t'];

$totalAprov = 0;
$r = $conexao->query("SELECT COUNT(*) AS t FROM requisicoes WHERE status = 'aprovado'");
if ($r) $totalAprov = (int) $r->fetch_assoc()['t'];

$taxaConv = $totalLeads > 0 ? round($totalAprov / $totalLeads * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER – Pipeline CRM</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.png">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap5-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .kanban-wrap          { display:flex; gap:16px; overflow-x:auto; padding-bottom:8px; }
        .kanban-col           { flex:0 0 260px; background:#f8f9fb; border-radius:10px; overflow:hidden; border:1px solid #e9ecef; }
        .kanban-head          { padding:12px 16px; color:#fff; font-weight:700; font-size:.85rem;
                                display:flex; justify-content:space-between; align-items:center; }
        .kanban-body          { padding:10px; min-height:100px; }
        .kanban-card          { background:#fff; border-radius:8px; padding:11px 13px; margin-bottom:9px;
                                box-shadow:0 1px 4px rgba(0,0,0,.07); border-left:3px solid #dee2e6; }
        .kanban-card:last-child { margin-bottom:0; }
        .kanban-card .k-nome  { font-weight:600; font-size:.85rem; color:#212529; margin-bottom:3px; }
        .kanban-card .k-tipo  { font-size:.78rem; color:#6c757d; }
        .kanban-card .k-meta  { font-size:.76rem; color:#8f9fb3; margin-top:5px;
                                display:flex; justify-content:space-between; }
    </style>
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
                        <i class="fa fa-columns text-primary me-2" aria-hidden="true"></i>
                        Pipeline CRM
                    </h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Pipeline CRM</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <a href="../requisicoes.php" class="btn btn-sm btn-primary">
                        <i class="fa fa-list me-1" aria-hidden="true"></i>Lista completa
                    </a>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 dashboard-grid">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-blue"><i class="fa fa-database" aria-hidden="true"></i></span>
                        <small class="text-muted">Total</small>
                    </div>
                    <p class="value"><?php echo number_format($totalLeads, 0, ',', '.'); ?></p>
                    <p class="label">Requisições cadastradas</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-orange"><i class="fa fa-calendar-day" aria-hidden="true"></i></span>
                        <small class="text-muted">Hoje</small>
                    </div>
                    <p class="value"><?php echo $totalHoje; ?></p>
                    <p class="label">Novas hoje</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-green"><i class="fa fa-check-circle" aria-hidden="true"></i></span>
                        <small class="text-muted">Resultado</small>
                    </div>
                    <p class="value"><?php echo $totalAprov; ?></p>
                    <p class="label">Aprovadas</p>
                </div>
            </div>
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="top">
                        <span class="metric-icon metric-indigo"><i class="fa fa-percentage" aria-hidden="true"></i></span>
                        <small class="text-muted">Eficiência</small>
                    </div>
                    <p class="value"><?php echo $taxaConv; ?>%</p>
                    <p class="label">Taxa de aprovação</p>
                </div>
            </div>
        </div>

        <!-- Board Kanban -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fa fa-th-large text-primary me-2" aria-hidden="true"></i>
                    Board de Requisições
                </h5>
                <small class="text-muted">Últimas 20 por coluna</small>
            </div>
            <div class="card-body">
                <div class="kanban-wrap">
                    <?php foreach ($stages as $statusKey => $info): ?>
                    <div class="kanban-col">
                        <div class="kanban-head" style="background:<?php echo $info['color']; ?>">
                            <span>
                                <i class="fa <?php echo $info['icon']; ?> me-1" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="badge bg-white text-dark"><?php echo count($pipelineData[$statusKey] ?? []); ?></span>
                        </div>
                        <div class="kanban-body">
                            <?php if (!empty($pipelineData[$statusKey])): ?>
                                <?php foreach ($pipelineData[$statusKey] as $card): ?>
                                <div class="kanban-card" style="border-left-color:<?php echo $info['color']; ?>">
                                    <div class="k-nome"><?php echo htmlspecialchars($card['nome'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="k-tipo"><?php echo htmlspecialchars($card['tipo'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="k-meta">
                                        <span><?php echo htmlspecialchars($card['categoria'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo date('d/m', strtotime($card['data_requisicao'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted small py-3 mb-0">Nenhuma</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /page-wrapper -->

<?php include '../footer.php'; ?>
</body>
</html>



