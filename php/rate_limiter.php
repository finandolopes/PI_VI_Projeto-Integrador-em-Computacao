<?php
/**
 * ========================================
 * RATE LIMITER - Sistema Global
 * Proteção contra ataque DDoS e força bruta
 * ========================================
 */

class RateLimiter {
    private $conexao;
    private $table = 'rate_limits';
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->criarTabelaSeNaoExistir();
    }
    
    /**
     * Criar tabela de rate limits se não existir
     */
    private function criarTabelaSeNaoExistir() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            chave VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            tentativas INT DEFAULT 1,
            primeiro_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY (chave, ip_address),
            KEY (ultimo_acesso)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conexao->query($sql);
    }
    
    /**
     * Verificar se cliente excedeu limite
     * 
     * @param string $chave Identificador único (ex: 'login', 'formulario_requisicao')
     * @param int $limite Número máximo de tentativas
     * @param int $intervalo Intervalo em segundos
     * @return array ['permitido' => bool, 'tentativas_restantes' => int, 'tempo_bloqueio' => int]
     */
    public function verificar($chave, $limite = 10, $intervalo = 3600) {
        $ip = $this->obterIP();
        $timestamp_limite = date('Y-m-d H:i:s', time() - $intervalo);
        
        // Limpar registros antigos
        $this->limparAntigos($intervalo);
        
        // Contar tentativas do IP neste intervalo
        $stmt = $this->conexao->prepare(
            "SELECT COUNT(*) as tentativas FROM {$this->table} 
             WHERE chave = ? AND ip_address = ? AND ultimo_acesso > ?"
        );
        
        if (!$stmt) {
            error_log("Erro ao preparar statement: " . $this->conexao->error);
            return ['permitido' => true, 'tentativas_restantes' => $limite, 'tempo_bloqueio' => 0];
        }
        
        $stmt->bind_param('sss', $chave, $ip, $timestamp_limite);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $tentativas = (int)$result['tentativas'];
        
        $permitido = $tentativas < $limite;
        $restantes = max(0, $limite - $tentativas - 1);
        $tempo_bloqueio = $permitido ? 0 : $intervalo - (time() - strtotime($timestamp_limite));
        
        // Registrar tentativa
        if ($permitido) {
            $this->registrar($chave, $ip);
        }
        
        return [
            'permitido' => $permitido,
            'tentativas_restantes' => $restantes,
            'tempo_bloqueio' => $tempo_bloqueio
        ];
    }
    
    /**
     * Registrar uma tentativa
     */
    private function registrar($chave, $ip) {
        $stmt = $this->conexao->prepare(
            "INSERT INTO {$this->table} (chave, ip_address, tentativas) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, 
             ultimo_acesso = CURRENT_TIMESTAMP"
        );
        
        if ($stmt) {
            $stmt->bind_param('ss', $chave, $ip);
            $stmt->execute();
        }
    }
    
    /**
     * Limpar registros antigos
     */
    private function limparAntigos($intervalo) {
        $timestamp = date('Y-m-d H:i:s', time() - $intervalo - 3600); // Manter 1h extra
        
        $stmt = $this->conexao->prepare(
            "DELETE FROM {$this->table} WHERE ultimo_acesso < ?"
        );
        
        if ($stmt) {
            $stmt->bind_param('s', $timestamp);
            $stmt->execute();
        }
    }
    
    /**
     * Obter IP do cliente (considerar proxies)
     */
    private function obterIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }
    
    /**
     * Limpar registros de um IP específico (após sucesso, por exemplo)
     */
    public function limparIP($chave = null) {
        $ip = $this->obterIP();
        
        if ($chave) {
            $stmt = $this->conexao->prepare(
                "DELETE FROM {$this->table} WHERE chave = ? AND ip_address = ?"
            );
            $stmt->bind_param('ss', $chave, $ip);
        } else {
            $stmt = $this->conexao->prepare(
                "DELETE FROM {$this->table} WHERE ip_address = ?"
            );
            $stmt->bind_param('s', $ip);
        }
        
        return $stmt->execute();
    }
}

?>
