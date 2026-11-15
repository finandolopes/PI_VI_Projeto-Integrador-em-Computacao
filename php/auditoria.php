<?php
/**
 * ========================================
 * SISTEMA DE AUDITORIA
 * Log de todas as ações administrativas
 * ========================================
 */

class Auditoria {
    private $conexao;
    private $table = 'auditoria';
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->criarTabelaSeNaoExistir();
    }
    
    /**
     * Criar tabela de auditoria se não existir
     */
    private function criarTabelaSeNaoExistir() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id_auditoria INT PRIMARY KEY AUTO_INCREMENT,
            id_usuario INT,
            username VARCHAR(50),
            acao VARCHAR(100) NOT NULL,
            tabela VARCHAR(50),
            id_registro INT,
            dados_antes JSON,
            dados_depois JSON,
            resultado VARCHAR(20),
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY (id_usuario),
            KEY (tabela),
            KEY (acao),
            KEY (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conexao->query($sql);
    }
    
    /**
     * Registrar ação de criação
     */
    public function criar($tabela, $id_registro, $dados, $id_usuario = null) {
        return $this->registrar('CREATE', $tabela, $id_registro, null, $dados, $id_usuario);
    }
    
    /**
     * Registrar ação de atualização
     */
    public function atualizar($tabela, $id_registro, $dados_antes, $dados_depois, $id_usuario = null) {
        return $this->registrar('UPDATE', $tabela, $id_registro, $dados_antes, $dados_depois, $id_usuario);
    }
    
    /**
     * Registrar ação de exclusão
     */
    public function deletar($tabela, $id_registro, $dados, $id_usuario = null) {
        return $this->registrar('DELETE', $tabela, $id_registro, $dados, null, $id_usuario);
    }
    
    /**
     * Registrar ação de login
     */
    public function login($username, $resultado = 'sucesso', $id_usuario = null) {
        return $this->registrar('LOGIN', 'usuarios', $id_usuario, null, 
                              json_encode(['username' => $username]), $id_usuario, $resultado);
    }
    
    /**
     * Registrar ação de logout
     */
    public function logout($username, $id_usuario = null) {
        return $this->registrar('LOGOUT', 'usuarios', $id_usuario, null, 
                              json_encode(['username' => $username]), $id_usuario);
    }
    
    /**
     * Registrar ação genérica
     */
    private function registrar($acao, $tabela, $id_registro, $dados_antes, $dados_depois, 
                              $id_usuario = null, $resultado = 'sucesso') {
        if (!isset($_SESSION['user_id'])) {
            $id_usuario = null;
        } elseif ($id_usuario === null) {
            $id_usuario = $_SESSION['user_id'];
        }
        
        $username = $_SESSION['username'] ?? 'sistema';
        $ip = $this->obterIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->conexao->prepare(
            "INSERT INTO {$this->table} 
             (id_usuario, username, acao, tabela, id_registro, dados_antes, dados_depois, 
              resultado, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            error_log("Erro ao preparar auditoria: " . $this->conexao->error);
            return false;
        }
        
        $dados_antes_json = is_array($dados_antes) ? json_encode($dados_antes) : $dados_antes;
        $dados_depois_json = is_array($dados_depois) ? json_encode($dados_depois) : $dados_depois;
        
        $stmt->bind_param(
            'isssisssss',
            $id_usuario, $username, $acao, $tabela, $id_registro,
            $dados_antes_json, $dados_depois_json, $resultado, $ip, $user_agent
        );
        
        return $stmt->execute();
    }
    
    /**
     * Obter histórico de um registro
     */
    public function getHistorico($tabela, $id_registro, $limite = 50) {
        $stmt = $this->conexao->prepare(
            "SELECT * FROM {$this->table} 
             WHERE tabela = ? AND id_registro = ? 
             ORDER BY timestamp DESC 
             LIMIT ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('ssi', $tabela, $id_registro, $limite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $historico = [];
        while ($row = $result->fetch_assoc()) {
            $historico[] = $row;
        }
        
        return $historico;
    }
    
    /**
     * Obter histórico de um usuário
     */
    public function getHistoricoUsuario($id_usuario, $limite = 50) {
        $stmt = $this->conexao->prepare(
            "SELECT * FROM {$this->table} 
             WHERE id_usuario = ? 
             ORDER BY timestamp DESC 
             LIMIT ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('ii', $id_usuario, $limite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $historico = [];
        while ($row = $result->fetch_assoc()) {
            $historico[] = $row;
        }
        
        return $historico;
    }
    
    /**
     * Obter auditoria por ação
     */
    public function getAuditoriaPorAcao($acao, $limite = 50) {
        $stmt = $this->conexao->prepare(
            "SELECT * FROM {$this->table} 
             WHERE acao = ? 
             ORDER BY timestamp DESC 
             LIMIT ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('si', $acao, $limite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $auditoria = [];
        while ($row = $result->fetch_assoc()) {
            $auditoria[] = $row;
        }
        
        return $auditoria;
    }
    
    /**
     * Obter auditoria por período
     */
    public function getAuditoriaPorPeriodo($data_inicio, $data_fim, $limite = 100) {
        $stmt = $this->conexao->prepare(
            "SELECT * FROM {$this->table} 
             WHERE DATE(timestamp) BETWEEN ? AND ? 
             ORDER BY timestamp DESC 
             LIMIT ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('ssi', $data_inicio, $data_fim, $limite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $auditoria = [];
        while ($row = $result->fetch_assoc()) {
            $auditoria[] = $row;
        }
        
        return $auditoria;
    }
    
    /**
     * Obter IP do cliente
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
}

?>
