<?php
/**
 * Two-Factor Authentication (2FA)
 * Fase 4: SeguranÃ§a
 * 
 * AutenticaÃ§Ã£o de dois fatores com:
 * - SMS OTP (One-Time Password)
 * - Google Authenticator
 * - Backup codes
 * 
 * @author CONFINTER
 * @version 1.0
 */

class TwoFactorAuth {
    
    private $db;
    private $otpLength = 6;
    private $otpTimeout = 300; // 5 minutos
    private $maxAttempts = 5;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Ativa 2FA para usuÃ¡rio
     */
    public function enableTwoFactor($userId, $method = 'sms') {
        try {
            // Gerar secret para Google Authenticator
            $secret = $this->generateGoogleAuthSecret();
            
            // Gerar backup codes
            $backupCodes = $this->generateBackupCodes(10);
            
            // Salvar no banco
            $sql = "INSERT INTO two_factor_auth 
                    (user_id, method, secret, backup_codes, enabled, created_at) 
                    VALUES (:user_id, :method, :secret, :codes, 0, NOW())
                    ON DUPLICATE KEY UPDATE 
                    secret = VALUES(secret),
                    backup_codes = VALUES(backup_codes),
                    enabled = 0";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'method' => $method,
                'secret' => $secret,
                'codes' => json_encode($backupCodes)
            ]);
            
            // Gerar QR code para Google Authenticator
            $qrCode = $this->generateQRCode($userId, $secret);
            
            return [
                'success' => true,
                'secret' => $secret,
                'qr_code' => $qrCode,
                'backup_codes' => $backupCodes,
                'method' => $method
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica e confirma 2FA
     */
    public function verifyAndConfirmTwoFactor($userId, $code) {
        try {
            // Obter configuraÃ§Ã£o 2FA
            $sql = "SELECT * FROM two_factor_auth WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $twoFa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$twoFa) {
                throw new Exception("2FA nÃ£o configurado");
            }
            
            // Verificar cÃ³digo
            if ($twoFa['method'] === 'authenticator') {
                if (!$this->verifyGoogleAuthCode($twoFa['secret'], $code)) {
                    throw new Exception("CÃ³digo invÃ¡lido");
                }
            }
            
            // Ativar 2FA
            $sql = "UPDATE two_factor_auth SET enabled = 1, enabled_at = NOW() 
                    WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            // Registrar auditoria
            $this->logSecurityEvent($userId, '2fa_enabled', 'success');
            
            return [
                'success' => true,
                'message' => '2FA ativado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent($userId, '2fa_verification', 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia OTP via SMS
     */
    public function sendOTPViaSMS($userId) {
        try {
            // Obter usuÃ¡rio
            $sql = "SELECT telefone FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !$user['telefone']) {
                throw new Exception("UsuÃ¡rio sem telefone cadastrado");
            }
            
            // Gerar OTP
            $otp = $this->generateOTP();
            
            // Salvar OTP
            $sql = "INSERT INTO otp_codes 
                    (user_id, code, method, created_at, expires_at) 
                    VALUES (:user_id, :code, 'sms', NOW(), DATE_ADD(NOW(), INTERVAL :timeout SECOND))";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'code' => password_hash($otp, PASSWORD_BCRYPT),
                'timeout' => $this->otpTimeout
            ]);
            
            // Enviar SMS
            $result = $this->sendSMS($user['telefone'], "Seu cÃ³digo de autenticaÃ§Ã£o: {$otp}");
            
            if (!$result) {
                throw new Exception("Erro ao enviar SMS");
            }
            
            $this->logSecurityEvent($userId, 'otp_sent', 'success');
            
            return [
                'success' => true,
                'message' => 'OTP enviado para ' . $this->maskPhone($user['telefone']),
                'timeout' => $this->otpTimeout
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent($userId, 'otp_send', 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica OTP enviado
     */
    public function verifyOTP($userId, $code) {
        try {
            // Obter OTP mais recente nÃ£o expirado
            $sql = "SELECT * FROM otp_codes 
                    WHERE user_id = :user_id 
                    AND method = 'sms'
                    AND expires_at > NOW()
                    ORDER BY created_at DESC 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$otpRecord) {
                throw new Exception("OTP expirado ou invÃ¡lido");
            }
            
            // Verificar tentativas
            $attempts = $this->getOTPAttempts($userId);
            if ($attempts >= $this->maxAttempts) {
                throw new Exception("Muitas tentativas. Tente novamente mais tarde.");
            }
            
            // Verificar cÃ³digo
            if (!password_verify($code, $otpRecord['code'])) {
                $this->recordOTPAttempt($userId, false);
                throw new Exception("CÃ³digo invÃ¡lido");
            }
            
            // Marcar como usado
            $sql = "UPDATE otp_codes SET used = 1, used_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $otpRecord['id']]);
            
            // Limpar tentativas
            $this->clearOTPAttempts($userId);
            
            $this->logSecurityEvent($userId, 'otp_verified', 'success');
            
            return [
                'success' => true,
                'message' => 'OTP verificado com sucesso'
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent($userId, 'otp_verification', 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica backup code
     */
    public function verifyBackupCode($userId, $code) {
        try {
            // Obter configuraÃ§Ã£o
            $sql = "SELECT backup_codes FROM two_factor_auth WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $twoFa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$twoFa) {
                throw new Exception("2FA nÃ£o configurado");
            }
            
            $backupCodes = json_decode($twoFa['backup_codes'], true);
            
            // Verificar se cÃ³digo existe
            $codeKey = array_search($code, $backupCodes);
            if ($codeKey === false) {
                $this->logSecurityEvent($userId, 'backup_code_invalid', 'failed');
                throw new Exception("CÃ³digo de backup invÃ¡lido");
            }
            
            // Remover cÃ³digo usado
            unset($backupCodes[$codeKey]);
            
            // Atualizar
            $sql = "UPDATE two_factor_auth SET backup_codes = :codes WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'codes' => json_encode(array_values($backupCodes)),
                'user_id' => $userId
            ]);
            
            $this->logSecurityEvent($userId, 'backup_code_used', 'success');
            
            return [
                'success' => true,
                'message' => 'Backup code verificado',
                'codes_remaining' => count($backupCodes)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera secret Google Authenticator
     */
    private function generateGoogleAuthSecret() {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $validChars[rand(0, strlen($validChars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Verifica cÃ³digo Google Authenticator (TOTP)
     */
    private function verifyGoogleAuthCode($secret, $code) {
        // ImplementaÃ§Ã£o TOTP
        $time = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $timeSlice = $time + $i;
            $hash = hash_hmac('sha1', pack('N*', 0, $timeSlice), base32_decode($secret), true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $value = unpack('N', substr($hash, $offset, 4))[1];
            $value = ($value & 0x7FFFFFFF) % 1000000;
            $value = str_pad($value, 6, '0', STR_PAD_LEFT);
            
            if ($value === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gera QR Code para Google Authenticator
     */
    private function generateQRCode($userId, $secret) {
        $companyName = 'CONFINTER';
        $otpauthUrl = "otpauth://totp/{$companyName}:{$userId}secret={$secret}&issuer={$companyName}";
        
        // Usar API QR Code gratuita
        return "https://api.qrserver.com/v1/create-qr-code/size=300x300&data=" . 
               urlencode($otpauthUrl);
    }
    
    /**
     * Gera OTP
     */
    private function generateOTP() {
        $otp = '';
        for ($i = 0; $i < $this->otpLength; $i++) {
            $otp .= rand(0, 9);
        }
        return $otp;
    }
    
    /**
     * Gera backup codes
     */
    private function generateBackupCodes($count) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            $codes[] = $code;
        }
        return $codes;
    }
    
    /**
     * Envia SMS
     */
    private function sendSMS($phone, $message) {
        // Implementar com Twilio ou outro serviÃ§o
        return true;
    }
    
    /**
     * Mascara telefone
     */
    private function maskPhone($phone) {
        return substr($phone, 0, 3) . 'XXXX' . substr($phone, -4);
    }
    
    /**
     * ObtÃ©m tentativas OTP
     */
    private function getOTPAttempts($userId) {
        $sql = "SELECT COUNT(*) as attempts FROM otp_attempts 
                WHERE user_id = :user_id 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['attempts'] ?? 0;
    }
    
    /**
     * Registra tentativa OTP
     */
    private function recordOTPAttempt($userId, $success) {
        $sql = "INSERT INTO otp_attempts (user_id, success, created_at) 
                VALUES (:user_id, :success, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'success' => $success ? 1 : 0
        ]);
    }
    
    /**
     * Limpa tentativas
     */
    private function clearOTPAttempts($userId) {
        $sql = "DELETE FROM otp_attempts WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Registra evento de seguranÃ§a
     */
    private function logSecurityEvent($userId, $action, $status, $details = null) {
        $sql = "INSERT INTO security_audit_log 
                (user_id, action, status, details, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :status, :details, :ip, :agent, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'status' => $status,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Desativa 2FA
     */
    public function disableTwoFactor($userId, $password) {
        try {
            // Verificar senha
            $sql = "SELECT password FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                throw new Exception("Senha incorreta");
            }
            
            // Desativar
            $sql = "UPDATE two_factor_auth SET enabled = 0, disabled_at = NOW() 
                    WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $this->logSecurityEvent($userId, '2fa_disabled', 'success');
            
            return [
                'success' => true,
                'message' => '2FA desativado'
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent($userId, '2fa_disable', 'failed', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * ObtÃ©m status 2FA
     */
    public function getStatus($userId) {
        $sql = "SELECT * FROM two_factor_auth WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $twoFa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$twoFa) {
            return ['enabled' => false];
        }
        
        return [
            'enabled' => (bool)$twoFa['enabled'],
            'method' => $twoFa['method'],
            'enabled_at' => $twoFa['enabled_at'],
            'backup_codes_remaining' => count((json_decode($twoFa['backup_codes'], true) ?? []))
        ];
    }
}

// Base32 Decode (para Google Auth)
function base32_decode($input) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $c = strpos($alphabet, $input[$i]);
        if ($c === false) continue;
        $v = ($v << 5) | $c;
        $vbits += 5;
        while ($vbits >= 8) {
            $vbits -= 8;
            $output .= chr(($v >> $vbits) & 255);
        }
    }
    
    return $output;
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("UsuÃ¡rio nÃ£o autenticado");
        }
        
        $db = new PDO($dsn, $db_user, $db_pass);
        $twofa = new TwoFactorAuth($db);
        $userId = $_SESSION['user_id'];
        
        if ($_POST['action'] === 'enable') {
            $result = $twofa->enableTwoFactor($userId, $_POST['method'] ?? 'sms');
            
        } elseif ($_POST['action'] === 'verify_and_confirm') {
            $result = $twofa->verifyAndConfirmTwoFactor($userId, $_POST['code'] ?? '');
            
        } elseif ($_POST['action'] === 'send_otp') {
            $result = $twofa->sendOTPViaSMS($userId);
            
        } elseif ($_POST['action'] === 'verify_otp') {
            $result = $twofa->verifyOTP($userId, $_POST['code'] ?? '');
            
        } elseif ($_POST['action'] === 'verify_backup_code') {
            $result = $twofa->verifyBackupCode($userId, $_POST['code'] ?? '');
            
        } elseif ($_POST['action'] === 'disable') {
            $result = $twofa->disableTwoFactor($userId, $_POST['password'] ?? '');
            
        } elseif ($_POST['action'] === 'status') {
            $result = ['success' => true, 'status' => $twofa->getStatus($userId)];
            
        } else {
            throw new Exception("AÃ§Ã£o invÃ¡lida");
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
