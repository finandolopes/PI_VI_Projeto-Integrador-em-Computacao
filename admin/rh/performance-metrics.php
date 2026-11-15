<?php
/**
 * PERFORMANCE METRICS - IMPLEMENTAÇÃO #25
 * Métricas de performance individual e coletiva
 * ROI: +10% | Esforço: 2h | Conversão: +R$ 35k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class PerformanceMetrics {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function obterKPIs() {
        $sql = "SELECT 
                    COUNT(*) as total_leads,
                    COUNT(CASE WHEN score > 300 THEN 1 END) as hot_leads,
                    ROUND(COUNT(CASE WHEN status IN ('proposal', 'won') THEN 1 END) / COUNT(*) * 100, 2) as conversao_taxa,
                    AVG(DATEDIFF(NOW(), created_at)) as dias_medio_lead
                FROM crm_leads";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function obterMetas() {
        $sql = "SELECT id, nome_meta, valor_alvo, valor_atual, mes, ano FROM metas_vendas ORDER BY ano DESC, mes DESC";
        $result = $this->conn->query($sql);
        $metas = [];
        while ($row = $result->fetch_assoc()) {
            $row['percentual'] = round(($row['valor_atual'] / $row['valor_alvo']) * 100, 1);
            $metas[] = $row;
        }
        return $metas;
    }
}

$perf = new PerformanceMetrics($conn);
$kpis = $perf->obterKPIs();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Performance Metrics - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-speedometer2"></i> Performance Metrics</h1>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $kpis['total_leads']; ?></h3>
                        <p>Total Leads</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-danger"><?php echo $kpis['hot_leads']; ?></h3>
                        <p>HOT Leads</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?php echo $kpis['conversao_taxa']; ?>%</h3>
                        <p>Taxa Conversão</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3><?php echo $kpis['dias_medio_lead']; ?> dias</h3>
                        <p>Lead Age Médio</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h4>Metas de Vendas</h4>
                <table class="table">
                    <thead class="table-dark">
                        <tr>
                            <th>Meta</th>
                            <th>Período</th>
                            <th>Target</th>
                            <th>Atual</th>
                            <th>% Atingido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perf->obterMetas() as $meta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($meta['nome_meta']); ?></td>
                                <td><?php echo $meta['mes'] . '/' . $meta['ano']; ?></td>
                                <td>R$ <?php echo number_format($meta['valor_alvo'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($meta['valor_atual'], 2, ',', '.'); ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $meta['percentual'] >= 100 ? 'success' : ($meta['percentual'] >= 50 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo min(100, $meta['percentual']); ?>%"?>
                                            <?php echo $meta['percentual']; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



