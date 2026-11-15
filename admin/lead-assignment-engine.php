<?php
/**
 * Smart Lead Assignment Engine
 * Fase 2: Lead Management & CRM
 * 
 * Auto-assign leads to sales team based on:
 * - Capacity (current workload)
 * - Expertise (segment/product knowledge)
 * - Geographic region (territory assignment)
 * - Lead quality (hot leads to top performers)
 * 
 * @author CONFINTER
 * @version 1.0
 */

class SmartLeadAssignment {
    
    private $db;
    private $assignmentStrategy = 'balanced'; // balanced, capacity, expertise, quality
    
    // Pesos para algoritmo de assignment
    private $weights = [
        'capacity' => 0.30,      // 30% - NÃ£o sobrecarregar
        'expertise' => 0.25,     // 25% - Conhecimento do segmento
        'geography' => 0.25,     // 25% - RegiÃ£o geogrÃ¡fica
        'quality' => 0.20        // 20% - Vendedor com melhor taxa
    ];
    
    public function __construct($db, $strategy = 'balanced') {
        $this->db = $db;
        $this->assignmentStrategy = $strategy;
    }
    
    /**
     * Assign lead para melhor vendedor disponÃ­vel
     * @param int $leadId ID do lead
     * @return array Resultado do assignment
     */
    public function assignLead($leadId) {
        try {
            // Carregar dados do lead
            $lead = $this->getLead($leadId);
            if (!$lead) {
                return [
                    'success' => false,
                    'message' => "Lead nÃ£o encontrado: {$leadId}"
                ];
            }
            
            // Obter vendedores elegÃ­veis
            $salesReps = $this->getEligibleSalesReps($lead);
            if (empty($salesReps)) {
                return [
                    'success' => false,
                    'message' => "Nenhum vendedor disponÃ­vel para este lead"
                ];
            }
            
            // Calcular score para cada vendedor
            $scores = $this->calculateScoresForReps($salesReps, $lead);
            
            // Selecionar melhor vendedor
            $bestRep = $this->selectBestRep($scores);
            
            // Atualizar assignment
            $this->db->beginTransaction();
            
            $sql = "UPDATE leads 
                    SET assigned_to = :sales_rep_id, 
                        assigned_at = NOW(), 
                        assignment_status = 'assigned'
                    WHERE id = :lead_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'sales_rep_id' => $bestRep['sales_rep_id'],
                'lead_id' => $leadId
            ]);
            
            // Registrar histÃ³rico
            $this->recordAssignment($leadId, $bestRep['sales_rep_id'], $scores);
            
            // Criar task para vendedor
            $this->createFollowUpTask($leadId, $bestRep['sales_rep_id']);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Lead atribuÃ­do com sucesso",
                'assigned_to' => $bestRep,
                'score' => $scores[$bestRep['sales_rep_id']]['total_score'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => "Erro ao atribuir lead: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Carrega dados do lead
     */
    private function getLead($leadId) {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m vendedores elegÃ­veis para este lead
     */
    private function getEligibleSalesReps($lead) {
        $sql = "SELECT 
                    u.id as sales_rep_id,
                    u.name,
                    u.email,
                    COALESCE(u.specialties, '') as specialties,
                    COALESCE(u.territories, '') as territories,
                    COUNT(DISTINCT l.id) as current_workload,
                    ROUND(AVG(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) * 100, 2) as conversion_rate
                FROM users u
                LEFT JOIN leads l ON l.assigned_to = u.id 
                    AND l.deleted_at IS NULL 
                    AND l.status NOT IN ('won', 'arquivado', 'rejeitado')
                WHERE u.role = 'sales' 
                    AND u.status = 'active'
                    AND u.deleted_at IS NULL
                GROUP BY u.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcula score para cada vendedor
     */
    private function calculateScoresForReps($salesReps, $lead) {
        $scores = [];
        $maxWorkload = $this->getMaxWorkload();
        
        foreach ($salesReps as $rep) {
            $score = [];
            
            // 1. Score de capacidade (menor workload Ã© melhor)
            $capacityScore = max(0, 100 - (($rep['current_workload'] / $maxWorkload) * 100));
            $score['capacity'] = $capacityScore;
            
            // 2. Score de expertise (especialidades match)
            $expertiseScore = $this->calculateExpertiseScore($rep, $lead);
            $score['expertise'] = $expertiseScore;
            
            // 3. Score de geografia (territÃ³rio match)
            $geoScore = $this->calculateGeographyScore($rep, $lead);
            $score['geography'] = $geoScore;
            
            // 4. Score de qualidade (conversion rate)
            $qualityScore = (float)($rep['conversion_rate'] ?? 0);
            $score['quality'] = $qualityScore;
            
            // Calcular score total ponderado
            $totalScore = 
                ($score['capacity'] * $this->weights['capacity']) +
                ($score['expertise'] * $this->weights['expertise']) +
                ($score['geography'] * $this->weights['geography']) +
                ($score['quality'] * $this->weights['quality']);
            
            $score['total_score'] = round($totalScore, 2);
            $score['rep_name'] = $rep['name'];
            $score['rep_email'] = $rep['email'];
            $score['workload'] = $rep['current_workload'];
            
            $scores[$rep['sales_rep_id']] = $score;
        }
        
        return $scores;
    }
    
    /**
     * Calcula score de expertise
     */
    private function calculateExpertiseScore($rep, $lead) {
        $specialties = explode(',', $rep['specialties'] ?? '');
        $leadSegment = strtolower(trim($lead['segmento'] ?? ''));
        
        foreach ($specialties as $specialty) {
            if (strtolower(trim($specialty)) === $leadSegment) {
                return 100; // Match perfeito
            }
        }
        
        return 50; // Partial match ou sem expertise
    }
    
    /**
     * Calcula score de geografia
     */
    private function calculateGeographyScore($rep, $lead) {
        $territories = explode(',', $rep['territories'] ?? '');
        $leadUF = strtoupper(trim($lead['uf'] ?? ''));
        
        foreach ($territories as $territory) {
            if (strtoupper(trim($territory)) === $leadUF) {
                return 100; // Mesma regiÃ£o
            }
        }
        
        return 50; // RegiÃ£o diferente, mas pode trabalhar
    }
    
    /**
     * Seleciona o melhor vendedor
     */
    private function selectBestRep($scores) {
        $bestRep = null;
        $bestScore = -1;
        
        foreach ($scores as $repId => $score) {
            if ($score['total_score'] > $bestScore) {
                $bestScore = $score['total_score'];
                $bestRep = ['sales_rep_id' => $repId, ...$score];
            }
        }
        
        return $bestRep;
    }
    
    /**
     * ObtÃ©m mÃ¡ximo de workload por vendedor
     */
    private function getMaxWorkload() {
        // Se nÃ£o houver limite configurado, usar default (30 leads)
        return 30;
    }
    
    /**
     * Registra histÃ³rico de assignment
     */
    private function recordAssignment($leadId, $salesRepId, $scores) {
        $sql = "INSERT INTO lead_assignments 
                (lead_id, assigned_to, scores, assigned_at) 
                VALUES (:lead_id, :assigned_to, :scores, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $leadId,
            'assigned_to' => $salesRepId,
            'scores' => json_encode($scores)
        ]);
    }
    
    /**
     * Cria task de follow-up para vendedor
     */
    private function createFollowUpTask($leadId, $salesRepId) {
        $followUpDate = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $sql = "INSERT INTO tasks 
                (lead_id, assigned_to, task_type, title, description, due_date, created_at) 
                VALUES (:lead_id, :assigned_to, 'follow_up', 'Contact new lead', 'Call or email lead', :due_date, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $leadId,
            'assigned_to' => $salesRepId,
            'due_date' => $followUpDate
        ]);
    }
    
    /**
     * Reassigna lead para outro vendedor
     */
    public function reassignLead($leadId, $newSalesRepId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Obter vendedor anterior
            $sql = "SELECT assigned_to FROM leads WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $leadId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $previousRepId = $result['assigned_to'] ?? null;
            
            // Atualizar assignment
            $sql = "UPDATE leads 
                    SET assigned_to = :sales_rep_id, 
                        reassigned_count = COALESCE(reassigned_count, 0) + 1
                    WHERE id = :lead_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'sales_rep_id' => $newSalesRepId,
                'lead_id' => $leadId
            ]);
            
            // Registrar reassignment
            $sql = "INSERT INTO lead_reassignments 
                    (lead_id, from_rep_id, to_rep_id, reason, reassigned_at) 
                    VALUES (:lead_id, :from_rep_id, :to_rep_id, :reason, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'lead_id' => $leadId,
                'from_rep_id' => $previousRepId,
                'to_rep_id' => $newSalesRepId,
                'reason' => $reason
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Lead reassigned com sucesso",
                'from_rep' => $previousRepId,
                'to_rep' => $newSalesRepId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => "Erro ao reassignar lead: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * ObtÃ©m leads nÃ£o atribuÃ­dos
     */
    public function getUnassignedLeads($limit = 50) {
        $sql = "SELECT * FROM leads 
                WHERE assigned_to IS NULL 
                    AND status = 'novo'
                    AND deleted_at IS NULL
                ORDER BY created_at ASC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Auto-assign todos os leads nÃ£o atribuÃ­dos
     */
    public function autoAssignPendingLeads() {
        $unassignedLeads = $this->getUnassignedLeads(100);
        $results = [];
        
        foreach ($unassignedLeads as $lead) {
            $result = $this->assignLead($lead['id']);
            $results[] = [
                'lead_id' => $lead['id'],
                'success' => $result['success'],
                'assigned_to' => $result['assigned_to']['sales_rep_id'] ?? null
            ];
        }
        
        return [
            'total' => count($results),
            'assigned' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results
        ];
    }
    
    /**
     * ObtÃ©m estatÃ­sticas de workload por vendedor
     */
    public function getWorkloadStats() {
        $sql = "SELECT 
                    u.id,
                    u.name,
                    COUNT(DISTINCT CASE WHEN l.status NOT IN ('won', 'arquivado', 'rejeitado') THEN l.id END) as active_leads,
                    COUNT(DISTINCT CASE WHEN l.status = 'won' THEN l.id END) as won_leads,
                    ROUND(AVG(CASE WHEN l.status = 'won' THEN 1 ELSE 0 END) * 100, 2) as conversion_rate
                FROM users u
                LEFT JOIN leads l ON l.assigned_to = u.id AND l.deleted_at IS NULL
                WHERE u.role = 'sales' AND u.deleted_at IS NULL
                GROUP BY u.id
                ORDER BY active_leads ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m histÃ³rico de assignments do lead
     */
    public function getAssignmentHistory($leadId) {
        $sql = "SELECT 
                    la.assigned_at,
                    u.name as assigned_to_name,
                    la.scores
                FROM lead_assignments la
                LEFT JOIN users u ON u.id = la.assigned_to
                WHERE la.lead_id = :lead_id
                ORDER BY la.assigned_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lead_id' => $leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// API Endpoint para auto-assign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("CSRF token invÃ¡lido");
        }
        
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $assignment = new SmartLeadAssignment($db);
        
        if ($_POST['action'] === 'assign_lead') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = $assignment->assignLead($leadId);
        } elseif ($_POST['action'] === 'reassign_lead') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $newRepId = intval($_POST['new_rep_id'] ?? 0);
            $reason = $_POST['reason'] ?? '';
            $result = $assignment->reassignLead($leadId, $newRepId, $reason);
        } elseif ($_POST['action'] === 'auto_assign_pending') {
            $result = $assignment->autoAssignPendingLeads();
        } else {
            throw new Exception("AÃ§Ã£o invÃ¡lida");
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>
