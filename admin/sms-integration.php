<?php
/**
 * SMS Integration
 * Fase 3: Communication & Automation
 * 
 * IntegraÃ§Ã£o SMS para hot leads via Twilio
 * - Alertas para hot leads (score >= 80)
 * - Follow-ups rÃ¡pidos
 * - Tracking de respostas
 * 
 * @author CONFINTER
 * @version 1.0
 */

class SMSIntegration {
    
    private $accountSid;
    private $authToken;
    private $twilioPhoneNumber;
    private $db;
    
    public function __construct($db, $accountSid, $authToken, $twilioPhoneNumber) {
        $this->db = $db;
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->twilioPhoneNumber = $twilioPhoneNumber;
    }
    
    /**
     * Envia SMS para lead
     */
    public function sendSMS($leadId, $message) {
        try {
            // Obter dados do lead
            $lead = $this->getLead($leadId);
            if (!$lead || !$lead['telefone']) {
                throw new Exception("Lead nÃ£o encontrado ou sem telefone");
            }
            
            // Formatar nÃºmero
            $phoneNumber = $this->formatPhoneNumber($lead['telefone']);
            
            // Enviar via Twilio
            $response = $this->sendViatwilio($phoneNumber, $message);
            
            // Registrar no banco
            $this->recordSMS($leadId, $phoneNumber, $message, 'sent', $response['sid'] ?? null);
            
            // Atualizar lead
            $this->updateLastContact($leadId, 'sms');
            
            return [
                'success' => true,
                'message' => 'SMS enviado com sucesso',
                'sms_id' => $response['sid'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->recordSMS($leadId, $lead['telefone'] ?? null, $message, 'failed', null, $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia alerta para hot leads (score >= 80)
     */
    public function sendHotLeadAlert($leadId) {
        try {
            $lead = $this->getLead($leadId);
            if (!$lead) {
                throw new Exception("Lead nÃ£o encontrado");
            }
            
            // Verificar se Ã© hot
            if ($lead['lead_score'] < 80) {
                return [
                    'success' => false,
                    'message' => 'Lead nÃ£o Ã© hot (score < 80)'
                ];
            }
            
            // Mensagem de alerta
            $message = "OlÃ¡ {$lead['nome']}! ðŸ‘‹\n\n";
            $message .= "Temos uma soluÃ§Ã£o de crÃ©dito especial para vocÃª!\n\n";
            $message .= "Taxa: 0.99% a.m.\n";
            $message .= "AtÃ© R$ 500mil\n";
            $message .= "Responda SIM para falar com consultor\n\n";
            $message .= "CONFINTER";
            
            return $this->sendSMS($leadId, $message);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia SMS de follow-up
     */
    public function sendFollowUpSMS($leadId, $type = 'standard') {
        $templates = [
            'standard' => "Oi, ainda interessado Temos oferta especial apenas para hoje! Responda SIM",
            'proposal' => "Sua proposta chegou! Acesse o link: [link] - CONFINTER",
            'reminder' => "Lembrete: VocÃª pediu mais informaÃ§Ãµes. Estou aqui para ajudar!",
            'urgent' => "ðŸ”¥ URGENTE: PromoÃ§Ã£o termina HOJE! Aproveita - CONFINTER"
        ];
        
        $message = $templates[$type] ?? $templates['standard'];
        return $this->sendSMS($leadId, $message);
    }
    
    /**
     * Envia SMS em massa (broadcast)
     */
    public function broadcastSMS($filters = [], $message) {
        try {
            // Obter leads
            $leads = $this->getLeadsForBroadcast($filters);
            
            $results = [];
            foreach ($leads as $lead) {
                $result = $this->sendSMS($lead['id'], $message);
                $results[] = [
                    'lead_id' => $lead['id'],
                    'success' => $result['success']
                ];
                
                // Rate limiting
                usleep(100000);
            }
            
            return [
                'success' => true,
                'total_sent' => count(array_filter($results, fn($r) => $r['success'])),
                'total_failed' => count(array_filter($results, fn($r) => !$r['success'])),
                'results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa resposta SMS (webhook)
     */
    public function handleIncomingSMS($data) {
        try {
            $fromNumber = $data['From'] ?? '';
            $message = $data['Body'] ?? '';
            $smsId = $data['MessageSid'] ?? '';
            
            // Encontrar lead
            $lead = $this->findLeadByPhone(str_replace('+', '', $fromNumber));
            if (!$lead) {
                throw new Exception("Lead nÃ£o encontrado");
            }
            
            // Registrar SMS recebido
            $this->recordSMS($lead['id'], $fromNumber, $message, 'received', $smsId);
            
            // Atualizar lead
            $this->updateLeadEngagement($lead['id']);
            
            // AnÃ¡lise de resposta
            $sentiment = $this->analyzeSentiment($message);
            
            return [
                'success' => true,
                'lead_id' => $lead['id'],
                'sentiment' => $sentiment,
                'message' => 'SMS processado com sucesso'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analisa sentimento da resposta
     */
    private function analyzeSentiment($message) {
        $message = strtolower($message);
        
        // Positivas
        $positive = ['sim', 'ok', 'topa', 'yes', 'Ã³timo', 'legal', 'topado', 'avanÃ§a', 'bora'];
        // Negativas
        $negative = ['nÃ£o', 'nao', 'nunca', 'no', 'sair', 'parar', 'incomodado'];
        
        foreach ($positive as $word) {
            if (strpos($message, $word) !== false) {
                return 'positive';
            }
        }
        
        foreach ($negative as $word) {
            if (strpos($message, $word) !== false) {
                return 'negative';
            }
        }
        
        return 'neutral';
    }
    
    /**
     * Envia via Twilio
     */
    private function sendViatwilio($toNumber, $message) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        $postData = [
            'From' => $this->twilioPhoneNumber,
            'To' => $toNumber,
            'Body' => $message
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception("Erro ao enviar SMS via Twilio: {$httpCode}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Registra SMS
     */
    private function recordSMS($leadId, $phone, $message, $direction, $smsId = null, $error = null) {
        $sql = "INSERT INTO sms_messages 
                (lead_id, phone, message, direction, sms_id, error, sent_at) 
                VALUES (:lead_id, :phone, :message, :direction, :sms_id, :error, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $leadId,
            'phone' => $phone,
            'message' => $message,
            'direction' => $direction,
            'sms_id' => $smsId,
            'error' => $error
        ]);
    }
    
    /**
     * Formata nÃºmero de telefone
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) === 11) {
            $phone = '+55' . $phone;
        } elseif (strlen($phone) === 10) {
            $phone = '+5' . $phone;
        } elseif (strpos($phone, '+') === false) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Encontra lead pelo telefone
     */
    private function findLeadByPhone($phone) {
        $sql = "SELECT * FROM leads WHERE telefone LIKE :phone LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['phone' => "%{$phone}%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualiza Ãºltimo contato
     */
    private function updateLastContact($leadId, $type = 'sms') {
        $sql = "UPDATE leads SET last_contact_at = NOW(), last_contact_type = :type 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type, 'id' => $leadId]);
    }
    
    /**
     * Atualiza engajamento
     */
    private function updateLeadEngagement($leadId) {
        $sql = "UPDATE leads SET engagement_count = COALESCE(engagement_count, 0) + 1,
                engagement_last_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
    }
    
    /**
     * Carrega lead
     */
    private function getLead($leadId) {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m leads para broadcast
     */
    private function getLeadsForBroadcast($filters = []) {
        $sql = "SELECT * FROM leads WHERE 1=1";
        
        // Hot leads apenas se nÃ£o especificado
        if (!isset($filters['score_min'])) {
            $filters['score_min'] = 80;
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = '{$filters['status']}'";
        }
        
        if (!empty($filters['score_min'])) {
            $sql .= " AND lead_score >= {$filters['score_min']}";
        }
        
        $sql .= " AND telefone IS NOT NULL AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ObtÃ©m histÃ³rico de SMS
     */
    public function getSMSHistory($leadId, $limit = 50) {
        $sql = "SELECT * FROM sms_messages 
                WHERE lead_id = :lead_id 
                ORDER BY sent_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lead_id', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    /**
     * ObtÃ©m estatÃ­sticas SMS
     */
    public function getSMSStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN direction = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN direction = 'received' THEN 1 ELSE 0 END) as received,
                    SUM(CASE WHEN direction = 'failed' THEN 1 ELSE 0 END) as failed,
                    COUNT(DISTINCT lead_id) as unique_leads
                FROM sms_messages";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $sms = new SMSIntegration(
            $db,
            $_ENV['TWILIO_ACCOUNT_SID'] ?? '',
            $_ENV['TWILIO_AUTH_TOKEN'] ?? '',
            $_ENV['TWILIO_PHONE_NUMBER'] ?? ''
        );
        
        if ($_POST['action'] === 'send_sms') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $message = $_POST['message'] ?? '';
            $result = $sms->sendSMS($leadId, $message);
        } elseif ($_POST['action'] === 'send_hot_alert') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = $sms->sendHotLeadAlert($leadId);
        } elseif ($_POST['action'] === 'send_followup') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $type = $_POST['type'] ?? 'standard';
            $result = $sms->sendFollowUpSMS($leadId, $type);
        } elseif ($_POST['action'] === 'broadcast') {
            $message = $_POST['message'] ?? '';
            $result = $sms->broadcastSMS([], $message);
        } elseif ($_POST['action'] === 'get_history') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = [
                'success' => true,
                'messages' => $sms->getSMSHistory($leadId)
            ];
        } else {
            throw new Exception("AÃ§Ã£o invÃ¡lida");
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Webhook handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && isset($_POST['MessageSid'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $sms = new SMSIntegration(
            $db,
            $_ENV['TWILIO_ACCOUNT_SID'] ?? '',
            $_ENV['TWILIO_AUTH_TOKEN'] ?? '',
            $_ENV['TWILIO_PHONE_NUMBER'] ?? ''
        );
        
        $result = $sms->handleIncomingSMS($_POST);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
