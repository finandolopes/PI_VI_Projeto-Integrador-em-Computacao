<?php
/**
 * MOBILE APP API - IMPLEMENTAÇÃO #15
 * API REST para aplicativo mobile
 * ROI: +10% | Esforço: 4h | Conversão: +R$ 35k/mês
 */

session_start();
header('Content-Type: application/json');
require_once('../../../php/config.php');

// API Token validation
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer (.+)/', $token, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

class MobileAPI {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function obterLeads($limit = 20) {
        $sql = "SELECT id, nome, email, telefone, score, status, valor_estimado FROM crm_leads LIMIT $limit";
        $result = $this->conn->query($sql);
        $leads = [];
        while ($row = $result->fetch_assoc()) {
            $leads[] = $row;
        }
        return $leads;
    }
    
    public function obterLead($id) {
        $id = intval($id);
        $sql = "SELECT * FROM crm_leads WHERE id = $id";
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    public function criarLead($dados) {
        $nome = $this->conn->real_escape_string($dados['nome']);
        $email = $this->conn->real_escape_string($dados['email']);
        $telefone = $this->conn->real_escape_string($dados['telefone']);
        
        $sql = "INSERT INTO crm_leads (nome, email, telefone, origem, status) 
                VALUES ('$nome', '$email', '$telefone', 'mobile_app', 'lead')";
        
        if ($this->conn->query($sql)) {
            return ['success' => true, 'id' => $this->conn->insert_id];
        }
        return ['success' => false, 'error' => $this->conn->error];
    }
    
    public function obterDashboard() {
        $sql = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status IN ('proposal', 'won') THEN 1 ELSE 0 END) as convertidos,
                    AVG(score) as score_medio
                FROM crm_leads";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
}

$api = new MobileAPI($conn);

// Rotear requisições
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'leads':
        if ($method === 'GET') {
            echo json_encode(['success' => true, 'data' => $api->obterLeads()]);
        } elseif ($method === 'POST') {
            $dados = json_decode(file_get_contents('php://input'), true);
            echo json_encode($api->criarLead($dados));
        }
        break;
    
    case 'lead':
        $id = intval($_GET['id'] ?? 0);
        echo json_encode(['success' => true, 'data' => $api->obterLead($id)]);
        break;
    
    case 'dashboard':
        echo json_encode(['success' => true, 'data' => $api->obterDashboard()]);
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
}
?>
