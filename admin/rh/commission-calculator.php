<?php
/**
 * COMMISSION CALCULATOR - IMPLEMENTAÇÃO #26
 * Cálculo automático de comissões
 * ROI: +6% | Esforço: 2h | Conversão: +R$ 20k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

class CommissionCalculator {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function calcularComissao($user_id, $mes, $ano) {
        // Base: 5% de cada venda
        $sql = "SELECT 
                    SUM(valor_estimado) as total_vendido,
                    COUNT(*) as total_vendas
                FROM crm_leads
                WHERE responsavel_id = $user_id 
                AND status = 'won'
                AND MONTH(created_at) = $mes 
                AND YEAR(created_at) = $ano";
        
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        
        $comissao_base = ($row['total_vendido'] ?? 0) * 0.05;
        $bonus = $row['total_vendas'] > 10 ? ($row['total_vendas'] - 10) * 500 : 0;
        
        return ['base' => $comissao_base, 'bonus' => $bonus, 'total' => $comissao_base + $bonus];
    }
    
    public function obterComissoesPendentes() {
        $sql = "SELECT c.id, u.nome, c.valor_base, c.bonus, c.total, c.mes, c.ano, c.status 
                FROM comissoes c 
                JOIN usuarios u ON c.user_id = u.id 
                WHERE c.status = 'pendente' 
                ORDER BY c.data_calculo DESC";
        
        $result = $this->conn->query($sql);
        $comissoes = [];
        while ($row = $result->fetch_assoc()) {
            $comissoes[] = $row;
        }
        return $comissoes;
    }
}

$commission = new CommissionCalculator($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Commission Calculator - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-cash-coin"></i> Commission Calculator</h1>
        
        <h4>Comissões Pendentes</h4>
        <table class="table">
            <thead class="table-dark">
                <tr>
                    <th>Operador</th>
                    <th>Base</th>
                    <th>Bônus</th>
                    <th>Total</th>
                    <th>Período</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commission->obterComissoesPendentes() as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['nome']); ?></td?>
                        <td>R$ <?php echo number_format($c['valor_base'], 2, ',', '.'); ?></td?>
                        <td>R$ <?php echo number_format($c['bonus'], 2, ',', '.'); ?></td?>
                        <td><strong>R$ <?php echo number_format($c['total'], 2, ',', '.'); ?></strong></td?>
                        <td><?php echo $c['mes'] . '/' . $c['ano']; ?></td?>
                        <td><span class="badge bg-warning"><?php echo $c['status']; ?></span></td?>
                        <td>
                            <button class="btn btn-sm btn-success">Confirmar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



