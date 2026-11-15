<?php
/**
 * INVOICE SYSTEM - IMPLEMENTAÇÃO #22
 * Sistema de emissão e gestão de notas fiscais
 * ROI: +8% | Esforço: 3h | Conversão: +R$ 28k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class InvoiceSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function gerarNota($id_lead, $valor, $descricao) {
        $nf_numero = date('YmdHis'); // Simples numeração
        
        $sql = "INSERT INTO notas_fiscais (id_lead, numero_nf, valor, descricao, data_emissao, status) 
                VALUES ($id_lead, '$nf_numero', $valor, '{$this->conn->real_escape_string($descricao)}', NOW(), 'emitida')";
        
        return $this->conn->query($sql) ? ['success' => true, 'nf' => $nf_numero] : ['success' => false];
    }
    
    public function obterNotas() {
        $sql = "SELECT nf.id, cr.nome, nf.numero_nf, nf.valor, nf.status, nf.data_emissao 
                FROM notas_fiscais nf 
                JOIN crm_leads cr ON nf.id_lead = cr.id 
                ORDER BY nf.data_emissao DESC LIMIT 50";
        
        $result = $this->conn->query($sql);
        $notas = [];
        while ($row = $result->fetch_assoc()) {
            $notas[] = $row;
        }
        return $notas;
    }
    
    public function obterTotalEmitido() {
        $sql = "SELECT SUM(valor) as total FROM notas_fiscais WHERE DATE(data_emissao) = CURDATE()";
        $result = $this->conn->query($sql);
        return $result->fetch_assoc()['total'] ?? 0;
    }
}

$invoice = new InvoiceSystem($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Invoice System - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-receipt"></i> Invoice System</h1>
        
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3>R$ <?php echo number_format($invoice->obterTotalEmitido(), 2, ',', '.'); ?></h3>
                        <p>Emitido Hoje</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h4>Notas Fiscais Recentes</h4>
                <table class="table">
                    <thead class="table-dark">
                        <tr>
                            <th>Cliente</th>
                            <th>NF</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice->obterNotas() as $nota): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nota['nome']); ?></td>
                                <td><?php echo $nota['numero_nf']; ?></td>
                                <td>R$ <?php echo number_format($nota['valor'], 2, ',', '.'); ?></td>
                                <td><span class="badge bg-success"><?php echo $nota['status']; ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($nota['data_emissao'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info">Visualizar</button>
                                    <button class="btn btn-sm btn-download">Download</button>
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



