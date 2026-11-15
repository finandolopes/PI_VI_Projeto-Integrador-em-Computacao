<?php
/**
 * Kanban Board Backend API
 * Fase 2: Lead Management & CRM
 * 
 * Endpoints para gerenciar board kanban:
 * - GET /api/kanban.php?action=get_board - Obtém todas as colunas
 * - POST /api/kanban.php - Move lead entre colunas
 * 
 * @author CONFINTER
 * @version 1.0
 */

class KanbanBoard {
    
    private $db;
    
    // Mapeamento de status para colunas do kanban
    private $statusColumns = [
        'novo' => ['title' => 'Novo', 'color' => '#3498db', 'order' => 1],
        'contatado' => ['title' => 'Contatado', 'color' => '#f39c12', 'order' => 2],
        'engajado' => ['title' => 'Engajado', 'color' => '#9b59b6', 'order' => 3],
        'proposta' => ['title' => 'Proposta', 'color' => '#e74c3c', 'order' => 4],
        'negociando' => ['title' => 'Negociando', 'color' => '#1abc9c', 'order' => 5],
        'won' => ['title' => 'Convertido', 'color' => '#27ae60', 'order' => 6]
    ];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Obtém board completo com todos os leads organizados por status
     */
    public function getBoard() {
        $board = [];
        
        foreach ($this->statusColumns as $status => $column) {
            $leads = $this->getLeadsByStatus($status);
            
            $board[] = [
                'id' => $status,
                'title' => $column['title'],
                'color' => $column['color'],
                'order' => $column['order'],
                'leads' => $leads,
                'count' => count($leads)
            ];
        }
        
        usort($board, fn($a, $b) => $a['order'] <=> $b['order']);
        
        return $board;
    }
    
    /**
     * Obtém leads de um status específico
     */
    private function getLeadsByStatus($status) {
        $sql = "SELECT 
                    l.id,
                    l.nome,
                    l.email,
                    l.telefone,
                    l.empresa,
                    l.segmento,
                    l.lead_score,
                    l.assigned_to,
                    l.created_at,
                    u.name as assigned_to_name,
                    u.avatar as assigned_to_avatar
                FROM leads l
                LEFT JOIN users u ON u.id = l.assigned_to
                WHERE l.status = :status 
                    AND l.deleted_at IS NULL
                ORDER BY l.lead_score DESC, l.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);
        
        $leads = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lead) {
            $leads[] = [
                'id' => (int)$lead['id'],
                'name' => $lead['nome'],
                'email' => $lead['email'],
                'phone' => $lead['telefone'],
                'company' => $lead['empresa'],
                'segment' => $lead['segmento'],
                'score' => (int)$lead['lead_score'],
                'score_label' => $this->getScoreLabel($lead['lead_score']),
                'assigned_to' => $lead['assigned_to'],
                'assigned_to_name' => $lead['assigned_to_name'],
                'assigned_to_avatar' => $lead['assigned_to_avatar'],
                'created_at' => $lead['created_at']
            ];
        }
        
        return $leads;
    }
    
    /**
     * Move lead entre colunas (status)
     */
    public function moveCard($leadId, $fromStatus, $toStatus) {
        try {
            // Validar status
            if (!isset($this->statusColumns[$fromStatus]) || !isset($this->statusColumns[$toStatus])) {
                throw new Exception("Status inválido");
            }
            
            $this->db->beginTransaction();
            
            // Usar workflow de transição
            require_once __DIR__ . '/../admin/lead-status-workflow.php';
            $workflow = new LeadStatusWorkflow($this->db, $leadId);
            
            $result = $workflow->transitionTo($toStatus, 'Moved via kanban board');
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Lead movido com sucesso",
                'lead_id' => $leadId,
                'new_status' => $toStatus
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém label de score
     */
    private function getScoreLabel($score) {
        if ($score >= 80) return 'Hot';
        if ($score >= 60) return 'Warm';
        if ($score >= 40) return 'Cool';
        return 'Cold';
    }
    
    /**
     * Obtém leads em atraso (sem atualização há mais de X dias)
     */
    public function getOverdueLeads($daysThreshold = 3) {
        $sql = "SELECT 
                    l.id,
                    l.nome,
                    l.status,
                    DATEDIFF(NOW(), l.updated_at) as days_since_update,
                    u.name as assigned_to_name
                FROM leads l
                LEFT JOIN users u ON u.id = l.assigned_to
                WHERE DATEDIFF(NOW(), l.updated_at) > :days
                    AND l.status NOT IN ('won', 'arquivado', 'rejeitado')
                    AND l.deleted_at IS NULL
                ORDER BY l.updated_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $daysThreshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém velocidade de progresso (WIP - Work in Progress)
     */
    public function getVelocity() {
        $sql = "SELECT 
                    DATEDIFF(
                        AVG(l.status_won_at),
                        AVG(l.created_at)
                    ) as avg_days_to_win,
                    COUNT(*) as total_conversions
                FROM leads l
                WHERE l.status = 'won'
                    AND l.deleted_at IS NULL
                    AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// API Routes
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    $db = new PDO($dsn, $db_user, $db_pass);
    
    $kanban = new KanbanBoard($db);
    
    // GET - Obter board
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'get_board') {
            $board = $kanban->getBoard();
            echo json_encode([
                'success' => true,
                'board' => $board,
                'generated_at' => date('Y-m-d H:i:s')
            ]);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'overdue') {
            $overdue = $kanban->getOverdueLeads($_GET['days'] ?? 3);
            echo json_encode([
                'success' => true,
                'overdue_leads' => $overdue
            ]);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'velocity') {
            $velocity = $kanban->getVelocity();
            echo json_encode([
                'success' => true,
                'velocity' => $velocity
            ]);
        }
    }
    
    // POST - Mover card
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'move_card') {
            $leadId = intval($input['lead_id'] ?? 0);
            $fromStatus = $input['from_status'] ?? '';
            $toStatus = $input['to_status'] ?? '';
            
            $result = $kanban->moveCard($leadId, $fromStatus, $toStatus);
            echo json_encode($result);
        }
    }
    
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
