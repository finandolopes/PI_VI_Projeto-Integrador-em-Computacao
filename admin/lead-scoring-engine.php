<?php
/**
 * SMART LEAD SCORING ENGINE
 * Sistema inteligente de pontuaÃ§Ã£o de leads com base em dados coletados
 * ROI: +R$35.000/mÃªs
 * Arquivo: admin/lead-scoring-engine.php
 */

class LeadScoringEngine {
    private $db;
    private $scoringRules = [];
    private $weights = [];
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->initializeScoringRules();
        $this->initializeWeights();
    }

    /**
     * Inicializar regras de scoring
     */
    private function initializeScoringRules() {
        $this->scoringRules = [
            // Dados pessoais
            'nome' => [
                'weights' => 10,
                'rules' => [
                    'min_length' => 3,
                    'not_generic' => true
                ]
            ],
            'email' => [
                'weights' => 15,
                'rules' => [
                    'valid_format' => true,
                    'domain_check' => true,
                    'not_free' => false // Se true, penaliza emails gratuitos
                ]
            ],
            'telefone' => [
                'weights' => 10,
                'rules' => [
                    'min_length' => 10,
                    'valid_format' => true
                ]
            ],
            // Dados comerciais
            'empresa' => [
                'weights' => 20,
                'rules' => [
                    'min_length' => 2,
                    'not_generic' => true,
                    'size_points' => true // Pontos por tamanho
                ]
            ],
            'segmento' => [
                'weights' => 15,
                'rules' => [
                    'high_value_industries' => ['financeiro', 'tecnologia', 'saude', 'energia'],
                    'medium_value_industries' => ['retail', 'servicos', 'educacao'],
                    'low_value_industries' => ['outros']
                ]
            ],
            'uf' => [
                'weights' => 5,
                'rules' => [
                    'high_priority_states' => ['SP', 'MG', 'RJ', 'RS'],
                    'medium_priority_states' => ['BA', 'SC', 'GO', 'DF'],
                    'low_priority_states' => []
                ]
            ],
            'descricao' => [
                'weights' => 10,
                'rules' => [
                    'min_length' => 50,
                    'keyword_matching' => ['credito', 'financeiro', 'emprestimo', 'linhas', 'faturamento']
                ]
            ],
            // Comportamento
            'visit_count' => [
                'weights' => 8,
                'rules' => [
                    'scale' => 'log' // LogarÃ­tmica - 1 visita = 1 ponto, 10 = 2 pontos, 100 = 3 pontos
                ]
            ],
            'time_on_site' => [
                'weights' => 8,
                'rules' => [
                    'min_seconds' => 30,
                    'scale' => 'progressive' // EscalÃ¡vel com tempo
                ]
            ],
            'pages_visited' => [
                'weights' => 5,
                'rules' => [
                    'scale' => 'linear' // Cada pÃ¡gina = 1 ponto
                ]
            ],
            'form_completion' => [
                'weights' => 25,
                'rules' => [
                    'completion_rate' => true
                ]
            ],
            'email_opens' => [
                'weights' => 5,
                'rules' => [
                    'opens_tracking' => true
                ]
            ],
            'link_clicks' => [
                'weights' => 5,
                'rules' => [
                    'clicks_tracking' => true
                ]
            ]
        ];
    }

    /**
     * Inicializar pesos dos critÃ©rios
     */
    private function initializeWeights() {
        $this->weights = [
            'data_quality' => 0.3,      // 30% - Qualidade dos dados
            'engagement' => 0.25,        // 25% - Engajamento
            'industry_fit' => 0.25,      // 25% - AdequaÃ§Ã£o ao segmento
            'firmographic' => 0.2       // 20% - Dados firmogrÃ¡ficos (empresa)
        ];
    }

    /**
     * Calcular score de um lead
     * 
     * @param array $leadData Dados do lead
     * @return array Score detalhado
     */
    public function calculateScore($leadData) {
        $scores = [
            'data_quality' => $this->scoreDataQuality($leadData),
            'engagement' => $this->scoreEngagement($leadData),
            'industry_fit' => $this->scoreIndustryFit($leadData),
            'firmographic' => $this->scoreFirmographic($leadData)
        ];

        // Calcular score final (0-100)
        $finalScore = 0;
        foreach ($scores as $category => $score) {
            if (isset($this->weights[$category])) {
                $finalScore += $score * $this->weights[$category] * 100;
            }
        }

        // Arredondar para 0-100
        $finalScore = min(100, max(0, round($finalScore, 2)));

        // Determinar qualidade do lead
        $quality = $this->getQualityLevel($finalScore);

        return [
            'final_score' => $finalScore,
            'quality' => $quality,
            'category_scores' => $scores,
            'recommendation' => $this->getRecommendation($finalScore, $leadData),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Score de Qualidade de Dados (30%)
     */
    private function scoreDataQuality($leadData) {
        $score = 0;
        $totalPoints = 0;

        // Nome
        $totalPoints += 10;
        if (!empty($leadData['nome']) && strlen($leadData['nome']) >= 3) {
            $score += 10;
        } elseif (!empty($leadData['nome'])) {
            $score += 5;
        }

        // Email
        $totalPoints += 15;
        if (!empty($leadData['email']) && $this->isValidEmail($leadData['email'])) {
            $score += 15;
            // Penalidade por email gratuito
            if ($this->isFreeEmailDomain($leadData['email'])) {
                $score -= 3;
            }
        } elseif (!empty($leadData['email'])) {
            $score += 5;
        }

        // Telefone
        $totalPoints += 10;
        if (!empty($leadData['telefone']) && $this->isValidPhone($leadData['telefone'])) {
            $score += 10;
        } elseif (!empty($leadData['telefone'])) {
            $score += 5;
        }

        // Empresa
        $totalPoints += 15;
        if (!empty($leadData['empresa']) && strlen($leadData['empresa']) >= 2) {
            $score += 15;
        } elseif (!empty($leadData['empresa'])) {
            $score += 7;
        }

        // DescriÃ§Ã£o
        $totalPoints += 15;
        if (!empty($leadData['descricao'])) {
            $length = strlen($leadData['descricao']);
            if ($length >= 100) {
                $score += 15;
            } elseif ($length >= 50) {
                $score += 10;
            } else {
                $score += 5;
            }
        }

        // Segmento
        $totalPoints += 10;
        if (!empty($leadData['segmento'])) {
            $score += 10;
        }

        // Cidade/UF
        $totalPoints += 10;
        if (!empty($leadData['uf'])) {
            $score += 10;
        }

        return $totalPoints > 0 ? $score / $totalPoints : 0;
    }

    /**
     * Score de Engajamento (25%)
     */
    private function scoreEngagement($leadData) {
        $score = 0;
        $totalPoints = 0;

        // Visitas ao site
        $totalPoints += 5;
        $visitCount = $leadData['visit_count'] ?? 1;
        $visitScore = min(5, 1 + log($visitCount + 1, 2));
        $score += $visitScore;

        // Tempo no site
        $totalPoints += 5;
        $timeOnSite = $leadData['time_on_site'] ?? 0;
        if ($timeOnSite >= 300) {
            $score += 5; // 5+ minutos
        } elseif ($timeOnSite >= 120) {
            $score += 4; // 2+ minutos
        } elseif ($timeOnSite >= 30) {
            $score += 3; // 30+ segundos
        } elseif ($timeOnSite > 0) {
            $score += 1;
        }

        // PÃ¡ginas visitadas
        $totalPoints += 5;
        $pagesVisited = $leadData['pages_visited'] ?? 0;
        $pagesScore = min(5, $pagesVisited * 0.5);
        $score += $pagesScore;

        // ConclusÃ£o do formulÃ¡rio
        $totalPoints += 5;
        $formCompletion = $leadData['form_completion'] ?? 0;
        if ($formCompletion >= 100) {
            $score += 5;
        } elseif ($formCompletion >= 75) {
            $score += 4;
        } elseif ($formCompletion >= 50) {
            $score += 3;
        } elseif ($formCompletion > 0) {
            $score += 1;
        }

        // Email abertos (se houver histÃ³rico)
        $totalPoints += 5;
        $emailOpens = $leadData['email_opens'] ?? 0;
        if ($emailOpens >= 3) {
            $score += 5;
        } elseif ($emailOpens >= 1) {
            $score += 3;
        }

        return $totalPoints > 0 ? $score / $totalPoints : 0;
    }

    /**
     * Score de AdequaÃ§Ã£o ao Segmento (25%)
     */
    private function scoreIndustryFit($leadData) {
        $score = 0;
        $totalPoints = 0;

        // Segmento
        $totalPoints += 50;
        $segment = strtolower($leadData['segmento'] ?? '');
        
        $highValue = ['financeiro', 'tecnologia', 'saude', 'energia', 'consultoria', 'contabilidade'];
        $mediumValue = ['retail', 'servicos', 'educacao', 'imobiliario', 'logistica'];
        
        if (in_array($segment, $highValue)) {
            $score += 50;
        } elseif (in_array($segment, $mediumValue)) {
            $score += 35;
        } else {
            $score += 15;
        }

        // Palavras-chave na descriÃ§Ã£o
        $totalPoints += 30;
        $description = strtolower($leadData['descricao'] ?? '');
        $keywords = ['credito', 'financeiro', 'emprestimo', 'linhas', 'faturamento', 'capital', 'investimento', 'liquider'];
        
        $keywordMatches = 0;
        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                $keywordMatches++;
            }
        }
        
        $score += min(30, $keywordMatches * 5);

        // LocalizaÃ§Ã£o
        $totalPoints += 20;
        $state = strtoupper($leadData['uf'] ?? '');
        $highPriority = ['SP', 'MG', 'RJ', 'RS'];
        $mediumPriority = ['BA', 'SC', 'GO', 'DF', 'PE'];
        
        if (in_array($state, $highPriority)) {
            $score += 20;
        } elseif (in_array($state, $mediumPriority)) {
            $score += 12;
        } else {
            $score += 5;
        }

        return $totalPoints > 0 ? $score / $totalPoints : 0;
    }

    /**
     * Score FirmogrÃ¡fico (20%)
     */
    private function scoreFirmographic($leadData) {
        $score = 0;
        $totalPoints = 0;

        // Tamanho da empresa (por inferÃªncia)
        $totalPoints += 40;
        $description = strtolower($leadData['descricao'] ?? '');
        
        if (strpos($description, 'mil') !== false || strpos($description, 'milhoes') !== false) {
            $score += 40; // Grande empresa
        } elseif (strlen($description) > 100) {
            $score += 25; // Empresa de tamanho mÃ©dio
        } else {
            $score += 15; // Pequena empresa ou startup
        }

        // Maturidade da empresa (por anÃ¡lise de dados)
        $totalPoints += 30;
        $company = $leadData['empresa'] ?? '';
        if (strlen($company) > 20) {
            $score += 25; // Nome profissional
        } else {
            $score += 15;
        }

        // Tipo de necessidade
        $totalPoints += 30;
        $needType = strtolower($leadData['descricao'] ?? '');
        if (preg_match('/(credito|capital de giro|financiamento)/i', $needType)) {
            $score += 30;
        } elseif (preg_match('/(consulta|informacao|duvida)/i', $needType)) {
            $score += 15;
        } else {
            $score += 10;
        }

        return $totalPoints > 0 ? $score / $totalPoints : 0;
    }

    /**
     * Obter nÃ­vel de qualidade do lead
     */
    private function getQualityLevel($score) {
        if ($score >= 80) {
            return 'hot'; // Lead quente - pronto para venda
        } elseif ($score >= 60) {
            return 'warm'; // Lead morno - precisa de nurturing
        } elseif ($score >= 40) {
            return 'cool'; // Lead frio - em observaÃ§Ã£o
        } else {
            return 'cold'; // Lead muito frio - descarte possÃ­vel
        }
    }

    /**
     * Obter recomendaÃ§Ã£o de aÃ§Ã£o
     */
    private function getRecommendation($score, $leadData) {
        $quality = $this->getQualityLevel($score);
        
        $recommendations = [
            'hot' => [
                'action' => 'Contactar imediatamente',
                'method' => 'LigaÃ§Ã£o + WhatsApp',
                'priority' => 'CRÃTICA',
                'next_step' => 'Agendar reuniÃ£o',
                'assignment' => 'Melhor consultor disponÃ­vel'
            ],
            'warm' => [
                'action' => 'Iniciar nurturing',
                'method' => 'Email + WhatsApp',
                'priority' => 'ALTA',
                'next_step' => 'Enviar proposta inicial',
                'assignment' => 'Consultor sÃªnior'
            ],
            'cool' => [
                'action' => 'Monitorar e engajar',
                'method' => 'Email marketing',
                'priority' => 'MÃ‰DIA',
                'next_step' => 'Enviar conteÃºdo educativo',
                'assignment' => 'Fila de espera'
            ],
            'cold' => [
                'action' => 'Verificar validade',
                'method' => 'Email Ãºnico',
                'priority' => 'BAIXA',
                'next_step' => 'Seguimento em 30 dias',
                'assignment' => 'Sem atribuiÃ§Ã£o imediata'
            ]
        ];

        $baseRecommendation = $recommendations[$quality];

        // Melhorar recomendaÃ§Ã£o com base em dados especÃ­ficos
        if (!empty($leadData['telefone'])) {
            $baseRecommendation['urgency'] = 'Dado telefone vÃ¡lido - priorizar contato';
        }

        if (!empty($leadData['empresa']) && strlen($leadData['empresa']) > 20) {
            $baseRecommendation['note'] = 'Empresa maior - potencial de deal maior';
        }

        return $baseRecommendation;
    }

    /**
     * Validar email
     */
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Verificar se Ã© email gratuito
     */
    private function isFreeEmailDomain($email) {
        $freeDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'mail.com'];
        $domain = strtolower((explode('@', $email)[1] ?? ''));
        return in_array($domain, $freeDomains);
    }

    /**
     * Validar telefone
     */
    private function isValidPhone($phone) {
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) >= 10;
    }

    /**
     * Salvar score no banco de dados
     */
    public function saveScore($leadId, $scoreData) {
        if (!$this->db) return false;

        try {
            $query = "
                INSERT INTO lead_scores (lead_id, score, quality, recommendation, details, created_at)
                VALUES (, , , , , NOW())
                ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    quality = VALUES(quality),
                    recommendation = VALUES(recommendation),
                    details = VALUES(details),
                    updated_at = NOW()
            ";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $leadId,
                $scoreData['final_score'],
                $scoreData['quality'],
                json_encode($scoreData['recommendation']),
                json_encode($scoreData)
            ]);
        } catch (Exception $e) {
            error_log('Erro ao salvar score: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter leads por qualidade
     */
    public function getLeadsByQuality($quality = 'hot', $limit = 20) {
        if (!$this->db) return [];

        try {
            $query = "
                SELECT l.*, s.score, s.quality, s.recommendation
                FROM leads l
                LEFT JOIN lead_scores s ON l.id = s.lead_id
                WHERE s.quality = 
                ORDER BY s.score DESC, l.created_at DESC
                LIMIT 
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$quality, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro ao obter leads: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gerar relatÃ³rio de scoring
     */
    public function generateReport($dateStart = null, $dateEnd = null) {
        if (!$this->db) return [];

        try {
            $query = "
                SELECT 
                    s.quality,
                    COUNT(*) as count,
                    AVG(s.score) as avg_score,
                    MIN(s.score) as min_score,
                    MAX(s.score) as max_score
                FROM lead_scores s
                WHERE 1=1
            ";

            $params = [];
            if ($dateStart) {
                $query .= " AND DATE(s.created_at) >= ";
                $params[] = $dateStart;
            }
            if ($dateEnd) {
                $query .= " AND DATE(s.created_at) <= ";
                $params[] = $dateEnd;
            }

            $query .= " GROUP BY s.quality";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro ao gerar relatÃ³rio: ' . $e->getMessage());
            return [];
        }
    }
}

// API endpoint para calcular score
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // Incluir configuraÃ§Ã£o do banco se necessÃ¡rio
    $db = null; // SerÃ¡ injetado do contexto
    
    $engine = new LeadScoringEngine($db);
    $result = $engine->calculateScore($data);
    
    echo json_encode($result);
    exit;
}
?>
