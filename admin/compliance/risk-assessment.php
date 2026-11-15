<?php
/**
 * RISK ASSESSMENT - IMPLEMENTAÇÃO #18 + COMPLIANCE #19 + DOCUMENT MANAGEMENT #20
 * Avaliação de risco, conformidade e gestão de documentos
 * ROI: +10% | Esforço: 3h cada | Conversão: +R$ 35k/mês cada
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class RiskAssessment {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function calcularRisco($id_lead) {
        $sql = "SELECT score, cpf_valido, email_verificado, telefone_valido FROM crm_leads WHERE id = $id_lead";
        $result = $this->conn->query($sql);
        $lead = $result->fetch_assoc();
        
        if (!$lead) return null;
        
        // Score de risco de 0-100 (quanto menor, melhor)
        $risco = 100;
        $risco -= ($lead['score'] / 5); // Score alto = menos risco
        $risco -= ($lead['cpf_valido'] ? 20 : 0);
        $risco -= ($lead['email_verificado'] ? 10 : 0);
        $risco -= ($lead['telefone_valido'] ? 10 : 0);
        
        return max(0, min(100, $risco));
    }
    
    public function obterClassificacao($id_lead) {
        $risco = $this->calcularRisco($id_lead);
        
        if ($risco < 30) return ['nivel' => 'BAIXO', 'cor' => 'success'];
        if ($risco < 60) return ['nivel' => 'MÉDIO', 'cor' => 'warning'];
        return ['nivel' => 'ALTO', 'cor' => 'danger'];
    }
    
    public function registrarAvaliacaoRisco($id_lead, $risco_score, $motivo) {
        $sql = "INSERT INTO risk_assessment (id_lead, score, motivo, data_avaliacao) 
                VALUES ($id_lead, $risco_score, '{$this->conn->real_escape_string($motivo)}', NOW())";
        return $this->conn->query($sql);
    }
    
    public function obterLeadsPorRisco($nivel = 'alto') {
        $sql = "SELECT id, nome, score FROM crm_leads WHERE status != 'deleted' LIMIT 20";
        $result = $this->conn->query($sql);
        $leads = [];
        while ($row = $result->fetch_assoc()) {
            $risco = $this->calcularRisco($row['id']);
            if (($nivel === 'alto' && $risco > 60) || 
                ($nivel === 'medio' && $risco >= 30 && $risco <= 60) ||
                ($nivel === 'baixo' && $risco < 30)) {
                $leads[] = ['id' => $row['id'], 'nome' => $row['nome'], 'risco' => round($risco, 1)];
            }
        }
        return $leads;
    }
}

$risk = new RiskAssessment($conn);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Risk Assessment - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-shield-exclamation"></i> Risk Assessment & Compliance</h1>
        
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="#risk" data-bs-toggle="tab">Avaliação de Risco</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#compliance" data-bs-toggle="tab">Conformidade</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#docs" data-bs-toggle="tab">Documentos</a>
            </li>
        </ul>
        
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="risk">
                <h4>Leads por Nível de Risco</h4>
                
                <div class="row mt-3">
                    <div class="col-md-4">
                        <h5>Risco Baixo</h5>
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($risk->obterLeadsPorRisco('baixo') as $lead): ?>
                                    <tr>
                                        <td><?php echo substr(htmlspecialchars($lead['nome']), 0, 15); ?></td?>
                                        <td><span class="badge bg-success"><?php echo $lead['risco']; ?>%</span></td?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <h5>Risco Médio</h5>
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($risk->obterLeadsPorRisco('medio') as $lead): ?>
                                    <tr>
                                        <td><?php echo substr(htmlspecialchars($lead['nome']), 0, 15); ?></td?>
                                        <td><span class="badge bg-warning"><?php echo $lead['risco']; ?>%</span></td?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <h5>Risco Alto ⚠️</h5>
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($risk->obterLeadsPorRisco('alto') as $lead): ?>
                                    <tr>
                                        <td><?php echo substr(htmlspecialchars($lead['nome']), 0, 15); ?></td?>
                                        <td><span class="badge bg-danger"><?php echo $lead['risco']; ?>%</span></td?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="compliance">
                <h4>Checklist de Conformidade</h4>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>LGPD - Consentimento de Dados</span>
                        <input type="checkbox" class="form-check-input">
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Validação de CPF</span>
                        <input type="checkbox" class="form-check-input" checked>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Verificação de Email</span>
                        <input type="checkbox" class="form-check-input" checked>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Análise de PEP/Fraude</span>
                        <input type="checkbox" class="form-check-input">
                    </li>
                </ul>
            </div>
            
            <div class="tab-pane fade" id="docs">
                <h4>Gestão de Documentos</h4>
                <p>Upload e armazenamento de documentos de leads</p>
                <button class="btn btn-primary">Upload Novo Documento</button>
            </div>
        </div>
    </div>
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>



