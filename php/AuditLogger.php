<?php
/**
 * ========================================
 * AUDIT LOGGER - Sistema de Auditoria
 * ========================================
 * Registra todas as ações de usuários no sistema
 * Data: 07/02/2026
 */

class AuditLogger {
    private $conexao;
    private $tableName = 'audit_logs';

    const ACTION_TYPES = [
        'LOGIN' => 'Login',
        'LOGOUT' => 'Logout',
        'CREATE' => 'Criação',
        'UPDATE' => 'Atualização',
        'DELETE' => 'Deleção',
        'VIEW' => 'Visualização',
        'DOWNLOAD' => 'Download',
        'EXPORT' => 'Exportação',
        'PERMISSION_CHANGE' => 'Mudança de Permissão',
        'ROLE_CHANGE' => 'Mudança de Role',
        'BACKUP' => 'Backup',
        'RESTORE' => 'Restauração',
        'FAILED_LOGIN' => 'Login Falhado',
        'FORCE_LOGOUT' => 'Logout Forçado',
    ];

    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->initializeDatabase();
    }

    /**
     * Inicializar banco de dados
     */
    private function initializeDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id_log INT PRIMARY KEY AUTO_INCREMENT,
            action_type VARCHAR(50) NOT NULL,
            description TEXT,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            affected_table VARCHAR(100),
            affected_id INT,
            changes JSON,
            status VARCHAR(50),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_user_id (user_id),
            INDEX idx_action_type (action_type),
            INDEX idx_affected_table (affected_table),
            FOREIGN KEY (user_id) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$this->conexao->query($sql)) {
            // Tabela pode já existir, continuar mesmo assim
            error_log("Aviso ao criar tabela audit_logs: " . $this->conexao->error);
        }
    }

    /**
     * Registrar ação no log
     */
    public function log($actionType, $description = null, $userId = null, $affectedTable = null, $affectedId = null, $changes = null, $status = 'SUCCESS') {
        if (!array_key_exists($actionType, self::ACTION_TYPES)) {
            $actionType = 'VIEW'; // Valor padrão
        }

        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $changesJson = $changes ? json_encode($changes) : null;

        $stmt = $this->conexao->prepare(
            "INSERT INTO {$this->tableName} (action_type, description, user_id, ip_address, user_agent, affected_table, affected_id, changes, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("Erro ao preparar statement audit_logs: " . $this->conexao->error);
            return false;
        }

        $stmt->bind_param(
            "ssississs",
            $actionType,
            $description,
            $userId,
            $ipAddress,
            $userAgent,
            $affectedTable,
            $affectedId,
            $changesJson,
            $status
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Registrar login
     */
    public function logLogin($userId, $success = true) {
        $actionType = $success ? 'LOGIN' : 'FAILED_LOGIN';
        $description = $success ? 'Usuário fez login' : 'Tentativa de login falhou';
        $status = $success ? 'SUCCESS' : 'FAILED';
        
        return $this->log($actionType, $description, $userId, null, null, null, $status);
    }

    /**
     * Registrar logout
     */
    public function logLogout($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        return $this->log('LOGOUT', 'Usuário fez logout', $userId);
    }

    /**
     * Registrar criação
     */
    public function logCreate($table, $id, $userId = null, $data = null) {
        return $this->log('CREATE', "Novo registro criado em {$table}", $userId, $table, $id, $data);
    }

    /**
     * Registrar atualização
     */
    public function logUpdate($table, $id, $userId = null, $oldData = null, $newData = null) {
        $changes = [
            'old' => $oldData,
            'new' => $newData
        ];
        return $this->log('UPDATE', "Registro atualizado em {$table}", $userId, $table, $id, $changes);
    }

    /**
     * Registrar deleção
     */
    public function logDelete($table, $id, $userId = null, $data = null) {
        return $this->log('DELETE', "Registro deletado em {$table}", $userId, $table, $id, $data);
    }

    /**
     * Obter logs
     */
    public function getLogs($limit = 50, $offset = 0, $filters = []) {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filters['user_id'])) {
            $where .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }

        if (!empty($filters['action_type'])) {
            $where .= " AND action_type = ?";
            $params[] = $filters['action_type'];
            $types .= "s";
        }

        if (!empty($filters['affected_table'])) {
            $where .= " AND affected_table = ?";
            $params[] = $filters['affected_table'];
            $types .= "s";
        }

        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }

        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE {$where} ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        
        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            error_log("Erro ao preparar getLogs: " . $this->conexao->error);
            return [];
        }

        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $logs;
    }

    /**
     * Contar logs
     */
    public function countLogs($filters = []) {
        $where = "1=1";
        $params = [];
        $types = "";

        if (!empty($filters['user_id'])) {
            $where .= " AND user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }

        if (!empty($filters['action_type'])) {
            $where .= " AND action_type = ?";
            $params[] = $filters['action_type'];
            $types .= "s";
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->tableName} WHERE {$where}";
        
        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['total'] ?? 0;
    }

    /**
     * Obter log por ID
     */
    public function getLogById($logId) {
        $stmt = $this->conexao->prepare("SELECT * FROM {$this->tableName} WHERE id_log = ?");
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param("i", $logId);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        $stmt->close();

        return $log;
    }

    /**
     * Deletar logs antigos
     */
    public function deleteOldLogs($daysOld = 90) {
        $stmt = $this->conexao->prepare(
            "DELETE FROM {$this->tableName} WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $daysOld);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Obter estatísticas
     */
    public function getStatistics() {
        $stats = [];

        // Total de logs
        $result = $this->conexao->query("SELECT COUNT(*) as total FROM {$this->tableName}");
        $stats['total_logs'] = $result->fetch_assoc()['total'];

        // Logs por tipo de ação
        $result = $this->conexao->query("SELECT action_type, COUNT(*) as count FROM {$this->tableName} GROUP BY action_type");
        $stats['logs_by_action'] = $result->fetch_all(MYSQLI_ASSOC);

        // Usuários mais ativos
        $result = $this->conexao->query(
            "SELECT u.nome_usuario, COUNT(*) as count FROM {$this->tableName} l 
             LEFT JOIN usuarios u ON l.user_id = u.id_usuario 
             GROUP BY l.user_id ORDER BY count DESC LIMIT 10"
        );
        $stats['most_active_users'] = $result->fetch_all(MYSQLI_ASSOC);

        // Logs do últimas 7 dias
        $result = $this->conexao->query(
            "SELECT DATE(timestamp) as date, COUNT(*) as count FROM {$this->tableName} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY DATE(timestamp) ORDER BY date DESC"
        );
        $stats['logs_last_7_days'] = $result->fetch_all(MYSQLI_ASSOC);

        return $stats;
    }

    /**
     * Obter IP do cliente
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
}
?>
