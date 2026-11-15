<?php
/**
 * AI Chatbot com NLP
 * Fase 3: Communication & Automation
 * 
 * Chatbot inteligente para qualificaÃ§Ã£o automÃ¡tica
 * - NLP para anÃ¡lise de textos
 * - ExtraÃ§Ã£o de dados de conversa
 * - Scoring em tempo real
 * - Roteamento inteligente
 * 
 * @author CONFINTER
 * @version 1.0
 */

class AIChatbot {
    
    private $db;
    private $knowledgeBase = [];
    private $entityPatterns = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->initializeKnowledgeBase();
        $this->initializeEntityPatterns();
    }
    
    /**
     * Inicializa base de conhecimento
     */
    private function initializeKnowledgeBase() {
        $this->knowledgeBase = [
            // Produtos
            'products' => [
                'capital_giro' => [
                    'keywords' => ['capital giro', 'fluxo caixa', 'giro negÃ³cio', 'working capital'],
                    'description' => 'CrÃ©dito para capital de giro',
                    'min_amount' => 5000,
                    'max_amount' => 500000,
                    'min_rate' => 0.99,
                    'max_rate' => 2.5
                ],
                'equipamento' => [
                    'keywords' => ['equipamento', 'mÃ¡quina', 'maquinÃ¡rio', 'aquisiÃ§Ã£o', 'compra'],
                    'description' => 'Financiamento de equipamento',
                    'min_amount' => 20000,
                    'max_amount' => 5000000,
                    'min_rate' => 0.89,
                    'max_rate' => 1.8
                ],
                'expansao' => [
                    'keywords' => ['expansÃ£o', 'crescimento', 'investimento', 'desenvolvimento'],
                    'description' => 'CrÃ©dito para expansÃ£o',
                    'min_amount' => 50000,
                    'max_amount' => 2000000,
                    'min_rate' => 1.2,
                    'max_rate' => 2.2
                ],
                'refinanciamento' => [
                    'keywords' => ['refinanciar', 'refinanciamento', 'renegociar', 'dÃ­vida', 'reduzir juros'],
                    'description' => 'Refinanciamento de dÃ­vidas',
                    'min_amount' => 10000,
                    'max_amount' => 3000000,
                    'min_rate' => 0.99,
                    'max_rate' => 2.0
                ]
            ],
            // Segmentos
            'segments' => [
                'comercio' => ['comÃ©rcio', 'varejo', 'loja', 'lojista', 'vendas'],
                'industria' => ['indÃºstria', 'industrial', 'fÃ¡brica', 'manufatura', 'produÃ§Ã£o'],
                'servicos' => ['serviÃ§o', 'consultoria', 'agÃªncia', 'escritÃ³rio', 'profissional'],
                'agro' => ['agronegÃ³cio', 'agro', 'propriedade', 'fazenda', 'agricultura'],
                'tech' => ['tecnologia', 'software', 'startup', 'desenvolvimento', 'ti']
            ],
            // Tamanhos
            'sizes' => [
                'pequena' => ['pequena', 'microempresa', '1 a 10', '1-10', 'micro'],
                'media' => ['mÃ©dia', 'mÃ©dio', '11 a 50', '11-50', 'pme'],
                'grande' => ['grande', '51 a 200', '51-200'],
                'muito_grande' => ['muito grande', '200', '500', '1000', 'multinacional', 'empresa grande']
            ]
        ];
    }
    
    /**
     * Inicializa padrÃµes de entidade
     */
    private function initializeEntityPatterns() {
        $this->entityPatterns = [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'phone' => '/(\(\d{2}\)\s)9\d{4}-\d{4}|\(\d{2}\)\s3\d{3}-\d{4}/',
            'company_name' => '/empresa\s+(:chamada|de|Ã©|se\s+chama)\s+([A-Za-z\s&\.]+)/i',
            'cnpj' => '/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{14}/',
            'amount' => '/(:R\$\s*)(:de\s+)(:atÃ©\s+)(:uns\s+)(\d{1,3}(:\.\d{3})*(:,\d{2}))\s*(:mil|k)(:\s+reais)/i',
            'date' => '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/i'
        ];
    }
    
    /**
     * Processa conversa com IA
     */
    public function processConversation($message, $leadId, $context = []) {
        try {
            // AnÃ¡lise de intenÃ§Ã£o
            $intent = $this->classifyIntent($message);
            
            // ExtraÃ§Ã£o de entidades
            $entities = $this->extractEntities($message);
            
            // AnÃ¡lise de sentimento
            $sentiment = $this->analyzeSentiment($message);
            
            // Atualizar perfil de lead
            $this->updateLeadProfile($leadId, $entities);
            
            // Gerar resposta contextual
            $response = $this->generateContextualResponse($intent, $entities, $sentiment, $leadId);
            
            // Calcular score
            $score = $this->calculateConversationScore($message, $intent, $entities, $sentiment);
            
            // Decidir se precisa agente humano
            $needsAgent = $this->shouldEscalateToAgent($leadId, $score);
            
            // Guardar anÃ¡lise
            $this->saveConversationAnalysis($leadId, [
                'message' => $message,
                'intent' => $intent,
                'entities' => $entities,
                'sentiment' => $sentiment,
                'score' => $score,
                'needs_agent' => $needsAgent
            ]);
            
            return [
                'success' => true,
                'response' => $response,
                'intent' => $intent,
                'entities' => $entities,
                'sentiment' => $sentiment,
                'score' => $score,
                'needs_agent' => $needsAgent
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Classifica intenÃ§Ã£o
     */
    private function classifyIntent($message) {
        $message = strtolower($message);
        $scores = [];
        
        // IntenÃ§Ãµes e suas palavras-chave
        $intentKeywords = [
            'product_inquiry' => ['taxa', 'juros', 'valor', 'prazo', 'condiÃ§Ã£o', 'valor mÃ­nimo', 'mÃ¡ximo'],
            'credit_request' => ['quero', 'preciso', 'solicito', 'gostaria', 'preciso de', 'preciso de crÃ©dito'],
            'company_info' => ['empresa', 'negÃ³cio', 'segmento', 'ramo', 'fÃ¡brica', 'loja'],
            'financial_situation' => ['faturamento', 'lucro', 'dÃ­vida', 'financiamento', 'fluxo'],
            'urgency' => ['urgente', 'rÃ¡pido', 'hoje', 'semana', 'agora', 'pressa', 'demorado'],
            'contact' => ['ligar', 'contato', 'encontro', 'whatsapp', 'telefone', 'agendamento'],
            'document' => ['documento', 'comprovante', 'comprovaÃ§Ã£o', 'requisito', 'precisa'],
            'objection' => ['caro', 'nÃ£o quero', 'nÃ£o tenho tempo', 'nÃ£o tenho certeza', 'dÃºvida'],
            'positive' => ['Ã³timo', 'maravilha', 'perfeito', 'topado', 'bora', 'vamo'],
            'negative' => ['nÃ£o', 'nÃ£o quero', 'nÃ£o preciso', 'nÃ£o tenho', 'nÃ£o gostei']
        ];
        
        foreach ($intentKeywords as $intent => $keywords) {
            $matchCount = 0;
            foreach ($keywords as $keyword) {
                if (strpos($message, $keyword) !== false) {
                    $matchCount++;
                }
            }
            if ($matchCount > 0) {
                $scores[$intent] = $matchCount;
            }
        }
        
        if (empty($scores)) {
            return 'general';
        }
        
        arsort($scores);
        return array_key_first($scores);
    }
    
    /**
     * Extrai entidades
     */
    private function extractEntities($message) {
        $entities = [
            'emails' => [],
            'phones' => [],
            'amounts' => [],
            'products' => [],
            'segments' => [],
            'sizes' => [],
            'dates' => []
        ];
        
        // Email
        if (preg_match_all($this->entityPatterns['email'], $message, $matches)) {
            $entities['emails'] = $matches[0];
        }
        
        // Telefone
        if (preg_match_all($this->entityPatterns['phone'], $message, $matches)) {
            $entities['phones'] = $matches[0];
        }
        
        // Valores
        if (preg_match_all($this->entityPatterns['amount'], $message, $matches)) {
            $entities['amounts'] = array_map(function($val) {
                return str_replace(['.', ','], '', $val);
            }, $matches[1]);
        }
        
        // Produtos
        foreach ($this->knowledgeBase['products'] as $prod => $data) {
            foreach ($data['keywords'] as $kw) {
                if (strpos(strtolower($message), strtolower($kw)) !== false) {
                    $entities['products'][] = $prod;
                }
            }
        }
        
        // Segmentos
        foreach ($this->knowledgeBase['segments'] as $seg => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos(strtolower($message), strtolower($kw)) !== false) {
                    $entities['segments'][] = $seg;
                }
            }
        }
        
        // Tamanhos
        foreach ($this->knowledgeBase['sizes'] as $size => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos(strtolower($message), strtolower($kw)) !== false) {
                    $entities['sizes'][] = $size;
                }
            }
        }
        
        return array_filter($entities);
    }
    
    /**
     * Analisa sentimento
     */
    private function analyzeSentiment($message) {
        $message = strtolower($message);
        
        $positiveWords = ['Ã³timo', 'maravilha', 'perfeito', 'excelente', 'legal', 'bom', 'topado', 'interessado', 'gostei', 'adorei'];
        $negativeWords = ['ruim', 'pÃ©ssimo', 'horrÃ­vel', 'chato', 'problema', 'desculpa', 'nÃ£o quero', 'nÃ£o tenho', 'nunca'];
        
        $posCount = 0;
        $negCount = 0;
        
        foreach ($positiveWords as $word) {
            $posCount += substr_count($message, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negCount += substr_count($message, $word);
        }
        
        if ($posCount > $negCount && $posCount > 0) {
            return 'positive';
        } elseif ($negCount > $posCount && $negCount > 0) {
            return 'negative';
        }
        
        return 'neutral';
    }
    
    /**
     * Gera resposta contextual
     */
    private function generateContextualResponse($intent, $entities, $sentiment, $leadId) {
        $responses = [
            'product_inquiry' => [
                'Nossas taxas variam de 0.99% a 2.5% ao mÃªs conforme o produto. ' .
                'O que vocÃª estÃ¡ procurando especificamente',
                'Temos vÃ¡rias linhas de crÃ©dito. VocÃª estÃ¡ buscando capital de giro, ' .
                'financiamento ou refinanciamento'
            ],
            'credit_request' => [
                'Ã“timo! Vou te conectar com um consultor especialista. ' .
                'Qual seu melhor contato (email ou WhatsApp)',
                'Perfeito! Temos soluÃ§Ãµes personalizadas. Qual o valor aproximado que vocÃª precisa'
            ],
            'company_info' => [
                'Entendi seu ramo. Que bacana! Como posso ajudar sua ' . 
                ($entities['segments'][0] ?? 'empresa') . '',
                'Interessante! Com quantos funcionÃ¡rios vocÃª conta'
            ],
            'urgency' => [
                'Para casos urgentes, temos processo acelerado. ' .
                'Qual valor vocÃª precisa e qual o prazo',
                'Com urgÃªncia, recomendo agendar uma conversa com nosso consultor!'
            ],
            'contact' => [
                'Claro! Qual seu WhatsApp ou email para contato',
                'Blz! Vou passar os dados para nosso time te ligar!'
            ],
            'general' => [
                'Entendi! Para melhor ajudÃ¡-lo, qual sua principal necessidade',
                'Certo! VocÃª estÃ¡ procurando soluÃ§Ãµes de crÃ©dito para sua empresa'
            ]
        ];
        
        $intentResponse = $responses[$intent] ?? $responses['general'];
        return $intentResponse[array_rand($intentResponse)];
    }
    
    /**
     * Calcula score da conversa
     */
    private function calculateConversationScore($message, $intent, $entities, $sentiment) {
        $score = 0;
        
        // Base por intenÃ§Ã£o
        $intentScores = [
            'credit_request' => 25,
            'product_inquiry' => 15,
            'company_info' => 10,
            'contact' => 20,
            'urgency' => 15,
            'positive' => 20,
            'financial_situation' => 12
        ];
        
        $score += $intentScores[$intent] ?? 5;
        
        // Bonus por entidades
        if (!empty($entities['amounts'])) $score += 10;
        if (!empty($entities['products'])) $score += 8;
        if (!empty($entities['segments'])) $score += 5;
        if (!empty($entities['phones']) || !empty($entities['emails'])) $score += 15;
        
        // Bonus por sentimento
        if ($sentiment === 'positive') $score += 10;
        if ($sentiment === 'negative') $score -= 5;
        
        // Bonus por comprimento (mensagem bem elaborada)
        $wordCount = str_word_count($message);
        if ($wordCount > 15) $score += 5;
        
        return min($score, 100);
    }
    
    /**
     * Verifica se precisa de agente
     */
    private function shouldEscalateToAgent($leadId, $score) {
        // CritÃ©rios para escalaÃ§Ã£o
        return $score >= 60; // Score alto = lead qualificado pronto para agente
    }
    
    /**
     * Atualiza perfil de lead
     */
    private function updateLeadProfile($leadId, $entities) {
        try {
            $updates = [];
            
            if (!empty($entities['segments'])) {
                $updates['segment'] = $entities['segments'][0];
            }
            if (!empty($entities['sizes'])) {
                $updates['company_size'] = $entities['sizes'][0];
            }
            if (!empty($entities['products'])) {
                $updates['product_interested'] = implode(',', $entities['products']);
            }
            if (!empty($entities['amounts'])) {
                $updates['amount_needed'] = $entities['amounts'][0];
            }
            if (!empty($entities['phones'])) {
                $updates['telefone'] = $entities['phones'][0];
            }
            if (!empty($entities['emails'])) {
                $updates['email'] = $entities['emails'][0];
            }
            
            if (!empty($updates)) {
                $setClause = implode(', ', array_map(function($k) {
                    return "$k = :$k";
                }, array_keys($updates)));
                
                $sql = "UPDATE leads SET $setClause, updated_at = NOW() WHERE id = :lead_id";
                $stmt = $this->db->prepare($sql);
                $updates['lead_id'] = $leadId;
                $stmt->execute($updates);
            }
            
        } catch (Exception $e) {
            error_log("Erro atualizar perfil: " . $e->getMessage());
        }
    }
    
    /**
     * Salva anÃ¡lise de conversa
     */
    private function saveConversationAnalysis($leadId, $analysis) {
        try {
            $sql = "INSERT INTO ai_conversation_analysis 
                    (lead_id, message, intent, entities, sentiment, score, needs_agent, created_at) 
                    VALUES (:lead_id, :message, :intent, :entities, :sentiment, :score, :needs_agent, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'lead_id' => $leadId,
                'message' => $analysis['message'],
                'intent' => $analysis['intent'],
                'entities' => json_encode($analysis['entities']),
                'sentiment' => $analysis['sentiment'],
                'score' => $analysis['score'],
                'needs_agent' => $analysis['needs_agent'] ? 1 : 0
            ]);
            
        } catch (Exception $e) {
            error_log("Erro salvar anÃ¡lise: " . $e->getMessage());
        }
    }
    
    /**
     * ObtÃ©m anÃ¡lise completa de lead
     */
    public function getLeadAnalysis($leadId) {
        try {
            $sql = "SELECT * FROM ai_conversation_analysis 
                    WHERE lead_id = :lead_id 
                    ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['lead_id' => $leadId]);
            $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular aggregates
            $totalMessages = count($analyses);
            $avgScore = $totalMessages > 0 ? array_sum(array_column($analyses, 'score')) / $totalMessages : 0;
            $positiveCount = count(array_filter($analyses, fn($a) => $a['sentiment'] === 'positive'));
            
            return [
                'total_messages' => $totalMessages,
                'avg_score' => round($avgScore, 2),
                'positive_sentiment_pct' => $totalMessages > 0 ? round(($positiveCount / $totalMessages) * 100, 1) : 0,
                'analyses' => $analyses
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Recomenda prÃ³ximo passo
     */
    public function recommendNextStep($leadId) {
        try {
            $analysis = $this->getLeadAnalysis($leadId);
            
            if ($analysis['avg_score'] >= 80) {
                return [
                    'action' => 'escalate_to_sales',
                    'reason' => 'Lead muito qualificado',
                    'priority' => 'high'
                ];
            } elseif ($analysis['avg_score'] >= 60) {
                return [
                    'action' => 'schedule_meeting',
                    'reason' => 'Lead qualificado',
                    'priority' => 'medium'
                ];
            } elseif ($analysis['positive_sentiment_pct'] >= 70) {
                return [
                    'action' => 'send_proposal',
                    'reason' => 'Sentimento positivo',
                    'priority' => 'medium'
                ];
            } else {
                return [
                    'action' => 'nurture_campaign',
                    'reason' => 'Lead em desenvolvimento',
                    'priority' => 'low'
                ];
            }
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['action'])) {
            throw new Exception("AÃ§Ã£o nÃ£o especificada");
        }
        
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $aiBot = new AIChatbot($db);
        
        if ($data['action'] === 'process_conversation') {
            $message = $data['message'] ?? '';
            $leadId = intval($data['lead_id'] ?? 0);
            
            if (!$message || !$leadId) {
                throw new Exception("Mensagem e lead_id sÃ£o obrigatÃ³rios");
            }
            
            $result = $aiBot->processConversation($message, $leadId);
            
        } elseif ($data['action'] === 'get_analysis') {
            $leadId = intval($data['lead_id'] ?? 0);
            if (!$leadId) {
                throw new Exception("lead_id obrigatÃ³rio");
            }
            
            $result = [
                'success' => true,
                'analysis' => $aiBot->getLeadAnalysis($leadId)
            ];
            
        } elseif ($data['action'] === 'recommend_next_step') {
            $leadId = intval($data['lead_id'] ?? 0);
            if (!$leadId) {
                throw new Exception("lead_id obrigatÃ³rio");
            }
            
            $result = [
                'success' => true,
                'recommendation' => $aiBot->recommendNextStep($leadId)
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
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>
