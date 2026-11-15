<?php
/**
 * Email Automation Engine
 * Fase 3: Communication & Automation
 * 
 * Campanhas de email automÃ¡ticas baseadas em eventos:
 * - Triggered emails por status change
 * - Email sequences (nurturing)
 * - Behavioral automation
 * - Schedule & timing
 * 
 * @author CONFINTER
 * @version 1.0
 */

class EmailAutomationEngine {
    
    private $db;
    private $mailer;
    
    public function __construct($db) {
        $this->db = $db;
        $this->initMailer();
    }
    
    /**
     * Inicializa PHPMailer
     */
    private function initMailer() {
        // TODO: Configurar PHPMailer com SMTP
        // $this->mailer = new PHPMailer(true);
        // $this->mailer->isSMTP();
        // $this->mailer->Host = $_ENV['SMTP_HOST'];
        // $this->mailer->SMTPAuth = true;
        // $this->mailer->Username = $_ENV['SMTP_USER'];
        // $this->mailer->Password = $_ENV['SMTP_PASS'];
    }
    
    /**
     * Registra automaÃ§Ã£o para um lead
     */
    public function registerAutomation($leadId, $triggerId, $sequence) {
        try {
            $this->db->beginTransaction();
            
            // Registrar automaÃ§Ã£o ativa
            $sql = "INSERT INTO email_automations 
                    (lead_id, trigger_id, sequence, status, created_at) 
                    VALUES (:lead_id, :trigger_id, :sequence, 'active', NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'lead_id' => $leadId,
                'trigger_id' => $triggerId,
                'sequence' => json_encode($sequence)
            ]);
            
            // Enviar primeiro email imediatamente
            $automation = $this->getAutomationDetails($triggerId);
            if ($automation && !empty($automation['emails'])) {
                $firstEmail = $automation['emails'][0];
                $this->sendAutomatedEmail($leadId, $firstEmail);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'AutomaÃ§Ã£o registrada com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia email automÃ¡tico
     */
    private function sendAutomatedEmail($leadId, $emailTemplate) {
        try {
            // Obter dados do lead
            $lead = $this->getLead($leadId);
            if (!$lead || !$lead['email']) {
                throw new Exception("Lead ou email nÃ£o encontrado");
            }
            
            // Processar template
            $subject = $this->parseTemplate($emailTemplate['subject'], $lead);
            $body = $this->parseTemplate($emailTemplate['body'], $lead);
            $htmlBody = $this->emailBodyToHTML($body);
            
            // Enviar via SMTP
            $this->sendSMTP($lead['email'], $subject, $htmlBody);
            
            // Registrar envio
            $this->recordEmailSent($leadId, $subject, $body, 'automated');
            
            return [
                'success' => true,
                'sent_to' => $lead['email']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cria automaÃ§Ã£o triggered por status
     */
    public function createStatusTriggeredAutomation($leadId, $newStatus) {
        $triggers = $this->getStatusTriggers();
        
        if (!isset($triggers[$newStatus])) {
            return null;
        }
        
        $trigger = $triggers[$newStatus];
        return $this->registerAutomation($leadId, $newStatus, $trigger['sequence']);
    }
    
    /**
     * Define triggers por status
     */
    private function getStatusTriggers() {
        return [
            'novo' => [
                'name' => 'New Lead Welcome',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 0,
                        'email' => [
                            'subject' => 'Bem-vindo ao CONFINTER ðŸ‘‹',
                            'body' => "OlÃ¡ {{name}},\n\nObrigado por se interessar em nossos serviÃ§os!\n\nAqui na CONFINTER, nos especializamos em soluÃ§Ãµes de crÃ©dito personalizadas para sua empresa.\n\nEm breve, um de nossos consultores entrarÃ¡ em contato para conhecer suas necessidades.\n\nAtt,\nEquipe CONFINTER"
                        ]
                    ]
                ]
            ],
            'contatado' => [
                'name' => 'Lead Contacted',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 0,
                        'email' => [
                            'subject' => 'InformaÃ§Ãµes sobre {{segment}}',
                            'body' => "Oi {{name}},\n\nSegue em anexo informaÃ§Ãµes relevantes sobre linhas de crÃ©dito para {{segment}}.\n\nTemos opÃ§Ãµes competitivas que podem atender bem a {{company}}.\n\nQual o melhor momento para conversarmos\n\nAtt"
                        ]
                    ],
                    [
                        'delay' => 1440, // 24 horas
                        'email' => [
                            'subject' => 'VocÃª recebeu as informaÃ§Ãµes',
                            'body' => "Oi {{name}},\n\nJust checking - vocÃª recebeu o material que enviamos\n\nTem alguma dÃºvida ou gostaria de discuti-lo melhor\n\nAtt"
                        ]
                    ]
                ]
            ],
            'engajado' => [
                'name' => 'Lead Engaged',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 0,
                        'email' => [
                            'subject' => 'Proposta Personalizada para {{company}}',
                            'body' => "Oi {{name}},\n\nâœ… Sua proposta personalizada estÃ¡ pronta!\n\nCom base em suas informaÃ§Ãµes, criamos uma proposta com as melhores linhas de crÃ©dito.\n\nTaxa inicial: 0,99% a.m.\nValor mÃ¡ximo: atÃ© R$ 500.000\nPrazo: atÃ© 60 meses\n\nAgendar reuniÃ£o agora\n\nAtt"
                        ]
                    ],
                    [
                        'delay' => 2880, // 48 horas
                        'email' => [
                            'subject' => 'Revisando a proposta: {{segment}}',
                            'body' => "OlÃ¡ {{name}},\n\nQual sua avaliaÃ§Ã£o sobre a proposta que enviamos\n\nEstou disponÃ­vel para qualquer ajuste ou dÃºvida.\n\nAtt"
                        ]
                    ]
                ]
            ],
            'proposta' => [
                'name' => 'Proposal Sent',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 4320, // 72 horas
                        'email' => [
                            'subject' => 'Ãšltima chance: Oferta especial',
                            'body' => "Oi {{name}},\n\nðŸŽ¯ PromoÃ§Ã£o Especial - Ãšltima Chance\n\nApenaspara os prÃ³ximos 3 dias:\n- 15% de desconto em taxa\n- AprovaÃ§Ã£o em 24h\n- Sem burocracias\n\nVamos finalizar isso\n\nAtt"
                        ]
                    ]
                ]
            ],
            'negociando' => [
                'name' => 'In Negotiation',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 0,
                        'email' => [
                            'subject' => 'Esclarecendo os termos',
                            'body' => "OlÃ¡ {{name}},\n\nPara finalizar, segue resumo dos termos acordados:\n\n- Taxa: X% a.m.\n- Valor: R$ Y\n- Prazo: Z meses\n- Primeira parcela: Data X\n\nConfirma ðŸ‘\n\nAtt"
                        ]
                    ]
                ]
            ],
            'won' => [
                'name' => 'Deal Won',
                'delay' => 0,
                'sequence' => [
                    [
                        'delay' => 0,
                        'email' => [
                            'subject' => 'ðŸŽ‰ Contrato Confirmado!',
                            'body' => "ParabÃ©ns {{name}}! ðŸŽ‰\n\nSua contrataÃ§Ã£o foi confirmada!\n\nNos prÃ³ximos 24h, vocÃª receberÃ¡ contato de nosso gerenciador de contas para finalizar os procedimentos.\n\nObrigado por confiar em CONFINTER!\n\nAtt,\nEquipe CONFINTER"
                        ]
                    ],
                    [
                        'delay' => 10080, // 7 dias
                        'email' => [
                            'subject' => 'Como estÃ¡ indo ðŸ˜Š',
                            'body' => "Oi {{name}},\n\nComo estÃ¡ sendo sua experiÃªncia com CONFINTER\n\nTemos muita vontade de melhorar. Suas sugestÃµes e feedback sÃ£o valiosos!\n\nAtt"
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Processa automaÃ§Ãµes pendentes (cron job)
     */
    public function processAutomations() {
        try {
            // Obter automaÃ§Ãµes que precisam ser acionadas
            $sql = "SELECT ea.*, l.email 
                    FROM email_automations ea
                    JOIN leads l ON l.id = ea.lead_id
                    WHERE ea.status = 'active'
                    AND ea.next_email_at <= NOW()
                    AND l.deleted_at IS NULL
                    LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            foreach ($automations as $automation) {
                // Processar
                $this->executeAutomation($automation);
                $processed++;
            }
            
            return [
                'success' => true,
                'processed' => $processed
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Executa automaÃ§Ã£o individual
     */
    private function executeAutomation($automation) {
        $sequence = json_decode($automation['sequence'], true);
        $sent = $automation['sent_count'] ?? 0;
        
        if ($sent >= count($sequence)) {
            // AutomaÃ§Ã£o completa
            $this->completeAutomation($automation['id']);
            return;
        }
        
        // PrÃ³ximo email
        $nextEmail = $sequence[$sent];
        $this->sendAutomatedEmail($automation['lead_id'], $nextEmail['email']);
        
        // Atualizar contador e prÃ³ximo schedule
        $nextDelay = $sequence[$sent + 1]['delay'] ?? 0;
        $nextTime = date('Y-m-d H:i:s', strtotime("+{$nextDelay} minutes"));
        
        $sql = "UPDATE email_automations 
                SET sent_count = sent_count + 1, 
                    next_email_at = :next_time 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'next_time' => $nextTime,
            'id' => $automation['id']
        ]);
    }
    
    /**
     * Conclui automaÃ§Ã£o
     */
    private function completeAutomation($automationId) {
        $sql = "UPDATE email_automations SET status = 'completed', completed_at = NOW() 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $automationId]);
    }
    
    /**
     * Envia via SMTP
     */
    private function sendSMTP($to, $subject, $htmlBody) {
        // Usar PHPMailer ou similar
        // $this->mailer->addAddress($to);
        // $this->mailer->Subject = $subject;
        // $this->mailer->Body = $htmlBody;
        // $this->mailer->send();
    }
    
    /**
     * Registra email enviado
     */
    private function recordEmailSent($leadId, $subject, $body, $type) {
        $sql = "INSERT INTO email_log 
                (lead_id, subject, body, type, sent_at) 
                VALUES (:lead_id, :subject, :body, :type, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'lead_id' => $leadId,
            'subject' => $subject,
            'body' => $body,
            'type' => $type
        ]);
    }
    
    /**
     * Processa template com dados do lead
     */
    private function parseTemplate($template, $lead) {
        $replacements = [
            '{{name}}' => $lead['nome'] ?? 'Cliente',
            '{{company}}' => $lead['empresa'] ?? 'sua empresa',
            '{{email}}' => $lead['email'] ?? '',
            '{{segment}}' => $lead['segmento'] ?? '',
        ];
        
        $result = $template;
        foreach ($replacements as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        
        return $result;
    }
    
    /**
     * Converte texto plano para HTML
     */
    private function emailBodyToHTML($text) {
        $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6;'>";
        $html .= "<div style='max-width: 600px; margin: 0 auto; color: #333;'>";
        $html .= "<p>" . nl2br(htmlspecialchars($text)) . "</p>";
        $html .= "<hr style='border: none; border-top: 1px solid #ccc; margin: 20px 0;'>";
        $html .= "<p style='font-size: 12px; color: #999;'>";
        $html .= "CONFINTER - SoluÃ§Ãµes em CrÃ©dito | www.confinter.com.br<br>";
        $html .= "</p>";
        $html .= "</div></body></html>";
        return $html;
    }
    
    /**
     * ObtÃ©m detalhes de automaÃ§Ã£o
     */
    private function getAutomationDetails($triggerId) {
        $triggers = $this->getStatusTriggers();
        return $triggers[$triggerId] ?? null;
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
     * ObtÃ©m histÃ³rico de automaÃ§Ãµes do lead
     */
    public function getAutomationHistory($leadId) {
        $sql = "SELECT * FROM email_automations 
                WHERE lead_id = :lead_id 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lead_id' => $leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Pausa automaÃ§Ã£o
     */
    public function pauseAutomation($automationId) {
        $sql = "UPDATE email_automations SET status = 'paused' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $automationId]);
        return ['success' => true];
    }
    
    /**
     * Resume automaÃ§Ã£o
     */
    public function resumeAutomation($automationId) {
        $sql = "UPDATE email_automations SET status = 'active' WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $automationId]);
        return ['success' => true];
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $automation = new EmailAutomationEngine($db);
        
        if ($_POST['action'] === 'register_automation') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $triggerId = $_POST['trigger_id'] ?? '';
            $result = $automation->createStatusTriggeredAutomation($leadId, $triggerId);
        } elseif ($_POST['action'] === 'process_automations') {
            $result = $automation->processAutomations();
        } elseif ($_POST['action'] === 'get_history') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = [
                'success' => true,
                'automations' => $automation->getAutomationHistory($leadId)
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
?>
