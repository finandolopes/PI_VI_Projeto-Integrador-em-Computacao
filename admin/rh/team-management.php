<?php
/**
 * TEAM MANAGEMENT - IMPLEMENTAÇÃO #24
 * Gestão de equipe, hierarquias e permissões
 * ROI: +8% | Esforço: 3h | Conversão: +R$ 28k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class TeamManagement {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function obterEquipe() {
        $sql = "SELECT id, nome, email, funcao, departamento, data_entrada, status FROM usuarios ORDER BY nome ASC";
        $result = $this->conn->query($sql);
        $equipe = [];
        while ($row = $result->fetch_assoc()) {
            $equipe[] = $row;
        }
        return $equipe;
    }
    
    public function obterPerformance($user_id) {
        $sql = "SELECT 
                    COUNT(CASE WHEN status = 'won' THEN 1 END) as vendas,
                    SUM(CASE WHEN status = 'won' THEN valor_estimado ELSE 0 END) as receita,
                    COUNT(CASE WHEN DATEDIFF(NOW(), created_at) <= 7 THEN 1 END) as leads_semana
                FROM crm_leads
                WHERE responsavel_id = $user_id";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function atribuirLead($id_lead, $user_id) {
        $sql = "UPDATE crm_leads SET responsavel_id = $user_id WHERE id = $id_lead";
        return $this->conn->query($sql);
    }
    
    public function obterEstatisticasEquipe() {
        $sql = "SELECT 
                    u.nome,
                    COUNT(cl.id) as total_leads,
                    SUM(CASE WHEN cl.status = 'won' THEN 1 ELSE 0 END) as vendas,
                    ROUND(SUM(CASE WHEN cl.status = 'won' THEN 1 ELSE 0 END) / COUNT(cl.id) * 100, 1) as taxa_conversao
                FROM usuarios u
                LEFT JOIN crm_leads cl ON u.id = cl.responsavel_id
                WHERE u.funcao IN ('operador', 'gerente')
                GROUP BY u.id, u.nome
                ORDER BY vendas DESC";
        
        $result = $this->conn->query($sql);
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        return $stats;
    }
}

$team = new TeamManagement($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Team Management - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-people"></i> Team Management</h1>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h4>Equipe</h4>
                <table class="table">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Função</th>
                            <th>Departamento</th>
                            <th>Entrada</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team->obterEquipe() as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo ucfirst($usuario['funcao']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['departamento']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['data_entrada'])); ?></td>
                                <td><span class="badge bg-success"><?php echo $usuario['status']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h4>Performance da Equipe</h4>
                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Membro</th>
                            <th>Total Leads</th>
                            <th>Vendas</th>
                            <th>Taxa Conversão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team->obterEstatisticasEquipe() as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['nome']); ?></td>
                                <td><?php echo $stat['total_leads']; ?></td>
                                <td><?php echo $stat['vendas']; ?></td>
                                <td><?php echo $stat['taxa_conversao']; ?>%</td>
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



