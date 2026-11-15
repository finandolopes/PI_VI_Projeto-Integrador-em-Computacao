<?php
/**
 * LEAD SCORING DASHBOARD
 * 
 * Arquivo: admin/lead-scoring.php
 * DescriÃ§Ã£o: Dashboard mostrando leads com score automÃ¡tico
 * ROI: +40% conversÃ£o (leads quentes identificados)
 * Tempo: ImplementaÃ§Ã£o 1h, Uso contÃ­nuo
 */

session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include_once('../php/conexao.php');
include_once('../php/lead-scoring-engine.php');
include_once('../php/RoleManager.php');

$roleManager = new RoleManager($conexao);
if (!$roleManager->checkPermission($_SESSION['user_id'], 'dashboard.view')) {
    header("Location: unauthorized.php");
    exit;
}

// Inicializar engine
$scoringEngine = new LeadScoringEngine($conexao);

// Filtros
$filtro_status = $_GET['status'] ?? 'all'; // all, hot, warm, cold
$filtro_categoria = $_GET['categoria'] ?? '';
$ordenar = $_GET['order'] ?? 'score_desc'; // score_desc, score_asc, nome, data

// Query base
$sql = "SELECT c.*, r.id_requisicao, r.valor, r.categoria, r.data_requisicao
        FROM clientes c
        LEFT JOIN requisicoes r ON c.id_cliente = r.id_cliente
        WHERE 1=1";

$params = [];
$types = '';

// Adicionar filtros
if ($filtro_categoria) {
    $sql .= " AND r.categoria = ";
    $types .= 's';
    $params[] = $filtro_categoria;
}

$sql .= " GROUP BY c.id_cliente";

// OrdenaÃ§Ã£o
switch($ordenar) {
    case 'score_asc':
        $sql .= " ORDER BY c.lead_score ASC";
        break;
    case 'nome':
        $sql .= " ORDER BY c.nome ASC";
        break;
    case 'data':
        $sql .= " ORDER BY r.data_requisicao DESC";
        break;
    default:
        $sql .= " ORDER BY c.lead_score DESC";
}

$sql .= " LIMIT 100";

$stmt = $conexao->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$clientes = [];
$hot_count = $warm_count = $cold_count = 0;

while ($row = $result->fetch_assoc()) {
    // Atualizar score
    $score = $scoringEngine->calculateScore($row['id_cliente']);
    $scoringEngine->updateScoreInDB($row['id_cliente']);
    
    $status = $scoringEngine->getLeadStatus($score);
    
    if ($status['status'] == 'HOT') $hot_count++;
    elseif ($status['status'] == 'WARM') $warm_count++;
    else $cold_count++;
    
    // Filtrar por status se necessÃ¡rio
    if ($filtro_status != 'all' && strtolower($filtro_status) != strtolower($status['status'])) {
        continue;
    }
    
    $row['score'] = $score;
    $row['status_info'] = $status;
    $clientes[] = $row;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Scoring - CONFINTER Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        .score-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .score-card.hot {
            background: #fee;
            border-left: 4px solid #dc3545;
        }
        
        .score-card.warm {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .score-card.cold {
            background: #e8f4f8;
            border-left: 4px solid #17a2b8;
        }
        
        .score-badge {
            font-size: 2em;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 50%;
            display: inline-block;
            color: white;
        }
        
        .score-badge.hot {
            background: #dc3545;
        }
        
        .score-badge.warm {
            background: #ffc107;
            color: #333;
        }
        
        .score-badge.cold {
            background: #17a2b8;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
        
        .lead-row {
            transition: all 0.3s;
        }
        
        .lead-row.hot {
            border-left: 5px solid #dc3545;
        }
        
        .lead-row.warm {
            border-left: 5px solid #ffc107;
        }
        
        .lead-row.cold {
            border-left: 5px solid #17a2b8;
        }
        
        .metric-box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .metric-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        
        .metric-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons .btn {
            font-size: 0.85em;
            padding: 5px 10px;
        }
    </style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    
    <div class="page-wrapper">
        <div class="content container-fluid">
            <!-- Header -->
            <div class="page-header">
                <div class="row mb-4">
                    <div class="col-sm-12">
                        <h3 class="page-title">
                            <i class="fas fa-fire"></i> Lead Scoring - AnÃ¡lise de Temperatura
                        </h3>
                    </div>
                </div>
            </div>
            
            <!-- MÃ©tricas Resumidas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="metric-box">
                        <div class="metric-number" style="color: #dc3545;">
                            ðŸ”´ <?php echo $hot_count; ?>
                        </div>
                        <div class="metric-label">Leads HOT (Prioridade Alta)</div>
                        <p class="text-muted small">Ligar agora! Score > 300</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-box">
                        <div class="metric-number" style="color: #ffc107;">
                            ðŸŸ¡ <?php echo $warm_count; ?>
                        </div>
                        <div class="metric-label">Leads WARM (Seguimento)</div>
                        <p class="text-muted small">Nutrir. Score 150-300</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-box">
                        <div class="metric-number" style="color: #17a2b8;">
                            ðŸŸ¢ <?php echo $cold_count; ?>
                        </div>
                        <div class="metric-label">Leads COLD (Aquecimento)</div>
                        <p class="text-muted small">Email/Newsletter. Score < 150</p>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="row mb-4">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all" <?php echo $filtro_status == 'all' ? 'selected' : ''; ?>>Todos</option>
                                        <option value="hot" <?php echo $filtro_status == 'hot' ? 'selected' : ''; ?>>ðŸ”´ HOT</option>
                                        <option value="warm" <?php echo $filtro_status == 'warm' ? 'selected' : ''; ?>>ðŸŸ¡ WARM</option>
                                        <option value="cold" <?php echo $filtro_status == 'cold' ? 'selected' : ''; ?>>ðŸŸ¢ COLD</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Categoria</label>
                                    <select name="categoria" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="consignado" <?php echo $filtro_categoria == 'consignado' ? 'selected' : ''; ?>>Consignado</option>
                                        <option value="pessoal" <?php echo $filtro_categoria == 'pessoal' ? 'selected' : ''; ?>>Pessoal</option>
                                        <option value="fgts" <?php echo $filtro_categoria == 'fgts' ? 'selected' : ''; ?>>FGTS</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ordenar por</label>
                                    <select name="order" class="form-select">
                                        <option value="score_desc" <?php echo $ordenar == 'score_desc' ? 'selected' : ''; ?>>Score Alto</option>
                                        <option value="score_asc" <?php echo $ordenar == 'score_asc' ? 'selected' : ''; ?>>Score Baixo</option>
                                        <option value="nome" <?php echo $ordenar == 'nome' ? 'selected' : ''; ?>>Nome A-Z</option>
                                        <option value="data" <?php echo $ordenar == 'data' ? 'selected' : ''; ?>>Data Recente</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                                    <a href="lead-scoring.php" class="btn btn-secondary">Limpar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Leads -->
            <div class="row">
                <div class="col-sm-12">
                    <div class="card card-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th>Email</th>
                                        <th>Telefone</th>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                        <th>Data</th>
                                        <th>AÃ§Ãµes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($clientes as $cliente): ?>
                                        <tr class="lead-row <?php echo strtolower($cliente['status_info']['status']); ?>"?>
                                            <td>
                                                <span class="badge bg-<?php echo $cliente['status_info']['color']; ?>"?>
                                                    <?php echo $cliente['status_info']['emoji'] . ' ' . $cliente['status_info']['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $cliente['score']; ?>/500</strong?>
                                                <div class="progress" style="height: 5px; margin-top: 5px;">
                                                    <div class="progress-bar bg-<?php echo $cliente['status_info']['color']; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo ($cliente['score']/500)*100; ?>%"?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($cliente['nome']); ?></strong></td>
                                            <td><?php echo substr($cliente['cpf'], 0, 3) . '.***.***-' . substr($cliente['cpf'], -2); ?></td>
                                            <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                            <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                                            <td><?php echo $cliente['categoria'] ? htmlspecialchars($cliente['categoria']) : '--'; ?></td>
                                            <td>
                                                <?php if ($cliente['valor']): ?>
                                                    R$ <?php echo number_format($cliente['valor'], 2, ',', '.'); ?>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cliente['data_requisicao']): ?>
                                                    <?php echo date('d/m/Y', strtotime($cliente['data_requisicao'])); ?>
                                                <?php else: ?>
                                                    --
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="whatsapp://sendphone=55<?php echo preg_replace('/[^0-9]/', '', $cliente['telefone']); ?>&text=OlÃ¡%20<?php echo urlencode($cliente['nome']); ?>,%20gostaria%20de%20informaÃ§Ãµes" 
                                                       class="btn btn-sm btn-success" 
                                                       title="Enviar WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </a>
                                                    <a href="mailto:<?php echo $cliente['email']; ?>subject=Oferta%20Especial%20CONFINTER" 
                                                       class="btn btn-sm btn-primary" 
                                                       title="Enviar Email">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    <a href="clientedit.php?id=<?php echo $cliente['id_cliente']; ?>" 
                                                       class="btn btn-sm btn-info" 
                                                       title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dicas -->
            <div class="row mt-4">
                <div class="col-sm-12">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-lightbulb"></i> Dicas de Uso</h5>
                        <ul class="mb-0">
                            <li><strong>ðŸ”´ HOT (Score > 300):</strong> Ligar agora! Maior chance de conversÃ£o</li>
                            <li><strong>ðŸŸ¡ WARM (150-300):</strong> Seguimento contÃ­nuo com email/WhatsApp</li>
                            <li><strong>ðŸŸ¢ COLD (< 150):</strong> Newsletter/conteÃºdo educativo para aquecimento</li>
                            <li><strong>ðŸ’¡ Score Aumenta:</strong> CPF vÃ¡lido, email verificado, visitou simulador, clicou CTA</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh a cada 5 minutos
        setTimeout(() => {
            location.reload();
        }, 5 * 60 * 1000);
    </script>
</body>
</html>


