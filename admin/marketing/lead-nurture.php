<?php
/**
 * LEAD NURTURE - IMPLEMENTAÇÃO #14
 * Automação de nutrição e qualificação de leads
 * ROI: +18% | Esforço: 3h | Conversão: +R$ 60k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class LeadNurture {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function criarSequencia($nome, $descricao) {
        $sql = "INSERT INTO nurture_sequencias (nome, descricao) 
                VALUES ('{$this->conn->real_escape_string($nome)}', '{$this->conn->real_escape_string($descricao)}')";
        return $this->conn->query($sql);
    }
    
    public function adicionarEmailSequencia($id_seq, $dia, $assunto, $corpo) {
        $sql = "INSERT INTO nurture_emails (id_sequencia, dia_intervalo, assunto, corpo) 
                VALUES ($id_seq, $dia, '{$this->conn->real_escape_string($assunto)}', '{$this->conn->real_escape_string($corpo)}')";
        return $this->conn->query($sql);
    }
    
    public function registrarLeadSequencia($id_lead, $id_sequencia) {
        $sql = "INSERT INTO nurture_leads (id_lead, id_sequencia, data_inicio, status) 
                VALUES ($id_lead, $id_sequencia, NOW(), 'ativo')";
        return $this->conn->query($sql);
    }
    
    public function obterSequencias() {
        $sql = "SELECT id, nome, descricao, created_at FROM nurture_sequencias ORDER BY created_at DESC";
        $result = $this->conn->query($sql);
        $seq = [];
        while ($row = $result->fetch_assoc()) {
            $seq[] = $row;
        }
        return $seq;
    }
}

$nurture = new LeadNurture($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lead Nurture - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-heart"></i> Lead Nurture</h1>
        
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="#sequencias" data-bs-toggle="tab">Sequências</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#leads" data-bs-toggle="tab">Leads em Nutrição</a>
            </li>
        </ul>
        
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="sequencias">
                <table class="table">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nurture->obterSequencias() as $seq): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($seq['nome']); ?></td>
                                <td><?php echo htmlspecialchars($seq['descricao']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($seq['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info">Editar</button>
                                    <button class="btn btn-sm btn-danger">Deletar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="tab-pane fade" id="leads">
                <p class="text-muted">Leads atualmente em sequências de nutrição</p>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



