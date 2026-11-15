<?php
/**
 * ========================================
 * admin.php - VERSÃƒO CORRIGIDA
 * Painel administrativo com dashboard melhorado
 * ========================================
 */

session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
include_once('../php/conexao.php');
include_once('../php/metrics.php');
include_once('../php/auditoria.php');

// Verificar autenticaÃ§Ã£o
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Inicializar auditoria
$auditoria = new Auditoria($conexao);

// Obter mÃ©tricas do dashboard
$metrics = getDashboardMetrics($conexao);
$totalVisitas = $metrics['total_visitas'];
$totalDepoimentos = $metrics['total_depoimentos'];
$total_requisicoes_realizadas = $metrics['total_requisicoes'];
$porcentagem_requisicoes_realizadas = $metrics['taxa_requisicoes'];
$media_tempo = $metrics['media_tempo'];

// Registrar acesso ao dashboard em auditoria
$auditoria->registrar('VIEW_DASHBOARD', 'dashboard', null, null, 
                      json_encode(['timestamp' => date('Y-m-d H:i:s')]), $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Painel Administrativo">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Painel Administrativo</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js para grÃ¡ficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <style>
        /* Melhorias de UI */
        .dash-count {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .dash-count:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .dash-count::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #0056b3);
        }
        
        .spark-chart {
            height: 40px;
            margin-top: 10px;
        }
        
        .stat-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .stat-badge.up {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-badge.down {
            background: #f8d7da;
            color: #721c24;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
    <!-- include da navbar -->
    <?php include 'navbar.php'; ?>

    <!-- include da sidebar -->
    

    <!-- conteÃºdo principal -->
    <div class="page-wrapper">
        <div class="content">
            <!-- Header com data e mensagem -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>Bem-vindo!</strong> VocÃª estÃ¡ no painel administrativo. 
                        Data e hora: <strong><?php echo date('d/m/Y H:i:s'); ?></strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>

            <!-- EstatÃ­sticas -->
            <div class="row">
                <!-- Total de Visitas -->
                <div class="col-lg-3 col-sm-6 col-12 d-flex mb-3">
                    <div class="dash-count dash-count-primary flex-fill">
                        <div class="dash-counts">
                            <h3 class="rate-percentage"><?php echo isset($totalVisitas) ? htmlspecialchars($totalVisitas) : '0'; ?></h3>
                            <h5>Total de Visitas</h5>
                            <small class="text-muted">Este mÃªs</small>
                            <div class="spark-chart">
                                <canvas id="sparkVisitas" width="200" height="40"></canvas>
                            </div>
                        </div>
                        <div class="dash-imgs">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <!-- Total de Depoimentos -->
                <div class="col-lg-3 col-sm-6 col-12 d-flex mb-3">
                    <div class="dash-count dash-count-success flex-fill">
                        <div class="dash-counts">
                            <h3 class="rate-percentage"><?php echo isset($totalDepoimentos) ? htmlspecialchars($totalDepoimentos) : '0'; ?></h3>
                            <h5>Total de Depoimentos</h5>
                            <small class="text-muted">Aprovados</small>
                            <span class="stat-badge up">â†‘ 5% vs mÃªs anterior</span>
                        </div>
                        <div class="dash-imgs">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>

                <!-- Total de RequisiÃ§Ãµes -->
                <div class="col-lg-3 col-sm-6 col-12 d-flex mb-3">
                    <div class="dash-count dash-count-warning flex-fill">
                        <div class="dash-counts">
                            <h3 class="rate-percentage">
                                <?php echo isset($total_requisicoes_realizadas) ? htmlspecialchars($total_requisicoes_realizadas) : '0'; ?>
                            </h3>
                            <p class="text-muted mb-0">
                                <?php echo isset($porcentagem_requisicoes_realizadas) ? round($porcentagem_requisicoes_realizadas, 1) : '0.0'; ?>% 
                                <span class="text-success">aprovadas</span>
                            </p>
                            <h5>Total de RequisiÃ§Ãµes</h5>
                            <small class="text-muted">Este mÃªs</small>
                        </div>
                        <div class="dash-imgs">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>

                <!-- Tempo MÃ©dio no Site -->
                <div class="col-lg-3 col-sm-6 col-12 d-flex mb-3">
                    <div class="dash-count dash-count-info flex-fill">
                        <div class="dash-counts">
                            <h3 class="rate-percentage">
                                <?php echo isset($media_tempo) ? htmlspecialchars($media_tempo) : '0m'; ?>
                            </h3>
                            <h5>Tempo MÃ©dio no Site</h5>
                            <small class="text-muted">Por visitante</small>
                        </div>
                        <div class="dash-imgs">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GrÃ¡ficos -->
            <div class="row mt-4">
                <div class="col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title">Visitas nos Ãšltimos 7 Dias</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chartVisitas" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title">RequisiÃ§Ãµes por Tipo</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="chartRequisicoes" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de RequisiÃ§Ãµes Recentes -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title">RequisiÃ§Ãµes Recentes</h5>
                        </div>
                        <div class="col-auto">
                            <a href="requisicoes.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-right"></i> Ver Todas
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Telefone</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>AÃ§Ã£o</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Consulta SQL para obter as Ãºltimas 10 requisiÃ§Ãµes
                                $sql = "SELECT c.id_cliente, c.nome, c.email, c.telefone, r.id_requisicao, r.categoria, 
                                        r.status, r.data_requisicao
                                        FROM clientes c
                                        INNER JOIN requisicoes r ON c.id_cliente = r.id_cliente
                                        ORDER BY r.data_requisicao DESC
                                        LIMIT 10";
                                
                                $resultado = mysqli_query($conexao, $sql);
                                
                                if ($resultado && mysqli_num_rows($resultado) > 0) {
                                    while ($row = mysqli_fetch_assoc($resultado)) {
                                        $status_class = 'status-pending';
                                        if ($row['status'] === 'aprovada') $status_class = 'status-approved';
                                        elseif ($row['status'] === 'rejeitada') $status_class = 'status-rejected';
                                        
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row["nome"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["telefone"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["categoria"]) . "</td>";
                                        echo "<td><span class='status-badge {$status_class}'>" . ucfirst($row["status"]) . "</span></td>";
                                        echo "<td>" . date('d/m/Y H:i', strtotime($row["data_requisicao"])) . "</td>";
                                        echo "<td><a href='requisicoes.php?id=" . $row['id_requisicao'] . "' class='btn btn-sm btn-info'><i class='fas fa-eye'></i></a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center text-muted'>Nenhuma requisiÃ§Ã£o encontrada.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- include do footer -->
    <?php include 'footer.php'; ?>

    <script>
        // GrÃ¡fico de visitas
        const ctxVisitas = document.getElementById('chartVisitas').getContext('2d');
        new Chart(ctxVisitas, {
            type: 'line',
            data: {
                labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'],
                datasets: [{
                    label: 'Visitas',
                    data: [65, 59, 80, 81, 56, 55, 40],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // GrÃ¡fico de requisiÃ§Ãµes por tipo
        const ctxRequisicoes = document.getElementById('chartRequisicoes').getContext('2d');
        new Chart(ctxRequisicoes, {
            type: 'doughnut',
            data: {
                labels: ['PF', 'PJ', 'Rural', 'Microempresa'],
                datasets: [{
                    data: [30, 25, 20, 25],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>


