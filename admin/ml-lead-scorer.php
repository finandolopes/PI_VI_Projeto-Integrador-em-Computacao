<?php
/**
 * ML-Enhanced Lead Scoring
 * Fase 2: Lead Management & CRM
 * 
 * Machine learning model for predictive lead quality
 * Uses historical data to predict conversion probability
 * 
 * @author CONFINTER
 * @version 1.0
 */

class MLEnhancedLeadScorer {
    
    private $db;
    private $baseScorer;
    private $weights = [
        'base_score' => 0.40,           // 40% - Score base do lead
        'historical_match' => 0.25,     // 25% - Match com leads histÃ³ricos
        'engagement_pattern' => 0.20,   // 20% - PadrÃ£o de engajamento
        'conversion_probability' => 0.15 // 15% - Probabilidade de conversÃ£o
    ];
    
    public function __construct($db) {
        $this->db = $db;
        require_once __DIR__ . '/lead-scoring-engine.php';
        $this->baseScorer = new LeadScoringEngine($db, null);
    }
    
    /**
     * Calcula score ML aprimorado
     */
    public function calculateMLScore($leadId) {
        try {
            // Carregar dados do lead
            $lead = $this->getLead($leadId);
            if (!$lead) {
                throw new Exception("Lead nÃ£o encontrado");
            }
            
            // 1. Base Score
            $baseScore = $this->calculateBaseScore($lead);
            
            // 2. HistÃ³rico Match
            $historicalScore = $this->calculateHistoricalMatch($lead);
            
            // 3. PadrÃ£o de Engajamento
            $engagementScore = $this->calculateEngagementPattern($lead);
            
            // 4. Probabilidade de ConversÃ£o
            $conversionProbability = $this->predictConversionProbability($lead);
            
            // Score final ponderado
            $finalScore = 
                ($baseScore * $this->weights['base_score']) +
                ($historicalScore * $this->weights['historical_match']) +
                ($engagementScore * $this->weights['engagement_pattern']) +
                ($conversionProbability * $this->weights['conversion_probability']);
            
            // Salvar resultado
            $this->saveMLScore($leadId, [
                'base_score' => $baseScore,
                'historical_score' => $historicalScore,
                'engagement_score' => $engagementScore,
                'conversion_probability' => $conversionProbability,
                'final_score' => round($finalScore, 2),
                'calculated_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'lead_id' => $leadId,
                'base_score' => round($baseScore, 2),
                'historical_score' => round($historicalScore, 2),
                'engagement_score' => round($engagementScore, 2),
                'conversion_probability' => round($conversionProbability, 2),
                'final_score' => round($finalScore, 2),
                'classification' => $this->classifyScore(round($finalScore, 2)),
                'recommendation' => $this->getRecommendation($finalScore),
                'confidence' => $this->calculateConfidence($lead)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calcula base score usando modelo tradicional
     */
    private function calculateBaseScore($lead) {
        // Score tradicional em escala 0-100
        $dataQuality = $this->scoreDataQuality($lead);
        $engagement = $this->scoreEngagement($lead);
        $industryFit = $this->scoreIndustryFit($lead);
        
        return ($dataQuality * 0.30 + $engagement * 0.40 + $industryFit * 0.30);
    }
    
    /**
     * Encontra leads histÃ³ricos similares que converteram
     */
    private function calculateHistoricalMatch($lead) {
        $sql = "SELECT 
                    AVG(CASE WHEN l.status = 'won' THEN 100 ELSE 0 END) as conversion_rate,
                    COUNT(*) as similar_count
                FROM leads l
                WHERE l.segmento = :segmento
                    AND l.status IN ('won', 'rejeitado')
                    AND l.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND l.deleted_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['segmento' => $lead['segmento']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['similar_count'] < 5) {
            return 50; // Score neutro se poucos dados histÃ³ricos
        }
        
        // Score baseado na taxa de conversÃ£o histÃ³rica do segmento
        return min(100, $result['conversion_rate'] * 1.2);
    }
    
    /**
     * Analisa padrÃ£o de engajamento
     */
    private function calculateEngagementPattern($lead) {
        $sql = "SELECT 
                    COUNT(*) as total_interactions,
                    MAX(interaction_date) as last_interaction,
                    SUM(CASE WHEN interaction_type = 'conversion' THEN 1 ELSE 0 END) as conversions,
                    DATEDIFF(MAX(interaction_date), MIN(interaction_date)) as engagement_span
                FROM lead_interactions
                WHERE lead_id = :lead_id
                    AND interaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lead_id' => $lead['id']]);
        $interactions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $score = 0;
        
        // FrequÃªncia de interaÃ§Ã£o
        if ($interactions['total_interactions'] >= 5) {
            $score += 30;
        } elseif ($interactions['total_interactions'] >= 3) {
            $score += 20;
        } elseif ($interactions['total_interactions'] >= 1) {
            $score += 10;
        }
        
        // RecÃªncia (interaÃ§Ã£o recente)
        if ($interactions['last_interaction']) {
            $daysSinceLastInteraction = 
                (strtotime('now') - strtotime($interactions['last_interaction'])) / (3600 * 24);
            
            if ($daysSinceLastInteraction < 1) {
                $score += 30;
            } elseif ($daysSinceLastInteraction < 7) {
                $score += 20;
            } elseif ($daysSinceLastInteraction < 30) {
                $score += 10;
            }
        }
        
        // ConversÃµes em interaÃ§Ãµes
        if ($interactions['conversions'] > 0) {
            $score += 40;
        }
        
        // Span de engajamento (diversidade temporal)
        if ($interactions['engagement_span'] >= 7) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    /**
     * Prediz probabilidade de conversÃ£o usando modelo simplificado
     */
    private function predictConversionProbability($lead) {
        $probability = 50; // Base neutra
        
        // Fatores positivos
        if (!empty($lead['email']) && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            $probability += 10;
        }
        
        if (!empty($lead['telefone']) && strlen($lead['telefone']) >= 10) {
            $probability += 10;
        }
        
        if (!empty($lead['empresa']) && strlen($lead['empresa']) > 3) {
            $probability += 10;
        }
        
        if (!empty($lead['segmento'])) {
            $probability += 5;
        }
        
        // Fator de tempo (leads mais novos tendem a ser mais quentes)
        $daysOld = (strtotime('now') - strtotime($lead['created_at'])) / (3600 * 24);
        if ($daysOld < 7) {
            $probability += 10;
        } elseif ($daysOld > 60) {
            $probability -= 10;
        }
        
        // Penalidade por falta de atualizaÃ§Ã£o
        if ($lead['updated_at']) {
            $daysSinceUpdate = (strtotime('now') - strtotime($lead['updated_at'])) / (3600 * 24);
            if ($daysSinceUpdate > 30) {
                $probability -= 15;
            }
        }
        
        // BÃ´nus por descriÃ§Ã£o detalhada
        if (!empty($lead['descricao']) && strlen($lead['descricao']) > 50) {
            $probability += 10;
        }
        
        return min(100, max(0, $probability));
    }
    
    /**
     * Scores dos componentes
     */
    private function scoreDataQuality($lead) {
        $score = 0;
        $maxScore = 0;
        
        // Email
        if (!empty($lead['email'])) {
            $maxScore += 20;
            if (filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
                $score += 20;
            }
        } else {
            $maxScore += 20;
        }
        
        // Telefone
        if (!empty($lead['telefone'])) {
            $maxScore += 20;
            if (strlen($lead['telefone']) >= 10) {
                $score += 20;
            }
        } else {
            $maxScore += 20;
        }
        
        // Empresa e Nome
        if (!empty($lead['empresa']) && !empty($lead['nome'])) {
            $score += 20;
        }
        $maxScore += 20;
        
        // DescriÃ§Ã£o
        if (!empty($lead['descricao'])) {
            $maxScore += 20;
            if (strlen($lead['descricao']) > 20) {
                $score += 20;
            }
        } else {
            $maxScore += 20;
        }
        
        // Segmento
        if (!empty($lead['segmento'])) {
            $score += 20;
        }
        $maxScore += 20;
        
        return $maxScore > 0 ? ($score / $maxScore) * 100 : 0;
    }
    
    /**
     * Score de engajamento
     */
    private function scoreEngagement($lead) {
        $sql = "SELECT COUNT(*) as count FROM lead_interactions 
                WHERE lead_id = :lead_id 
                AND interaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lead_id' => $lead['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $count = $result['count'] ?? 0;
        
        if ($count >= 10) return 100;
        if ($count >= 5) return 75;
        if ($count >= 2) return 50;
        if ($count >= 1) return 25;
        return 0;
    }
    
    /**
     * Score de industry fit
     */
    private function scoreIndustryFit($lead) {
        // Segmentos de alto valor
        $highValueSegments = ['tecnologia', 'financeiro', 'saÃºde', 'varejo'];
        
        if (in_array(strtolower($lead['segmento'] ?? ''), $highValueSegments)) {
            return 80;
        }
        
        // Segmentos mÃ©dios
        if (!empty($lead['segmento'])) {
            return 60;
        }
        
        return 40;
    }
    
    /**
     * Classifica o score final
     */
    private function classifyScore($score) {
        if ($score >= 85) return 'hot';
        if ($score >= 65) return 'warm';
        if ($score >= 45) return 'cool';
        return 'cold';
    }
    
    /**
     * Gera recomendaÃ§Ã£o baseada no score
     */
    private function getRecommendation($score) {
        if ($score >= 85) {
            return 'Contactar imediatamente via chamada + WhatsApp';
        } elseif ($score >= 65) {
            return 'Enviar proposta e agendar reuniÃ£o';
        } elseif ($score >= 45) {
            return 'Iniciar nurturing com conteÃºdo educativo';
        } else {
            return 'Monitorar comportamento antes de contato';
        }
    }
    
    /**
     * Calcula confianÃ§a da prediÃ§Ã£o
     */
    private function calculateConfidence($lead) {
        $confidence = 50; // Base
        
        // Dados disponÃ­veis aumentam confianÃ§a
        $dataPoints = 0;
        if (!empty($lead['email'])) $dataPoints++;
        if (!empty($lead['telefone'])) $dataPoints++;
        if (!empty($lead['empresa'])) $dataPoints++;
        if (!empty($lead['segmento'])) $dataPoints++;
        if (!empty($lead['descricao'])) $dataPoints++;
        
        $confidence += ($dataPoints * 10);
        
        return min(100, $confidence);
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
     * Salva score ML no banco
     */
    private function saveMLScore($leadId, $scoreData) {
        $sql = "UPDATE leads 
                SET ml_score = :final_score,
                    ml_components = :components,
                    ml_calculated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'final_score' => $scoreData['final_score'],
            'components' => json_encode($scoreData),
            'id' => $leadId
        ]);
    }
    
    /**
     * Recalcula ML scores para todos os leads (batch processing)
     */
    public function batchRecalculateScores($limit = 100) {
        $sql = "SELECT id FROM leads 
                WHERE deleted_at IS NULL
                AND (ml_calculated_at IS NULL OR ml_calculated_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result = $this->calculateMLScore($row['id']);
            $results[] = [
                'lead_id' => $row['id'],
                'success' => $result['success'],
                'final_score' => $result['final_score'] ?? null
            ];
        }
        
        return [
            'processed' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * ObtÃ©m leads de alta prioridade (hot + warm)
     */
    public function getHighPriorityLeads() {
        $sql = "SELECT 
                    id,
                    nome,
                    email,
                    empresa,
                    ml_score,
                    lead_score,
                    CASE 
                        WHEN ml_score >= 85 THEN 'hot'
                        WHEN ml_score >= 65 THEN 'warm'
                        ELSE 'cold'
                    END as priority
                FROM leads
                WHERE ml_score >= 65
                    AND status != 'won'
                    AND deleted_at IS NULL
                ORDER BY ml_score DESC
                LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        
        $mlScorer = new MLEnhancedLeadScorer($db);
        
        if ($_POST['action'] === 'calculate_score') {
            $leadId = intval($_POST['lead_id'] ?? 0);
            $result = $mlScorer->calculateMLScore($leadId);
        } elseif ($_POST['action'] === 'batch_recalculate') {
            $limit = intval($_POST['limit'] ?? 100);
            $result = $mlScorer->batchRecalculateScores($limit);
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
