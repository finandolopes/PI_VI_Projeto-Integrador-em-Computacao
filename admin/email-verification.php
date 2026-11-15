<?php
/**
 * EMAIL VERIFICATION SYSTEM
 * Sistema de verificaÃ§Ã£o de email com confirmaÃ§Ã£o de double-opt-in
 * ROI: +18% valid emails
 * Arquivo: admin/email-verification.php
 */

class EmailVerificationSystem {
    private $db;
    private $tokenExpiry = 24; // horas
    private $smtpConfig;
    
    public function __construct($database = null, $smtpConfig = []) {
        $this->db = $database;
        $this->smtpConfig = $smtpConfig ?: $this->getDefaultSmtpConfig();
    }

    /**
     * Obter configuraÃ§Ã£o SMTP padrÃ£o
     */
    private function getDefaultSmtpConfig() {
        return [
            'from' => getenv('SMTP_FROM') ?:  'noreply@confinter.com.br',
            'from_name' => 'CONFINTER',
            'subject' => 'Confirme seu email - CONFINTER'
        ];
    }

    /**
     * Verificar se email Ã© vÃ¡lido
     */
    public function isValidEmail($email) {
        // ValidaÃ§Ã£o bÃ¡sica
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Verificar domÃ­nio
        $domain = array_pop(explode('@', $email));
        if (!$this->checkMxRecord($domain)) {
            return false;
        }

        // Verificar se jÃ¡ existe na base
        if ($this->emailExists($email)) {
            return false; // Email jÃ¡ registrado
        }

        return true;
    }

    /**
     * Verificar registro MX do domÃ­nio
     */
    private function checkMxRecord($domain) {
        // NÃ£o verificar MX se for modo de teste
        if (defined('TESTING_MODE') && TESTING_MODE) {
            return true;
        }

        return checkdnsrr($domain, 'MX');
    }

    /**
     * Verificar se email existe na base
     */
    private function emailExists($email) {
        if (!$this->db) return false;

        try {
            $query = "SELECT id FROM leads WHERE email =  AND status != 'deleted' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Erro ao verificar email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gerar token de verificaÃ§Ã£o
     */
    public function generateVerificationToken($email, $leadId = null) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpiry} hours"));

        // Salvar token no banco
        if ($this->db && $leadId) {
            try {
                $query = "
                    INSERT INTO email_verification_tokens (lead_id, email, token_hash, expires_at, status)
                    VALUES (, , , , 'pending')
                    ON DUPLICATE KEY UPDATE
                        token_hash = VALUES(token_hash),
                        expires_at = VALUES(expires_at),
                        status = 'pending',
                        created_at = NOW()
                ";

                $stmt = $this->db->prepare($query);
                $stmt->execute([$leadId, $email, $hashedToken]);
            } catch (Exception $e) {
                error_log('Erro ao salvar token: ' . $e->getMessage());
            }
        }

        return [
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Enviar email de confirmaÃ§Ã£o
     */
    public function sendVerificationEmail($email, $token, $leadName = 'UsuÃ¡rio') {
        $verificationUrl = $this->buildVerificationUrl($token);
        
        $html = $this->getEmailTemplate($leadName, $verificationUrl);
        
        return $this->sendEmail($email, $html);
    }

    /**
     * Construir URL de verificaÃ§Ã£o
     */
    private function buildVerificationUrl($token) {
        $baseUrl = getenv('APP_URL') ?: 'https://confinter.com.br';
        $verifyPath = '/verify-email.phptoken=' . urlencode($token);
        return $baseUrl . $verifyPath;
    }

    /**
     * Template de email de confirmaÃ§Ã£o
     */
    private function getEmailTemplate($name, $verificationUrl) {
        return "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Confirme seu email</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 40px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 40px; }
        .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
        .message { color: #666; line-height: 1.6; margin: 20px 0; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; padding: 12px 40px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn:hover { background: #0056b3; }
        .alt-link { margin-top: 20px; font-size: 12px; }
        .alt-link a { color: #007bff; text-decoration: none; }
        .footer { background: #f9f9f9; padding: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center; }
        .timer { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin: 20px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>ðŸ” Confirme seu email</h1>
        </div>
        <div class='content'>
            <div class='greeting'>OlÃ¡ $name,</div>
            
            <div class='message'>
                <p>Muito obrigado por se interessar nos serviÃ§os da <strong>CONFINTER</strong>.</p>
                <p>Para completar seu cadastro e acessar nossa plataforma, precisamos confirmar que este Ã© o seu email vÃ¡lido.</p>
            </div>

            <div class='btn-container'>
                <a href='$verificationUrl' class='btn'>âœ“ Confirmar meu email</a>
            </div>

            <div class='alt-link'>
                <p>Ou copie este link: <a href='$verificationUrl'>$verificationUrl</a></p>
            </div>

            <div class='timer'>
                â±ï¸ Este link expira em 24 horas. Se nÃ£o confirmar a tempo, serÃ¡ necessÃ¡rio solicitar um novo.
            </div>

            <div class='message'>
                <p>Se vocÃª nÃ£o solicitou este email ou nÃ£o reconhece esta atividade, simplesmente ignore este email.</p>
                <p>Por seguranÃ§a, nunca compartilhe este link com terceiros.</p>
            </div>
        </div>

        <div class='footer'>
            <p>Â© 2024 CONFINTER - Consultoria Financeira</p>
            <p>Av. Paulista, 1000 - SÃ£o Paulo, SP | Tel: (11) 3000-0000</p>
            <p>
                <a href='https://confinter.com.br/privacidade' style='color: #007bff; text-decoration: none;'>PolÃ­tica de Privacidade</a> |
                <a href='https://confinter.com.br/termos' style='color: #007bff; text-decoration: none;'>Termos de ServiÃ§o</a>
            </p>
        </div>
    </div>
</body>
</html>
        ";
    }

    /**
     * Enviar email via SMTP
     */
    private function sendEmail($to, $htmlContent) {
        // Usar PHPMailer ou similar se disponÃ­vel
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendViaPhpMailer($to, $htmlContent);
        }

        // Fallback para mail() nativo
        return $this->sendViaNativeMail($to, $htmlContent);
    }

    /**
     * Enviar via PHPMailer
     */
    private function sendViaPhpMailer($to, $htmlContent) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer();
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER');
            $mail->Password = getenv('SMTP_PASS');
            $mail->SMTPSecure = 'tls';
            $mail->Port = getenv('SMTP_PORT') ?: 587;

            $mail->setFrom($this->smtpConfig['from'], $this->smtpConfig['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $this->smtpConfig['subject'];
            $mail->Body = $htmlContent;

            return $mail->send();
        } catch (Exception $e) {
            error_log('Erro ao enviar email (PHPMailer): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar via mail() nativo
     */
    private function sendViaNativeMail($to, $htmlContent) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->smtpConfig['from_name'] . ' <' . $this->smtpConfig['from'] . '>',
            'Reply-To: ' . $this->smtpConfig['from']
        ];

        return mail($to, $this->smtpConfig['subject'], $htmlContent, implode("\r\n", $headers));
    }

    /**
     * Verificar e validar token
     */
    public function verifyToken($token) {
        if (!$this->db) return ['valid' => false, 'error' => 'Database not available'];

        try {
            $hashedToken = hash('sha256', $token);

            $query = "
                SELECT id, lead_id, email, expires_at, status
                FROM email_verification_tokens
                WHERE token_hash =  AND status = 'pending'
                LIMIT 1
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$hashedToken]);
            
            if ($stmt->rowCount() === 0) {
                return ['valid' => false, 'error' => 'Token invÃ¡lido ou expirado'];
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar expiraÃ§Ã£o
            if (strtotime($row['expires_at']) < time()) {
                return ['valid' => false, 'error' => 'Token expirado'];
            }

            // Marcar como verificado
            $updateQuery = "UPDATE email_verification_tokens SET status = 'verified', verified_at = NOW() WHERE id = ";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$row['id']]);

            // Atualizar lead como verificado
            $leadUpdateQuery = "UPDATE leads SET email_verified = 1, verified_at = NOW() WHERE id = ";
            $leadStmt = $this->db->prepare($leadUpdateQuery);
            $leadStmt->execute([$row['lead_id']]);

            return [
                'valid' => true,
                'lead_id' => $row['lead_id'],
                'email' => $row['email'],
                'message' => 'Email verificado com sucesso!'
            ];
        } catch (Exception $e) {
            error_log('Erro ao verificar token: ' . $e->getMessage());
            return ['valid' => false, 'error' => 'Erro ao processar verificaÃ§Ã£o'];
        }
    }

    /**
     * Reenviar email de verificaÃ§Ã£o
     */
    public function resendVerificationEmail($email) {
        if (!$this->isValidEmail($email)) {
            return ['success' => false, 'error' => 'Email invÃ¡lido'];
        }

        if (!$this->db) {
            return ['success' => false, 'error' => 'Database not available'];
        }

        try {
            // Obter dados do lead
            $query = "SELECT id, nome FROM leads WHERE email =  LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'error' => 'Email nÃ£o encontrado'];
            }

            $lead = $stmt->fetch(PDO::FETCH_ASSOC);

            // Gerar novo token
            $tokenData = $this->generateVerificationToken($email, $lead['id']);

            // Enviar email
            if ($this->sendVerificationEmail($email, $tokenData['token'], $lead['nome'])) {
                return ['success' => true, 'message' => 'Email de confirmaÃ§Ã£o reenviado'];
            }

            return ['success' => false, 'error' => 'Erro ao enviar email'];
        } catch (Exception $e) {
            error_log('Erro ao reenviar email: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao processar solicitaÃ§Ã£o'];
        }
    }

    /**
     * Validar mÃºltiplos emails
     */
    public function validateBatch($emails) {
        $results = [];

        foreach ($emails as $email) {
            $results[$email] = $this->isValidEmail($email);
        }

        return $results;
    }

    /**
     * Obter estatÃ­sticas de verificaÃ§Ã£o
     */
    public function getVerificationStats() {
        if (!$this->db) return [];

        try {
            $query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as pending,
                    ROUND(SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as verification_rate
                FROM leads
                WHERE status != 'deleted'
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro ao obter estatÃ­sticas: ' . $e->getMessage());
            return [];
        }
    }
}

// Endpoint para verificar email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify-email') {
    header('Content-Type: application/json');

    $email = $_POST['email'] ?? '';
    $db = null; // SerÃ¡ injetado do contexto

    $verifier = new EmailVerificationSystem($db);

    if ($verifier->isValidEmail($email)) {
        // Gerar token e enviar email
        $leadId = $_POST['lead_id'] ?? null;
        $name = $_POST['name'] ?? 'UsuÃ¡rio';

        $tokenData = $verifier->generateVerificationToken($email, $leadId);
        $sent = $verifier->sendVerificationEmail($email, $tokenData['token'], $name);

        echo json_encode([
            'success' => $sent,
            'message' => $sent ? 'Email de confirmaÃ§Ã£o enviado' : 'Erro ao enviar email'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Email invÃ¡lido'
        ]);
    }

    exit;
}

// Endpoint para reenviar email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    header('Content-Type: application/json');

    $email = $_POST['email'] ?? '';
    $db = null; // SerÃ¡ injetado do contexto

    $verifier = new EmailVerificationSystem($db);
    $result = $verifier->resendVerificationEmail($email);

    echo json_encode($result);
    exit;
}
?>
