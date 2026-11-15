<?php
/**
 * LGPD Consent Manager
 * Gerenciador de consentimento para conformidade com LGPD (Lei Geral de Proteção de Dados)
 * 
 * @version 2.0
 * @author Sistema CONFINTER
 * @description Gerencia preferências de consentimento de usuários para cookies, analytics e marketing
 */

class ConsentManager {
    private $conexao;
    private $user_id;
    private $session_id;
    private $consent_cookie = 'confinter_consent_v1';
    private $consent_expires = 31536000; // 1 ano em segundos
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->session_id = session_id();
    }
    
    /**
     * Inicializa o gerenciador de consentimento
     * Define user_id se usuário está autenticado
     */
    public function initialize() {
        if (isset($_SESSION['user_id'])) {
            $this->user_id = $_SESSION['user_id'];
        }
        
        // Carregar consentimento armazenado
        return $this->loadConsent();
    }
    
    /**
     * Carregar preferências de consentimento do usuário
     * Prioridade: Banco de dados > Cookie > Padrão
     */
    private function loadConsent() {
        // Se usuário autenticado, carregar do BD
        if ($this->user_id) {
            return $this->loadFromDatabase();
        }
        
        // Caso contrário, verificar cookie
        if (isset($_COOKIE[$this->consent_cookie])) {
            return json_decode($_COOKIE[$this->consent_cookie], true);
        }
        
        // Retornar preferências padrão (apenas essencial)
        return $this->getDefaultConsent();
    }
    
    /**
     * Carregar consentimento do banco de dados
     */
    private function loadFromDatabase() {
        $sql = "SELECT * FROM lgpd_consent WHERE user_id = ? LIMIT 1";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return array(
                'essential' => true, // Sempre essencial
                'analytics' => (bool)$row['analytics'],
                'marketing' => (bool)$row['marketing'],
                'preferences' => (bool)$row['preferences'],
                'updated_at' => $row['updated_at'],
                'version' => $row['version']
            );
        }
        
        return $this->getDefaultConsent();
    }
    
    /**
     * Retornar consentimento padrão (apenas cookies essenciais)
     */
    public function getDefaultConsent() {
        return array(
            'essential' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'updated_at' => date('Y-m-d H:i:s'),
            'version' => '2.0'
        );
    }
    
    /**
     * Salvar preferências de consentimento
     */
    public function saveConsent($analytics = false, $marketing = false, $preferences = false) {
        $consent = array(
            'essential' => true,
            'analytics' => (bool)$analytics,
            'marketing' => (bool)$marketing,
            'preferences' => (bool)$preferences,
            'updated_at' => date('Y-m-d H:i:s'),
            'version' => '2.0'
        );
        
        // Salvar em cookie
        setcookie(
            $this->consent_cookie,
            json_encode($consent),
            time() + $this->consent_expires,
            '/',
            '',
            true, // Secure - HTTPS only
            true  // HttpOnly
        );
        
        // Se usuário autenticado, salvar no BD
        if ($this->user_id) {
            $this->saveToDatabase($consent);
        }
        
        return $consent;
    }
    
    /**
     * Salvar consentimento no banco de dados
     */
    private function saveToDatabase($consent) {
        $sql = "INSERT INTO lgpd_consent 
                (user_id, analytics, marketing, preferences, updated_at, version) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                analytics = VALUES(analytics),
                marketing = VALUES(marketing),
                preferences = VALUES(preferences),
                updated_at = VALUES(updated_at)";
        
        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            error_log("Erro ao preparar statement LGPD: " . $this->conexao->error);
            return false;
        }
        
        $stmt->bind_param(
            'iiiiss',
            $this->user_id,
            $consent['analytics'],
            $consent['marketing'],
            $consent['preferences'],
            $consent['updated_at'],
            $consent['version']
        );
        
        return $stmt->execute();
    }
    
    /**
     * Verificar se cookie específico foi consentido
     */
    public function hasConsent($type = 'analytics') {
        $consent = $this->loadConsent();
        return isset($consent[$type]) ? $consent[$type] : false;
    }
    
    /**
     * Revogar consentimento
     */
    public function revokeConsent() {
        // Remover cookie
        setcookie($this->consent_cookie, '', time() - 3600, '/');
        
        // Remover do BD
        if ($this->user_id) {
            $sql = "DELETE FROM lgpd_consent WHERE user_id = ?";
            $stmt = $this->conexao->prepare($sql);
            $stmt->bind_param('i', $this->user_id);
            $stmt->execute();
        }
        
        return true;
    }
    
    /**
     * Obter status completo de consentimento
     */
    public function getConsentStatus() {
        return array(
            'has_user' => (bool)$this->user_id,
            'consent' => $this->loadConsent(),
            'cookie_banner_shown' => isset($_COOKIE[$this->consent_cookie]),
            'timestamp' => time()
        );
    }
    
    /**
     * Registrar auditoria de consentimento (para compliance)
     */
    public function auditConsent($action, $details = array()) {
        $sql = "INSERT INTO lgpd_audit 
                (user_id, session_id, action, details, ip_address, user_agent, timestamp) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conexao->prepare($sql);
        $user_id = $this->user_id ?? null;
        $details_json = json_encode($details);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param('sssss', $user_id, $this->session_id, $action, $details_json, $ip);
        
        return $stmt->execute();
    }
}

// Inicializar ConsentManager globalmente se sessão ativa
if (session_status() === PHP_SESSION_ACTIVE && isset($conexao)) {
    $consentManager = new ConsentManager($conexao);
    $consentManager->initialize();
}
?>
