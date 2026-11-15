<?php
/**
 * Security: IP Whitelist & Rate Limiting
 * Fase 4: SeguranÃ§a
 * 
 * - RestriÃ§Ã£o de acesso por IP
 * - Rate limiting por endpoint
 * - DDoS protection
 * - Bot detection
 * 
 * @author CONFINTER
 * @version 1.0
 */

class SecurityManager {
    
    private $db;
    private $redis;
    private $maxRequests = 100;
    private $timeWindow = 3600; // 1 hora
    
    public function __construct($db, $redis = null) {
        $this->db = $db;
        $this->redis = $redis;
    }
    
    /**
     * Verifica IP contra whitelist
     */
    public function isIPWhitelisted($ip) {
        try {
            $sql = "SELECT * FROM ip_whitelist 
                    WHERE ip = :ip 
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND enabled = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (bool)$result;
            
        } catch (Exception $e) {
            error_log("Erro verificar whitelist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica IP contra blacklist
     */
    public function isIPBlacklisted($ip) {
        try {
            $sql = "SELECT * FROM ip_blacklist 
                    WHERE ip = :ip 
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND enabled = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->logSecurityEvent($ip, 'blacklisted_access_attempt', 'blocked');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erro verificar blacklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aplica rate limiting
     */
    public function checkRateLimit($identifier, $endpoint, $limit = null, $window = null) {
        try {
            $limit = $limit ?? $this->maxRequests;
            $window = $window ?? $this->timeWindow;
            
            $key = "ratelimit:{$endpoint}:{$identifier}";
            
            // Usar Redis se disponÃ­vel, senÃ£o usar banco
            if ($this->redis) {
                $current = $this->redis->incr($key);
                
                if ($current === 1) {
                    $this->redis->expire($key, $window);
                }
                
                $remaining = $limit - $current;
            } else {
                // Fallback para banco de dados
                $sql = "SELECT request_count FROM rate_limit_log 
                        WHERE identifier = :id 
                        AND endpoint = :endpoint 
                        AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
                        ORDER BY created_at DESC LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id' => $identifier,
                    'endpoint' => $endpoint,
                    'window' => $window
                ]);
                $log = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $current = ($log['request_count'] ?? 0) + 1;
                $remaining = $limit - $current;
                
                // Registrar
                $sql = "INSERT INTO rate_limit_log 
                        (identifier, endpoint, request_count, created_at) 
                        VALUES (:id, :endpoint, :count, NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id' => $identifier,
                    'endpoint' => $endpoint,
                    'count' => $current
                ]);
            }
            
            if ($current > $limit) {
                $this->logSecurityEvent($identifier, 'rate_limit_exceeded', 'blocked', 
                    "Endpoint: {$endpoint}, Requests: {$current}/{$limit}");
                
                return [
                    'allowed' => false,
                    'limit' => $limit,
                    'current' => $current,
                    'reset_in' => $window
                ];
            }
            
            return [
                'allowed' => true,
                'limit' => $limit,
                'current' => $current,
                'remaining' => max(0, $remaining)
            ];
            
        } catch (Exception $e) {
            error_log("Erro rate limit: " . $e->getMessage());
            // Permitir em caso de erro
            return ['allowed' => true];
        }
    }
    
    /**
     * Detecta padrÃµes suspeitos (bot detection)
     */
    public function detectSuspiciousActivity($ip, $endpoint) {
        try {
            $suspiciousPatterns = [];
            
            // 1. Muitas requisiÃ§Ãµes em curto espaÃ§o de tempo
            $sql = "SELECT COUNT(*) as count FROM request_log 
                    WHERE ip_address = :ip 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 50) {
                $suspiciousPatterns[] = 'high_request_rate';
            }
            
            // 2. MÃºltiplos endpoints em curto espaÃ§o
            $sql = "SELECT COUNT(DISTINCT endpoint) as endpoints FROM request_log 
                    WHERE ip_address = :ip 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['endpoints'] > 20) {
                $suspiciousPatterns[] = 'multiple_endpoints';
            }
            
            // 3. User-agents suspeitos (bots)
            $botKeywords = ['crawler', 'bot', 'spider', 'scraper', 'curl', 'wget', 'python'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            foreach ($botKeywords as $keyword) {
                if (stripos($userAgent, $keyword) !== false) {
                    $suspiciousPatterns[] = 'bot_user_agent';
                    break;
                }
            }
            
            // 4. Muitas tentativas de login falhadas
            $sql = "SELECT COUNT(*) as fails FROM security_audit_log 
                    WHERE ip_address = :ip 
                    AND action = 'login_failed'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['fails'] > 5) {
                $suspiciousPatterns[] = 'login_attempts';
            }
            
            return [
                'suspicious' => !empty($suspiciousPatterns),
                'patterns' => $suspiciousPatterns,
                'risk_score' => count($suspiciousPatterns) * 20
            ];
            
        } catch (Exception $e) {
            error_log("Erro detectar atividade: " . $e->getMessage());
            return ['suspicious' => false, 'patterns' => []];
        }
    }
    
    /**
     * Adiciona IP Ã  whitelist
     */
    public function addToWhitelist($ip, $expiresAt = null) {
        try {
            $sql = "INSERT INTO ip_whitelist (ip, enabled, created_at, expires_at) 
                    VALUES (:ip, 1, NOW(), :expires)
                    ON DUPLICATE KEY UPDATE 
                    enabled = 1, expires_at = VALUES(expires_at)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'ip' => $ip,
                'expires' => $expiresAt
            ]);
            
            $this->logSecurityEvent($ip, 'ip_whitelisted', 'success');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Adiciona IP Ã  blacklist
     */
    public function addToBlacklist($ip, $reason = null, $expiresAt = null) {
        try {
            $sql = "INSERT INTO ip_blacklist (ip, reason, enabled, created_at, expires_at) 
                    VALUES (:ip, :reason, 1, NOW(), :expires)
                    ON DUPLICATE KEY UPDATE 
                    enabled = 1, expires_at = VALUES(expires_at)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'ip' => $ip,
                'reason' => $reason,
                'expires' => $expiresAt
            ]);
            
            $this->logSecurityEvent($ip, 'ip_blacklisted', 'success', $reason);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Registra requisiÃ§Ã£o
     */
    public function logRequest($endpoint, $userId = null) {
        try {
            $sql = "INSERT INTO request_log 
                    (endpoint, user_id, ip_address, method, user_agent, created_at) 
                    VALUES (:endpoint, :user_id, :ip, :method, :agent, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'endpoint' => $endpoint,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Erro log request: " . $e->getMessage());
        }
    }
    
    /**
     * Registra evento de seguranÃ§a
     */
    private function logSecurityEvent($identifier, $action, $status, $details = null) {
        try {
            $sql = "INSERT INTO security_audit_log 
                    (identifier, action, status, details, ip_address, created_at) 
                    VALUES (:id, :action, :status, :details, :ip, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $identifier,
                'action' => $action,
                'status' => $status,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Erro log seguranÃ§a: " . $e->getMessage());
        }
    }
    
    /**
     * Middleware - VerificaÃ§Ã£o de seguranÃ§a
     */
    public static function securityMiddleware($db, $redis = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $security = new self($db, $redis);
        
        // Verificar blacklist
        if ($security->isIPBlacklisted($ip)) {
            http_response_code(403);
            die(json_encode(['error' => 'IP bloqueado']));
        }
        
        // Detectar atividade suspeita
        $suspicious = $security->detectSuspiciousActivity($ip, $_SERVER['REQUEST_URI'] ?? '');
        if ($suspicious['risk_score'] > 60) {
            // Adicionar Ã  blacklist temporÃ¡ria
            $security->addToBlacklist($ip, 'Suspicious activity detected', 
                date('Y-m-d H:i:s', strtotime('+1 hour')));
            
            http_response_code(403);
            die(json_encode(['error' => 'Acesso suspeito detectado']));
        }
        
        // Rate limiting
        $limit = $security->checkRateLimit($ip, $_SERVER['REQUEST_URI'] ?? '');
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $limit['reset_in']);
            die(json_encode(['error' => 'Rate limit excedido']));
        }
        
        // Registrar requisiÃ§Ã£o
        $security->logRequest($_SERVER['REQUEST_URI'] ?? '');
    }
    
    /**
     * ObtÃ©m estatÃ­sticas de seguranÃ§a
     */
    public function getSecurityStats($days = 7) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_requests,
                        COUNT(DISTINCT ip_address) as unique_ips,
                        SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_requests
                    FROM security_audit_log
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

// Middleware automÃ¡tico
if (php_sapi_name() !== 'cli') {
    // Aplicar apenas em produÃ§Ã£o
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        require_once __DIR__ . '/../config/database.php';
        $db = new PDO($dsn, $db_user, $db_pass);
        SecurityManager::securityMiddleware($db);
    }
}
?>
