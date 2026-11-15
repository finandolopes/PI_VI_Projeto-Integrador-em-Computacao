<?php
/**
 * WhatsApp Integration
 * Fase 3: Communication & Automation
 * 
 * IntegraÃ§Ã£o com WhatsApp Business API via Twilio
 * - Envio de mensagens para leads
 * - Template messages
 * - Webhook para mensagens recebidas
 * - HistÃ³rico de conversas
 * 
 * @author CONFINTER
 * @version 1.0
 */

class WhatsAppIntegration {
    
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
     * Envia mensagem WhatsApp para lead
     */
    public function sendMessage($leadId, $message, $type = 'text', $mediaUrl = null) {
        try {
            // Obter dados do lead
            $lead = $this->getLead($leadId);
            if (!$lead || !$lead['telefone']) {
                throw new Exception("Lead nÃ£o encontrado ou sem telefone");
            }
            
            // Formatar nÃºmero para WhatsApp (adicionar cÃ³digo paÃ­s)
            $whatsappNumber = $this->formatWhatsAppNumber($lead['telefone']);
            
            // Enviar via Twilio
            $response = $this->sendViatwilio($whatsappNumber, $message, $type, $mediaUrl);
            
            // Registrar no banco
            $this->recordMessage($leadId, $whatsappNumber, $message, 'sent', $response['sid'] ?? null);
            
            // Atualizar lead com Ãºltimo contato
            $this->updateLastContact($leadId, 'whatsapp');
            
            return [
                'success' => true,
                'message' => 'Mensagem enviada com sucesso',
                'message_id' => $response['sid'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->recordMessage($leadId, $lead['telefone'] ?? null, $message, 'failed', null, $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia mensagem via Twilio
     */
    private function sendViatwilio($toNumber, $message, $type = 'text', $mediaUrl = null) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        
        $postData = [
            'From' => "whatsapp:{$this->twilioPhoneNumber}",
            'To' => "whatsapp:{$toNumber}",
            'Body' => $message
        ];
        
        if ($type === 'media' && $mediaUrl) {
            $postData['MediaUrl'] = $mediaUrl;
        }
        
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
            throw new Exception("Erro ao enviar via Twilio: {$httpCode}");
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Envia template message predefinida
     */
    public function sendTemplate($leadId, $templateName) {
        $templates = $this->getTemplates();
        
        if (!isset($templates[$templateName])) {
            return [
                'success' => false,
                'message' => "Template nÃ£o encontrado: {$templateName}"
            ];
        }
        
        $template = $templates[$templateName];
        $message = $this->parseTemplate($template, $leadId);
        
        return $this->sendMessage($leadId, $message);
    }
    
    /**
     * ObtÃ©m templates de mensagem
     */
    private function getTemplates() {
        return [
            'welcome' => [
                'subject' => 'Bem-vindo ao CONFINTER',
                'body' => "OlÃ¡ {{name}}, bem-vindo ao CONFINTER! ðŸ‘‹\n\nEstamos aqui para ajudÃ¡-lo a encontrar as melhores soluÃ§Ãµes em linhas de crÃ©dito.\n\nCom que podemos ajudÃ¡-lo ðŸ˜Š"
            ],
            'qualification' => [
                'subject' => 'QualificaÃ§Ã£o de Lead',
                'body' => "OlÃ¡ {{name}},\n\nGostaria de fazer algumas perguntas rÃ¡pidas para entender melhor suas necessidades:\n\n1. Qual Ã© o segmento da sua empresa\n2. Qual valor aproximado vocÃª precisa\n3. Qual Ã© a melhor forma de contato\n\nCom isto, posso enviar proposta personalizada! ðŸ“‹"
            ],
            'proposal' => [
                'subject' => 'Sua Proposta Personalizada',
                'body' => "OlÃ¡ {{name}},\n\nâœ… Sua proposta estÃ¡ pronta!\n\nEnviei um documento PDF com as melhores opÃ§Ãµes de linhas de crÃ©dito para {{company}}.\n\nPodemos agendar uma reuniÃ£o para discuti-la ðŸ“ž"
            ],
            'follow_up' => [
                'subject' => 'Seguimento - Proposta CONFINTER',
                'body' => "OlÃ¡ {{name}},\n\nComo vai Recebeu a proposta que enviamos\n\nFique Ã  vontade para enviar qualquer dÃºvida ou sugestÃ£o. Estou aqui para ajudar! ðŸ’ª"
            ],
            'closing' => [
                'subject' => 'Oferta Especial Hoje',
                'body' => "OlÃ¡ {{name}},\n\nðŸŽ¯ PromoÃ§Ã£o Especial\n\nAtÃ© hoje, oferecemos 15% de desconto em taxa para novos clientes!\n\nVamos conversar NÃ£o perca esta oportunidade! â°"
            ],
            'thank_you' => [
                'subject' => 'Obrigado por Contratar com CONFINTER',
                'body' => "OlÃ¡ {{name}},\n\nðŸŽ‰ ParabÃ©ns!\n\nSua contrataÃ§Ã£o foi confirmada. Vamos colocÃ¡-lo em contato com nosso gerenciador de contas nos prÃ³ximos 24h.\n\nObrigado por confiar em nÃ³s! ðŸ™"
            ]
        ];
    }
    
    /**
     * Processa template com dados do lead
     */
    private function parseTemplate($template, $leadId) {
        $lead = $this->getLead($leadId);
        
        $replacements = [
            '{{name}}' => $lead['nome'] ?? 'Cliente',
            '{{company}}' => $lead['empresa'] ?? 'sua empresa',
            '{{email}}' => $lead['email'] ?? '',
            '{{segment}}' => $lead['segmento'] ?? '',
        ];
        
        $body = $template['body'];
        foreach ($replacements as $key => $value) {
            $body = str_replace($key, $value, $body);
        }
        
        return $body;
    }
    
    /**
     * Processa webhook de mensagem recebida
     */
    public function handleIncomingMessage($data) {
        try {
            // Validar webhook
            if (!$this->validateWebhookSignature($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '', $data)) {
                throw new Exception("Assinatura de webhook invÃ¡lida");
            }
            
            // Extrair dados
            $fromNumber = $data['From'] ?? '';
            $message = $data['Body'] ?? '';
            $messageId = $data['MessageSid'] ?? '';
            
            // Encontrar lead pelo nÃºmero
            $lead = $this->findLeadByPhone(str_replace('whatsapp:', '', $fromNumber));
            if (!$lead) {
                throw new Exception("Lead nÃ£o encontrado para nÃºmero: {$fromNumber}");
            }
            
            // Registrar mensagem
            $this->recordMessage($lead['id'], $fromNumber, $message, 'received', $messageId);
            
            // Atualizar status do lead
            $this->updateLeadEngagement($lead['id']);
            
            // Responder automaticamente (opcional)
            $this->sendAutoResponse($lead['id']);
            
            return [
                'success' => true,
                'lead_id' => $lead['id'],
                'message' => 'Mensagem processada com sucesso'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida assinatura do webhook
     */
    private function validateWebhookSignature($signature, $data) {
        // TODO: Implementar validaÃ§Ã£o de assinatura Twilio
        return true;
    }
    
    /**
     * Encontra lead pelo nÃºmero de telefone
     */
    private function findLeadByPhone($phone) {
        $sql = "SELECT * FROM leads WHERE telefone LIKE :phone LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['phone' => "%{$phone}%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Registra mensagem no banco
     */
    private function recordMessage($leadId, $phone, $message, $direction, $messageId = null, $error = null) {
        $sql = "INSERT INTO whatsapp_messages 
                (lead_id, phone, message, direction, message_id, error, sent_at) 
                VALUES (:lead_id, :phone, :message, :direction, :message_id, :error, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $leadId,
            'phone' => $phone,
            'message' => $message,
            'direction' => $direction,
            'message_id' => $messageId,
            'error' => $error
        ]);
    }
    
    /**
     * Atualiza Ãºltimo contato
     */
    private function updateLastContact($leadId, $type = 'whatsapp') {
        $sql = "UPDATE leads SET last_contact_at = NOW(), last_contact_type = :type 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type, 'id' => $leadId]);
    }
    
    /**
     * Atualiza engajamento do lead
     */
    private function updateLeadEngagement($leadId) {
        $sql = "UPDATE leads SET engagement_count = COALESCE(engagement_count, 0) + 1,
                engagement_last_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
    }
    
    /**
     * Envia resposta automÃ¡tica
     */
    private function sendAutoResponse($leadId) {
        // TODO: Implementar lÃ³gica de resposta automÃ¡tica
    }
    
    /**
     * Formata nÃºmero para WhatsApp
     */
    private function formatWhatsAppNumber($phone) {
        // Remove caracteres especiais
        $phone = preg_replace('/\D/', '', $phone);
        
        // Se nÃ£o tem cÃ³digo de paÃ­s, adiciona +55 (Brasil)
        if (strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * ObtÃ©m histÃ³rico de mensagens do lead
     */
    public function getConversationHistory($leadId, $limit = 50) {
        $sql = "SELECT * FROM whatsapp_messages 
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
     * Carrega lead
     */
    private function getLead($leadId) {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Envia mensagens em massa para leads (broadcast)
     */
    public function broadcastMessage($templateName, $filters = []) {
        try {
            // Obter leads
            $leads = $this->getLeadsForBroadcast($filters);
            
            $results = [];
            foreach ($leads as $lead) {
                $result = $this->sendTemplate($lead['id'], $templateName);
                $results[] = [
                    'lead_id' => $lead['id'],
                    'success' => $result['success']
                ];
                
                // Rate limiting - 1 mensagem a cada 100ms
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
     * ObtÃ©m leads para broadcast
     */
    private function getLeadsForBroadcast($filters = []) {
        $sql = "SELECT * FROM leads WHERE 1=1";
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = '{$filters['status']}'";
        }
        
        if (!empty($filters['segment'])) {
            $sql .= " AND segmento = '{$filters['segment']}'";
        }
        
        if (!empty($filters['score_min'])) {
            $sql .= " AND lead_score >= {$filters['score_min']}";
        }
        
        $sql .= " AND telefone IS NOT NULL AND deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        // Credenciais Twilio (usar variÃ¡veis de ambiente)
        $whatsapp = new WhatsAppIntegration(
            $db,
            $_ENV['TWILIO_ACCOUNT_SID'] ?? '',
            $_ENV['TWILIO_AUTH_TOKEN'] ?? '',
            $_ENV['TWILIO_PHONE_NUMBER'] ?? ''
        );
        
        if ($_POST['action'] === 'send_message') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $message = $_POST['message'] ?? '';
            $result = $whatsapp->sendMessage($leadId, $message);
        } elseif ($_POST['action'] === 'send_template') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $templateName = $_POST['template'] ?? '';
            $result = $whatsapp->sendTemplate($leadId, $templateName);
        } elseif ($_POST['action'] === 'get_history') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = [
                'success' => true,
                'messages' => $whatsapp->getConversationHistory($leadId)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $whatsapp = new WhatsAppIntegration(
            $db,
            $_ENV['TWILIO_ACCOUNT_SID'] ?? '',
            $_ENV['TWILIO_AUTH_TOKEN'] ?? '',
            $_ENV['TWILIO_PHONE_NUMBER'] ?? ''
        );
        
        $result = $whatsapp->handleIncomingMessage($_POST);
        
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
