<?php
/**
 * Chat API Backend
 * Fase 3: Communication & Automation
 * 
 * Backend para processar mensagens de chat
 * - Pre-qualificação automática
 * - Roteamento para agente
 * - Lead scoring via conversa
 * 
 * @author CONFINTER
 * @version 1.0
 */

class ChatBot {
    private $db;
    private $qualifierQuestions = [];
    private $qualifierData = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->initializeQualifierQuestions();
    }
    
    /**
     * Inicializa perguntas qualificadoras
     */
    private function initializeQualifierQuestions() {
        $this->qualifierQuestions = [
            [
                'id' => 'segment',
                'question' => 'Qual é o seu ramo de negócio?',
                'options' => [
                    'Comércio',
                    'Indústria',
                    'Serviços',
                    'Agronegócio',
                    'Tecnologia',
                    'Outro'
                ]
            ],
            [
                'id' => 'company_size',
                'question' => 'Qual o tamanho da sua empresa?',
                'options' => [
                    'Pequena (1-10 funcionários)',
                    'Média (11-50 funcionários)',
                    'Grande (51-200 funcionários)',
                    'Muito Grande (200+ funcionários)'
                ]
            ],
            [
                'id' => 'need',
                'question' => 'Qual é sua principal necessidade?',
                'options' => [
                    'Crédito para capital de giro',
                    'Financiamento de equipamento',
                    'Investimento em expansão',
                    'Refinanciamento de dívidas',
                    'Não tenho certeza'
                ]
            ],
            [
                'id' => 'urgency',
                'question' => 'Qual a urgência da sua necessidade?',
                'options' => [
                    'Muito urgente (próximas 2 semanas)',
                    'Urgente (próximo mês)',
                    'Normal (próximos 2-3 meses)',
                    'Sem pressa'
                ]
            ],
            [
                'id' => 'amount',
                'question' => 'Qual valor aproximado você precisa?',
                'options' => [
                    'Até R$ 50 mil',
                    'R$ 50-100 mil',
                    'R$ 100-250 mil',
                    'R$ 250-500 mil',
                    'Acima de R$ 500 mil'
                ]
            ]
        ];
    }
    
    /**
     * Processa mensagem do usuário
     */
    public function processMessage($message, $sessionId, $leadId = null) {
        try {
            // Obter ou criar lead
            $lead = $this->getOrCreateLead($sessionId, $leadId);
            $leadId = $lead['id'];
            
            // Registrar mensagem
            $this->recordMessage($leadId, $message, 'incoming', $sessionId);
            
            // Analisar intenção
            $intent = $this->analyzeIntent($message);
            
            // Gerar resposta
            $response = $this->generateResponse($message, $intent, $lead);
            
            // Verificar próxima pergunta qualificadora
            $nextQuestion = $this->getNextQualifierQuestion($leadId);
            
            // Registrar resposta
            $this->recordMessage($leadId, $response['text'], 'outgoing', $sessionId);
            
            // Atualizar scoring
            $this->updateLeadScore($leadId);
            
            return [
                'success' => true,
                'lead_id' => $leadId,
                'response' => $response['text'],
                'intent' => $intent,
                'qualifier' => $nextQuestion,
                'sentiment' => $response['sentiment']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analisa intenção da mensagem
     */
    private function analyzeIntent($message) {
        $message = strtolower($message);
        
        // Keywords por intenção
        $intents = [
            'credit' => ['crédito', 'empréstimo', 'capital', 'dinheiro', 'financiar', 'valor'],
            'info' => ['informação', 'saber', 'como', 'taxa', 'juros', 'prazo', 'condição'],
            'urgent' => ['urgente', 'rápido', 'hoje', 'semana', 'agora', 'pressa'],
            'interested' => ['sim', 'quero', 'gostaria', 'topado', 'interessado', 'bora'],
            'not_interested' => ['não', 'nao', 'nunca', 'desculpa', 'agora não', 'depois'],
            'contact' => ['fone', 'telefone', 'ligação', 'chamada', 'whatsapp'],
            'documentation' => ['documento', 'comprovante', 'comprovação', 'precisa', 'exigência']
        ];
        
        $detectedIntents = [];
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $detectedIntents[] = $intent;
                    break;
                }
            }
        }
        
        return $detectedIntents[0] ?? 'general';
    }
    
    /**
     * Gera resposta inteligente
     */
    private function generateResponse($message, $intent, $lead) {
        $responses = [
            'credit' => [
                'Ótimo! Temos linhas de crédito personalizadas. Com que rapidez você precisa? Muito urgente, urgente ou normal?',
                'Excelente! Nossa taxa começa em 0.99% ao mês. Qual seria o valor aproximado?',
                'Perfeito! Oferecemos crédito de R$ 1 mil até R$ 5 milhões. Quanto você precisa?'
            ],
            'info' => [
                'Ótima pergunta! Nossas taxas variam de 0.99% a 2.5% a.m. dependendo do seu perfil. Posso saber mais sobre sua necessidade?',
                'Os prazos podem ir de 1 até 60 meses. Qual seria o ideal para você?',
                'Requisitos: Empresa constituída há 6+ meses, CNPJ ativo e sem restrições. Você atende?'
            ],
            'urgent' => [
                'Entendi! Urgente. Qual valor você precisa? Podemos processar em 24-48 horas.',
                'Para casos urgentes, temos processo acelerado. Qual a sua necessidade exata?',
                'Com urgência, recomendo falar direto com nosso consultor. Posso passar seus dados?'
            ],
            'interested' => [
                'Maravilha! Qual é a sua situação? (Necessidade de crédito, refinanciamento, etc)',
                'Ótimo! Vou conectar você com nosso consultor especialista.',
                'Perfeito! Vamos agendar uma conversa? Qual sua disponibilidade?'
            ],
            'not_interested' => [
                'Sem problema! Se mudar de ideia, estamos por aqui. Tudo bem?',
                'Ok! Pode ser que no futuro precise. Tudo de bom!',
                'Sem pressa! Nossa porta está sempre aberta. Boa sorte!'
            ],
            'contact' => [
                'Claro! Qual seu WhatsApp? Vou conectar direto com o consultor.',
                'Perfeito! Qual o seu melhor telefone para contato?',
                'Blz! Me passe seu WhatsApp que já entramos em contato.'
            ],
            'documentation' => [
                'Simples! Você vai precisar de: CNPJ, últimos 3 balancetes, DRE e RG do sócio.',
                'Documentação padrão: Contrato social, últimas 2 declarações de imposto de renda, comprovante de residência.',
                'A gente verifica com você antes! Geralmente é rápido e simples. Quer começar?'
            ],
            'general' => [
                'Entendi! Você está procurando soluções de crédito? Posso ajudar!',
                'Certo! Para melhor ajudá-lo, qual sua principal necessidade?',
                'Tá bem! Conte-me mais sobre sua situação para que eu possa encontrar a melhor solução.'
            ]
        ];
        
        $responseArray = $responses[$intent] ?? $responses['general'];
        $selectedResponse = $responseArray[array_rand($responseArray)];
        
        return [
            'text' => $selectedResponse,
            'sentiment' => $this->analyzeSentiment($message)
        ];
    }
    
    /**
     * Analisa sentimento
     */
    private function analyzeSentiment($message) {
        $message = strtolower($message);
        
        $positive = ['sim', 'ótimo', 'legal', 'maravilha', 'perfeito', 'interesse', 'gostei', 'bora'];
        $negative = ['não', 'nao', 'nunca', 'chato', 'ruim', 'problema', 'dúvida'];
        
        $posCount = 0;
        $negCount = 0;
        
        foreach ($positive as $word) {
            if (strpos($message, $word) !== false) $posCount++;
        }
        
        foreach ($negative as $word) {
            if (strpos($message, $word) !== false) $negCount++;
        }
        
        if ($posCount > $negCount) return 'positive';
        if ($negCount > $posCount) return 'negative';
        return 'neutral';
    }
    
    /**
     * Obtém próxima pergunta qualificadora
     */
    private function getNextQualifierQuestion($leadId) {
        try {
            // Verificar quais perguntas já foram feitas
            $sql = "SELECT answered_questions FROM chat_sessions 
                    WHERE lead_id = :lead_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['lead_id' => $leadId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $answered = [];
            if ($session && $session['answered_questions']) {
                $answered = json_decode($session['answered_questions'], true) ?? [];
            }
            
            // Encontrar próxima
            foreach ($this->qualifierQuestions as $q) {
                if (!in_array($q['id'], $answered)) {
                    return $q;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Salva resposta qualificadora
     */
    public function saveQualifierAnswer($leadId, $questionId, $answer) {
        try {
            $sql = "SELECT answered_questions FROM chat_sessions 
                    WHERE lead_id = :lead_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['lead_id' => $leadId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $answered = [];
            if ($session && $session['answered_questions']) {
                $answered = json_decode($session['answered_questions'], true) ?? [];
            }
            
            if (!in_array($questionId, $answered)) {
                $answered[] = $questionId;
                
                $sql = "UPDATE chat_sessions SET answered_questions = :answers 
                        WHERE lead_id = :lead_id ORDER BY created_at DESC LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'answers' => json_encode($answered),
                    'lead_id' => $leadId
                ]);
                
                // Salvar resposta específica
                $sql = "INSERT INTO lead_qualifier_answers 
                        (lead_id, question_id, answer) VALUES (:lead_id, :question_id, :answer)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'lead_id' => $leadId,
                    'question_id' => $questionId,
                    'answer' => $answer
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro salvar resposta: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza score do lead
     */
    private function updateLeadScore($leadId) {
        try {
            // Contar mensagens
            $sql = "SELECT COUNT(*) as msg_count FROM chat_messages WHERE lead_id = :lead_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['lead_id' => $leadId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $messageCount = $result['msg_count'] ?? 0;
            $scoreBoost = min($messageCount * 2, 20); // Máximo 20 pontos por engajamento
            
            $sql = "UPDATE leads SET 
                    engagement_count = COALESCE(engagement_count, 0) + 1,
                    engagement_last_at = NOW(),
                    lead_score = COALESCE(lead_score, 0) + :boost
                    WHERE id = :lead_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'boost' => $scoreBoost,
                'lead_id' => $leadId
            ]);
            
        } catch (Exception $e) {
            error_log("Erro atualizar score: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém ou cria lead
     */
    private function getOrCreateLead($sessionId, $leadId = null) {
        try {
            if ($leadId) {
                $sql = "SELECT * FROM leads WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $leadId]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lead) return $lead;
            }
            
            // Procurar por session
            $sql = "SELECT * FROM chat_sessions WHERE session_id = :session_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                return $this->getLead($session['lead_id']);
            }
            
            // Criar novo lead
            $sql = "INSERT INTO leads (source, status, created_at) 
                    VALUES ('chat', 'novo', NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $newLeadId = $this->db->lastInsertId();
            
            // Criar session
            $sql = "INSERT INTO chat_sessions (lead_id, session_id, created_at) 
                    VALUES (:lead_id, :session_id, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'lead_id' => $newLeadId,
                'session_id' => $sessionId
            ]);
            
            return $this->getLead($newLeadId);
            
        } catch (Exception $e) {
            throw new Exception("Erro criar lead: " . $e->getMessage());
        }
    }
    
    /**
     * Registra mensagem
     */
    private function recordMessage($leadId, $message, $direction, $sessionId) {
        try {
            $sql = "INSERT INTO chat_messages 
                    (lead_id, message, direction, session_id, created_at) 
                    VALUES (:lead_id, :message, :direction, :session_id, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'lead_id' => $leadId,
                'message' => $message,
                'direction' => $direction,
                'session_id' => $sessionId
            ]);
        } catch (Exception $e) {
            error_log("Erro registrar mensagem: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém lead
     */
    private function getLead($leadId) {
        $sql = "SELECT * FROM leads WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $leadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém histórico de chat
     */
    public function getChatHistory($leadId, $limit = 50) {
        $sql = "SELECT * FROM chat_messages 
                WHERE lead_id = :lead_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lead_id', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

// API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['action'])) {
            throw new Exception("Ação não especificada");
        }
        
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $chatBot = new ChatBot($db);
        
        if ($data['action'] === 'process_message') {
            $message = $data['message'] ?? '';
            $sessionId = $data['session_id'] ?? '';
            $leadId = $data['lead_id'] ?? null;
            
            if (!$message || !$sessionId) {
                throw new Exception("Mensagem e session_id são obrigatórios");
            }
            
            $result = $chatBot->processMessage($message, $sessionId, $leadId);
            
        } elseif ($data['action'] === 'get_history') {
            $leadId = intval($data['lead_id'] ?? 0);
            if (!$leadId) {
                throw new Exception("lead_id obrigatório");
            }
            
            $result = [
                'success' => true,
                'messages' => $chatBot->getChatHistory($leadId)
            ];
            
        } else {
            throw new Exception("Ação inválida: {$data['action']}");
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
