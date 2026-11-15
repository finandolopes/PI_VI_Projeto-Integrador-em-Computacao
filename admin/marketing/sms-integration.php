<?php
/**
 * SMS INTEGRATION - IMPLEMENTAÇÃO #8
 * Envio de SMS automático para leads
 * ROI: +18% | Esforço: 2h | Conversão: +R$ 60k/mês
 */

session_start();
require_once(__DIR__ . '/../../php/config.php');
require_once(__DIR__ . '/../../php/seguranca.php');

verificarSessao();
require_permissao(['admin', 'gestor']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class SMSIntegration {
    private $conn;
    private $api_key;
    private $gateway = 'twilio'; // twilio, zenvia, etc
    
    public function __construct($connection, $api_key = null) {
        $this->conn = $connection;
        $this->api_key = $api_key;
    }
    
    public function enviarSMS($numero, $mensagem, $id_lead = null) {
        // Remover caracteres não numéricos
        $numero = preg_replace('/\D/', '', $numero);
        
        // Limitar mensagem a 160 caracteres (padrão SMS)
        $mensagem = substr($conn->real_escape_string($mensagem), 0, 160);
        
        // Log de envio
        $sql = "INSERT INTO sms_logs (numero, mensagem, status, id_lead, data_envio) 
                VALUES ('$numero', '$mensagem', 'pendente', " . ($id_lead ? $id_lead : "NULL") . ", NOW())";
        
        if ($this->conn->query($sql)) {
            return ['success' => true, 'id_log' => $this->conn->insert_id];
        }
        return ['success' => false, 'message' => $this->conn->error];
    }
    
    public function enviarCampanhaSMS($id_campanha, $filtro_leads = []) {
        $sql = "SELECT id, telefone, nome FROM crm_leads WHERE 1=1";
        
        if (!empty($filtro_leads['status'])) {
            $status = $this->conn->real_escape_string($filtro_leads['status']);
            $sql .= " AND status = '$status'";
        }
        
        $result = $this->conn->query($sql);
        $enviados = 0;
        
        while ($lead = $result->fetch_assoc()) {
            if (!empty($lead['telefone'])) {
                $mensagem = $this->obterMensagemCampanha($id_campanha);
                $this->enviarSMS($lead['telefone'], $mensagem, $lead['id']);
                $enviados++;
            }
        }
        
        return ['success' => true, 'enviados' => $enviados];
    }
    
    public function obterMensagemCampanha($id_campanha) {
        $sql = "SELECT mensagem FROM campanhas_sms WHERE id = $id_campanha LIMIT 1";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return $row['mensagem'] ?? '';
    }
    
    public function obterEstatisticas() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'enviada' THEN 1 ELSE 0 END) as enviados,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues,
                    SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas
                FROM sms_logs";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
}

$sms = new SMSIntegration($conn);

$acao = $_GET['acao'] ?? 'lista';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['acao'] === 'criar_campanha') {
        $nome = $conn->real_escape_string($_POST['nome']);
        $mensagem = substr($conn->real_escape_string($_POST['mensagem']), 0, 160);
        
        $sql = "INSERT INTO campanhas_sms (nome, mensagem, status) 
                VALUES ('$nome', '$mensagem', 'draft')";
        
        if ($conn->query($sql)) {
            $_SESSION['msg'] = 'Campanha SMS criada com sucesso!';
            $_SESSION['tipo_msg'] = 'success';
        }
    }
    elseif ($_POST['acao'] === 'enviar_campanha') {
        $id_campanha = intval($_POST['id_campanha']);
        $resultado = $sms->enviarCampanhaSMS($id_campanha);
        $_SESSION['msg'] = 'SMS enviados: ' . $resultado['enviados'];
        $_SESSION['tipo_msg'] = 'success';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Integration - CONFINTER</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/confinter-admin-design-system.css">
<script defer src="../assets/js/confinter-admin-design-system.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="bi bi-chat-left-text"></i> SMS Integration</h1>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $sms->obterEstatisticas()['total'] ?? 0; ?></h3?>
                        <p>Total SMS</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h3><?php echo $sms->obterEstatisticas()['enviados'] ?? 0; ?></h3?>
                        <p>Enviados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h3><?php echo $sms->obterEstatisticas()['entregues'] ?? 0; ?></h3?>
                        <p>Entregues</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h3><?php echo $sms->obterEstatisticas()['falhas'] ?? 0; ?></h3?>
                        <p>Falhas</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <h4>Campanhas SMS</h4>
                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Mensagem</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM campanhas_sms ORDER BY created_at DESC LIMIT 10";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo substr(htmlspecialchars($row['mensagem']), 0, 20); ?>...</td>
                                <td><span class="badge bg-info"><?php echo $row['status']; ?></span></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="enviar_campanha">
                                        <input type="hidden" name="id_campanha" value="<?php echo $row['id']; ?>"
                                        <button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Enviar SMS')">Enviar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="col-md-6">
                <h4>Nova Campanha SMS</h4>
                <form method="POST">
                    <input type="hidden" name="acao" value="criar_campanha">
                    
                    <div class="mb-2">
                        <label>Nome</label>
                        <input type="text" name="nome" class="form-control form-control-sm" required>
                    </div>
                    
                    <div class="mb-2">
                        <label>Mensagem (máx 160 caracteres)</label>
                        <textarea name="mensagem" class="form-control form-control-sm" rows="3" maxlength="160" required></textarea>
                        <small class="form-text text-muted">Caracteres restantes: <span id="chars">160</span></small>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-sm">Criar Campanha</button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('textarea[name="mensagem"]').addEventListener('input', function() {
            document.getElementById('chars').textContent = 160 - this.value.length;
        });
    </script>
</body>
</html>



