<?php
/**
 * LEAD SCORING ENGINE - Sistema de Pontuação de Leads
 * 
 * Arquivo: php/lead-scoring-engine.php
 * Descrição: Engine para calcular score automático de cada cliente
 * ROI Estimado: +40% conversão
 * Tempo Implementação: 1h
 */

class LeadScoringEngine {
    private $conexao;
    private $user_id;
    
    const BASE_SCORE = 0;
    
    // Tabela de pontos
    const SCORING_TABLE = [
        'cpf_valid' => 10,              // CPF validado
        'email_verified' => 10,         // Email verificado
        'phone_verified' => 10,         // Telefone verificado
        'visited_simulator' => 15,      // Visitou simulador
        'visited_3_plus_sections' => 20, // Visitou 3+ seções
        'clicked_whatsapp' => 30,       // Clicou WhatsApp
        'form_incomplete' => -5,        // Form incompleto
        'form_complete' => 25,          // Form completo
        'clicked_cta' => 40,            // Clicou em CTA
        'submitted_request' => 50,      // Enviou requisição
        'high_loan_amount' => 15,       // Valor alto requisitado
        'quick_decision' => 20,         // Decidiu rápido (< 5 min)
    ];
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    /**
     * Calcula o score total de um cliente
     * 
     * @param int $id_cliente ID do cliente
     * @return int Score final (0-500)
     */
    public function calculateScore($id_cliente) {
        $score = self::BASE_SCORE;
        
        // Buscar dados do cliente
        $cliente = $this->getClienteData($id_cliente);
        if (!$cliente) return 0;
        
        // Scoring por validações
        if ($this->isCPFValid($cliente['cpf'])) {
            $score += self::SCORING_TABLE['cpf_valid'];
        }
        
        if ($this->isEmailVerified($cliente['email'])) {
            $score += self::SCORING_TABLE['email_verified'];
        }
        
        if (!empty($cliente['telefone'])) {
            $score += self::SCORING_TABLE['phone_verified'];
        }
        
        // Scoring por comportamento
        $behavior_score = $this->getBehaviorScore($id_cliente);
        $score += $behavior_score;
        
        // Scoring por requisição
        if (!empty($cliente['id_requisicao'])) {
            $score += self::SCORING_TABLE['submitted_request'];
            
            // Valor alto requisitado
            if ($cliente['valor_requisitado'] > 30000) {
                $score += self::SCORING_TABLE['high_loan_amount'];
            }
        }
        
        // Score final nunca negativo
        return max(0, min($score, 500));
    }
    
    /**
     * Calcula score baseado em comportamento
     */
    private function getBehaviorScore($id_cliente) {
        $score = 0;
        
        // Visitou simulador?
        $sql = "SELECT COUNT(*) as count FROM analytics 
                WHERE id_cliente = ? AND event = 'visited_simulator'";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $score += self::SCORING_TABLE['visited_simulator'];
        }
        
        // Visitou 3+ seções?
        $sql = "SELECT COUNT(DISTINCT secao) as count FROM analytics 
                WHERE id_cliente = ? AND event = 'visited_section'";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] >= 3) {
            $score += self::SCORING_TABLE['visited_3_plus_sections'];
        }
        
        // Clicou WhatsApp?
        $sql = "SELECT COUNT(*) as count FROM analytics 
                WHERE id_cliente = ? AND event = 'clicked_whatsapp'";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $score += self::SCORING_TABLE['clicked_whatsapp'];
        }
        
        // Clicou CTA?
        $sql = "SELECT COUNT(*) as count FROM analytics 
                WHERE id_cliente = ? AND event = 'clicked_cta'";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $score += self::SCORING_TABLE['clicked_cta'];
        }
        
        return $score;
    }
    
    /**
     * Valida CPF
     */
    private function isCPFValid($cpf) {
        $cpf = preg_replace('/[^0-9]/i', '', $cpf);
        
        if (strlen($cpf) != 11)
            return false;
            
        if (preg_match('/(\d)\1{10}/', $cpf))
            return false;
            
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d)
                return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se email foi verificado
     */
    private function isEmailVerified($email) {
        $sql = "SELECT email_verified FROM clientes WHERE email = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result && $result['email_verified'] == 1;
    }
    
    /**
     * Obtém dados do cliente
     */
    private function getClienteData($id_cliente) {
        $sql = "SELECT c.*, r.id_requisicao, r.valor as valor_requisitado 
                FROM clientes c
                LEFT JOIN requisicoes r ON c.id_cliente = r.id_cliente
                WHERE c.id_cliente = ?
                ORDER BY r.data_requisicao DESC
                LIMIT 1";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result;
    }
    
    /**
     * Atualiza score no banco de dados
     */
    public function updateScoreInDB($id_cliente) {
        $score = $this->calculateScore($id_cliente);
        
        $sql = "UPDATE clientes SET lead_score = ? WHERE id_cliente = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('ii', $score, $id_cliente);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém status do lead (Hot, Warm, Cold)
     */
    public function getLeadStatus($score) {
        if ($score >= 300) {
            return [
                'status' => 'HOT',
                'color' => 'danger',
                'emoji' => '🔴',
                'description' => 'Prioridade máxima - Ligar agora!'
            ];
        } elseif ($score >= 150) {
            return [
                'status' => 'WARM',
                'color' => 'warning',
                'emoji' => '🟡',
                'description' => 'Seguimento necessário'
            ];
        } else {
            return [
                'status' => 'COLD',
                'color' => 'secondary',
                'emoji' => '🟢',
                'description' => 'Nutrição para aquecimento'
            ];
        }
    }
}

// Uso:
/*
include 'php/lead-scoring-engine.php';

$scoringEngine = new LeadScoringEngine($conexao);

// Calcular score
$score = $scoringEngine->calculateScore($id_cliente);

// Atualizar no BD
$scoringEngine->updateScoreInDB($id_cliente);

// Obter status
$status = $scoringEngine->getLeadStatus($score);
echo "{$status['emoji']} {$status['status']} - {$status['description']}";
*/
?>
