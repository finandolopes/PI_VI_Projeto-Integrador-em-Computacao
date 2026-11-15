<?php
/**
 * ========================================
 * AUDIT LOGS VIEWER - Visualizar Registros de Auditoria
 * ========================================
 * Interface para visualizar e gerenciar logs de auditoria
 * Data: 07/02/2026
 */

session_start();

// Verificar autenticaÃ§Ã£o
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Incluir arquivos necessÃ¡rios
include_once('../php/conexao.php');
include_once('../php/security.php');
include_once('../php/RoleManager.php');
include_once('../php/AuditLogger.php');

// Inicializar gerenciadores
$roleManager = new RoleManager($conexao);
$auditLogger = new AuditLogger($conexao);

// Verificar permissÃ£o
if (!$roleManager->checkPermission($_SESSION['user_id'], 'audit.view')) {
    header("Location: unauthorized.php");
    exit;
}

// Registrar acesso
$auditLogger->log('VIEW', 'Acessou pÃ¡gina de auditoria', $_SESSION['user_id']);

// ParÃ¢metros de filtro
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = ($page - 1) * $limit;

$filters = [];
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $filters['user_id'] = intval($_GET['user_id']);
}
if (isset($_GET['action_type']) && in_array($_GET['action_type'], array_keys($auditLogger::ACTION_TYPES))) {
    $filters['action_type'] = $_GET['action_type'];
}
if (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Obter logs
$logs = $auditLogger->getLogs($limit, $offset, $filters);
$totalLogs = $auditLogger->countLogs($filters);
$totalPages = ceil($totalLogs / $limit);

// Obter estatÃ­sticas
$stats = $auditLogger->getStatistics();

// Obter lista de usuÃ¡rios para o filtro
$usersResult = $conexao->query("SELECT id, nome FROM usuarios ORDER BY nome");
$users = $usersResult ? $usersResult->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - CONFINTER</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .log-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .action-CREATE { background-color: #28a745; }
        .action-UPDATE { background-color: #007bff; }
        .action-DELETE { background-color: #dc3545; }
        .action-LOGIN { background-color: #17a2b8; }
        .action-LOGOUT { background-color: #6c757d; }
        .action-FAILED_LOGIN { background-color: #fd7e14; }

        .log-detail {
            max-height: 300px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h5 {
            font-weight: 600;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .stat-card .number {
            font-size: 1.75rem;
            font-weight: bold;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
    </style>
</head>
<body>
    <?php include_once('navbar.php'); ?>
    

    <div class="page-wrapper">
        <div class="content container-fluid">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-sm-8">
                        <h3 class="page-title">Logs de auditoria</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Auditoria</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- EstatÃ­sticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h5>Total de Logs</h5>
                        <div class="number"><?php echo number_format($stats['total_logs']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h5>Ãšltimos 7 Dias</h5>
                        <div class="number">
                            <?php 
                            $last7Days = array_reduce($stats['logs_last_7_days'], function($carry, $item) {
                                return $carry + $item['count'];
                            }, 0);
                            echo number_format($last7Days);
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <h5>UsuÃ¡rios Ativos</h5>
                        <div class="number"><?php echo count($stats['most_active_users']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <h5>Tipos de AÃ§Ã£o</h5>
                        <div class="number"><?php echo count($stats['logs_by_action']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-filter"></i> Filtros</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">UsuÃ¡rio</label>
                            <select name="user_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo isset($filters['user_id']) && $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>
                                        <?php echo htmlspecialchars($user['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipo de AÃ§Ã£o</label>
                            <select name="action_type" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($auditLogger::ACTION_TYPES as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo isset($filters['action_type']) && $filters['action_type'] == $key ? 'selected' : ''; ?>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">De</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>"
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">AtÃ©</label>
                            <input type="date" name="date_to" class="form-control"
                                   value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>"
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>

                        <div class="col-12">
                            <a href="audit-logs.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-redo"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabela de Logs -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list"></i> Registros (<?php echo $totalLogs; ?> total)</h6>
                    <span class="badge bg-secondary">PÃ¡gina <?php echo $page; ?> de <?php echo $totalPages; ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data/Hora</th>
                                <th>Tipo</th>
                                <th>UsuÃ¡rio</th>
                                <th>DescriÃ§Ã£o</th>
                                <th>Tabela</th>
                                <th>IP Address</th>
                                <th>AÃ§Ãµes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox"></i> Nenhum log encontrado
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge log-badge action-<?php echo $log['action_type']; ?>"
                                                <?php echo $log['action_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($log['user_id']) {
                                                $userResult = $conexao->prepare("SELECT nome FROM usuarios WHERE id = ");
                                                $userResult->bind_param("i", $log['user_id']);
                                                $userResult->execute();
                                                $userData = $userResult->get_result()->fetch_assoc();
                                                echo htmlspecialchars($userData['nome'] ?? 'Unknown');
                                                $userResult->close();
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($log['description'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <?php echo $log['affected_table'] ? htmlspecialchars($log['affected_table']) : '-'; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                    data-bs-target="#logModal" 
                                                    onclick="showLogDetail(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                                                <i class="fas fa-eye"></i> Ver
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PaginaÃ§Ã£o -->
                <?php if ($totalPages > 1): ?>
                    <?php $queryBase = http_build_query($filters); ?>
                    <?php $querySuffix = $queryBase !== '' ? '&' . $queryBase : ''; ?>
                    <div class="card-footer bg-light">
                        <nav>
                            <ul class="pagination mb-0 justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="page=1<?php echo $querySuffix; ?>">Primeira</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="page=<?php echo $page - 1; ?><?php echo $querySuffix; ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"
                                        <a class="page-link" href="page=<?php echo $i; ?><?php echo $querySuffix; ?>"?>
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="page=<?php echo $page + 1; ?><?php echo $querySuffix; ?>">PrÃ³xima</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="page=<?php echo $totalPages; ?><?php echo $querySuffix; ?>">Ãšltima</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para detalhes do log -->
    <div class="modal fade" id="logModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="log-detail" id="logDetail"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function showLogDetail(log) {
            const detail = document.getElementById('logDetail');
            let html = '<pre style="margin: 0;">';
            html += JSON.stringify(log, null, 2);
            html += '</pre>';
            detail.innerHTML = html;
        }
    </script>
</body>
</html>

<?php $conexao->close(); ?>




