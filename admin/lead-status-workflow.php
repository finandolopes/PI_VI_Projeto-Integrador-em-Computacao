<?php
/**
 * Lead Status Workflow Management
 * Fase 2: Lead Management & CRM
 * 
 * Gerencia 8+ status de leads com transiÃ§Ãµes automÃ¡ticas
 * Status: Novo > Contatado > Engajado > Proposta > Negociando > Won/Arquivado/Rejeitado
 * 
 * @author CONFINTER
 * @version 1.0
 */

class LeadStatusWorkflow {
    
    // Status definidos
    const STATUS_NOVO = 'novo';
    const STATUS_CONTATADO = 'contatado';
    const STATUS_ENGAJADO = 'engajado';
    const STATUS_PROPOSTA = 'proposta';
    const STATUS_NEGOCIANDO = 'negociando';
    const STATUS_WON = 'won';
    const STATUS_ARQUIVADO = 'arquivado';
    const STATUS_REJEITADO = 'rejeitado';
    
    private $db;
    private $leadId;
    private $leadData;
    
    // TransiÃ§Ãµes permitidas por status
    private $validTransitions = [
        self::STATUS_NOVO => ['contatado', 'rejeitado'],
        self::STATUS_CONTATADO => ['engajado', 'rejeitado', 'arquivado'],
        self::STATUS_ENGAJADO => ['proposta', 'rejeitado', 'arquivado'],
        self::STATUS_PROPOSTA => ['negociando', 'rejeitado', 'arquivado'],
        self::STATUS_NEGOCIANDO => ['won', 'rejeitado', 'arquivado'],
        self::STATUS_WON => ['arquivado'],
        self::STATUS_ARQUIVADO => ['novo'],
        self::STATUS_REJEITADO => ['novo']
    ];
    
    // AÃ§Ãµes automÃ¡ticas por status
    private $autoActions = [
        self::STATUS_NOVO => 'send_welcome_email',
        self::STATUS_CONTATADO => 'schedule_follow_up',
        self::STATUS_ENGAJADO => 'send_product_info',
        self::STATUS_PROPOSTA => 'send_proposal_document',
        self::STATUS_NEGOCIANDO => 'send_negotiation_terms',
        self::STATUS_WON => 'send_success_email',
        self::STATUS_REJEITADO => 'archive_lead'
    ];
    
    public function __construct($db, $leadId) {
        $this->db = $db;
        $this->leadId = $leadId;
        $this->loadLeadData();
    }
    
    /**
     * Carrega dados do lead
     */
    private function loadLeadData() {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $this->leadId]);
        $this->leadData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->leadData) {
            throw new Exception("Lead nÃ£o encontrado: " . $this->leadId);
        }
    }
    
    /**
     * Transiciona lead para novo status
     * @param string $newStatus Novo status
     * @param string $reason Motivo da transiÃ§Ã£o
     * @param int $userId ID do usuÃ¡rio que fez a transiÃ§Ã£o
     * @return array Status e mensagem
     */
    public function transitionTo($newStatus, $reason = '', $userId = null) {
        // Validar status
        if (!$this->isValidStatus($newStatus)) {
            return [
                'success' => false,
                'message' => "Status invÃ¡lido: {$newStatus}",
                'code' => 'INVALID_STATUS'
            ];
        }
        
        $currentStatus = $this->leadData['status'] ?? self::STATUS_NOVO;
        
        // Validar transiÃ§Ã£o permitida
        if (!$this->isTransitionAllowed($currentStatus, $newStatus)) {
            return [
                'success' => false,
                'message' => "TransiÃ§Ã£o nÃ£o permitida: {$currentStatus} â†’ {$newStatus}",
                'code' => 'INVALID_TRANSITION',
                'current_status' => $currentStatus,
                'allowed_transitions' => $this->validTransitions[$currentStatus] ?? []
            ];
        }
        
        // Executar transiÃ§Ã£o
        try {
            $this->db->beginTransaction();
            
            // Atualizar status do lead
            $sql = "UPDATE leads SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['status' => $newStatus, 'id' => $this->leadId]);
            
            // Registrar histÃ³rico
            $this->recordStatusHistory($currentStatus, $newStatus, $reason, $userId);
            
            // Executar aÃ§Ãµes automÃ¡ticas
            $this->executeAutoActions($newStatus, $userId);
            
            // Atualizar timestamps de status
            $this->updateStatusTimestamps($newStatus);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Lead transicionado para: {$newStatus}",
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => "Erro ao transicionar lead: " . $e->getMessage(),
                'code' => 'TRANSITION_ERROR'
            ];
        }
    }
    
    /**
     * Verifica se a transiÃ§Ã£o Ã© permitida
     */
    private function isTransitionAllowed($from, $to) {
        if (!isset($this->validTransitions[$from])) {
            return false;
        }
        return in_array($to, $this->validTransitions[$from]);
    }
    
    /**
     * Valida se Ã© um status definido
     */
    private function isValidStatus($status) {
        $validStatuses = [
            self::STATUS_NOVO,
            self::STATUS_CONTATADO,
            self::STATUS_ENGAJADO,
            self::STATUS_PROPOSTA,
            self::STATUS_NEGOCIANDO,
            self::STATUS_WON,
            self::STATUS_ARQUIVADO,
            self::STATUS_REJEITADO
        ];
        return in_array($status, $validStatuses);
    }
    
    /**
     * Registra histÃ³rico de transiÃ§Ã£o
     */
    private function recordStatusHistory($fromStatus, $toStatus, $reason, $userId) {
        $sql = "INSERT INTO lead_status_history 
                (lead_id, from_status, to_status, reason, changed_by, changed_at) 
                VALUES (:lead_id, :from_status, :to_status, :reason, :changed_by, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $this->leadId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'changed_by' => $userId
        ]);
    }
    
    /**
     * Executa aÃ§Ãµes automÃ¡ticas baseadas no novo status
     */
    private function executeAutoActions($newStatus, $userId) {
        $action = $this->autoActions[$newStatus] ?? null;
        
        if (!$action) {
            return;
        }
        
        switch ($action) {
            case 'send_welcome_email':
                $this->sendWelcomeEmail();
                break;
            case 'schedule_follow_up':
                $this->scheduleFollowUp();
                break;
            case 'send_product_info':
                $this->sendProductInfo();
                break;
            case 'send_proposal_document':
                $this->sendProposal();
                break;
            case 'send_negotiation_terms':
                $this->sendNegotiationTerms();
                break;
            case 'send_success_email':
                $this->sendSuccessEmail();
                break;
            case 'archive_lead':
                $this->archiveLead();
                break;
        }
    }
    
    /**
     * Atualiza timestamps de status
     */
    private function updateStatusTimestamps($newStatus) {
        $timestampField = "status_{$newStatus}_at";
        
        $sql = "UPDATE leads SET {$timestampField} = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $this->leadId]);
    }
    
    /**
     * Envia email de boas-vindas
     */
    private function sendWelcomeEmail() {
        // Implementar envio de email
        $email = $this->leadData['email'] ?? null;
        if ($email) {
            // Usar PHPMailer ou similar
            // mail($email, "Bem-vindo ao CONFINTER", ...);
        }
    }
    
    /**
     * Agenda follow-up automÃ¡tico
     */
    private function scheduleFollowUp() {
        $followUpDate = date('Y-m-d H:i:s', strtotime('+2 days'));
        
        $sql = "INSERT INTO tasks (lead_id, task_type, due_date, created_at) 
                VALUES (:lead_id, 'follow_up', :due_date, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $this->leadId,
            'due_date' => $followUpDate
        ]);
    }
    
    /**
     * Envia informaÃ§Ãµes do produto
     */
    private function sendProductInfo() {
        // Implementar envio de documento
    }
    
    /**
     * Envia proposta
     */
    private function sendProposal() {
        // Implementar envio de proposta
    }
    
    /**
     * Envia termos de negociaÃ§Ã£o
     */
    private function sendNegotiationTerms() {
        // Implementar envio de termos
    }
    
    /**
     * Envia email de sucesso (venda concluÃ­da)
     */
    private function sendSuccessEmail() {
        // Implementar envio de email
    }
    
    /**
     * Arquiva lead rejeitado
     */
    private function archiveLead() {
        // JÃ¡ estÃ¡ no status arquivado, apenas registrar
    }
    
    /**
     * ObtÃ©m histÃ³rico completo do lead
     */
    public function getHistory() {
        $sql = "SELECT * FROM lead_status_history 
                WHERE lead_id = :lead_id 
                ORDER BY changed_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lead_id' => $this->leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m status atual
     */
    public function getCurrentStatus() {
        return $this->leadData['status'] ?? self::STATUS_NOVO;
    }
    
    /**
     * ObtÃ©m transiÃ§Ãµes permitidas do status atual
     */
    public function getAllowedTransitions() {
        $currentStatus = $this->getCurrentStatus();
        return $this->validTransitions[$currentStatus] ?? [];
    }
    
    /**
     * ObtÃ©m tempo decorrido no status atual
     */
    public function getTimeInStatus() {
        $statusField = "status_{$this->getCurrentStatus()}_at";
        $createdAt = $this->leadData[$statusField] ?? $this->leadData['created_at'];
        
        $now = new DateTime();
        $created = new DateTime($createdAt);
        $diff = $now->diff($created);
        
        return [
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'total_seconds' => $now->getTimestamp() - $created->getTimestamp()
        ];
    }
    
    /**
     * ObtÃ©m status de um lead por ID
     */
    public static function getStatusById($db, $leadId) {
        $sql = "SELECT status FROM leads WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['status'] ?? self::STATUS_NOVO;
    }
    
    /**
     * Lista leads por status
     */
    public static function getLeadsByStatus($db, $status, $limit = 50, $offset = 0) {
        if (!in_array($status, [
            self::STATUS_NOVO,
            self::STATUS_CONTATADO,
            self::STATUS_ENGAJADO,
            self::STATUS_PROPOSTA,
            self::STATUS_NEGOCIANDO,
            self::STATUS_WON,
            self::STATUS_ARQUIVADO,
            self::STATUS_REJEITADO
        ])) {
            return [];
        }
        
        $sql = "SELECT * FROM leads 
                WHERE status = :status 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m estatÃ­sticas por status
     */
    public static function getStatusStats($db) {
        $sql = "SELECT status, COUNT(*) as count, 
                AVG(DATEDIFF(NOW(), created_at)) as avg_days_in_status
                FROM leads 
                WHERE deleted_at IS NULL
                GROUP BY status";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m funil de conversÃ£o
     */
    public static function getConversionFunnel($db) {
        $statuses = [
            self::STATUS_NOVO,
            self::STATUS_CONTATADO,
            self::STATUS_ENGAJADO,
            self::STATUS_PROPOSTA,
            self::STATUS_NEGOCIANDO,
            self::STATUS_WON
        ];
        
        $funnel = [];
        foreach ($statuses as $status) {
            $sql = "SELECT COUNT(*) as count FROM leads 
                    WHERE status = :status AND deleted_at IS NULL";
            $stmt = $db->prepare($sql);
            $stmt->execute(['status' => $status]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $funnel[$status] = $result['count'];
        }
        
        return $funnel;
    }
}

// API Endpoint para transiÃ§Ã£o de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transition') {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("CSRF token invÃ¡lido");
        }
        
        $leadId = intval($_POST['lead_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        // Conectar ao banco
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $workflow = new LeadStatusWorkflow($db, $leadId);
        $result = $workflow->transitionTo($newStatus, $reason, $userId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'code' => 'ERROR'
        ]);
        exit;
    }
}
?>
