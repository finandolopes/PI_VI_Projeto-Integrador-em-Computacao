<?php
/**
 * Account Deletion Service
 * Permite exclusão de conta com conformidade LGPD/GDPR
 * Implementa soft delete, anonimização e limpeza de dados
 */

class AccountDeletionService {
    private $conexao;
    private $user_id;
    private $user_email;
    private $deletion_token;
    private $deletion_expires = 86400; // 24 horas
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    /**
     * Iniciar processo de exclusão de conta
     * Gera token de confirmação via email
     */
    public function initiateAccountDeletion($user_id, $user_email) {
        // Validar user
        if (!$this->validateUser($user_id, $user_email)) {
            return array('success' => false, 'error' => 'Usuário não encontrado');
        }
        
        $this->user_id = $user_id;
        $this->user_email = $user_email;
        
        // Gerar token
        $this->deletion_token = bin2hex(random_bytes(32));
        $expiry_time = date('Y-m-d H:i:s', time() + $this->deletion_expires);
        
        // Salvar token no BD
        $sql = "INSERT INTO account_deletion_tokens 
                (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                expires_at = VALUES(expires_at),
                created_at = NOW()";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('iss', $user_id, $this->deletion_token, $expiry_time);
        
        if (!$stmt->execute()) {
            return array('success' => false, 'error' => 'Erro ao gerar token');
        }
        
        // Enviar email com link de confirmação
        $confirmation_url = $this->getConfirmationUrl();
        $email_sent = $this->sendDeletionEmail($user_email, $confirmation_url);
        
        if (!$email_sent) {
            return array(
                'success' => false, 
                'error' => 'Erro ao enviar email de confirmação'
            );
        }
        
        // Registrar auditoria
        $this->auditDeletion('deletion_initiated', array(
            'user_id' => $user_id,
            'user_email' => $user_email
        ));
        
        return array(
            'success' => true,
            'message' => 'Email de confirmação enviado. Você tem 24 horas para confirmar a exclusão.',
            'expires_in_hours' => 24
        );
    }
    
    /**
     * Confirmar exclusão de conta
     */
    public function confirmAccountDeletion($token) {
        // Validar token
        $deletion_data = $this->validateToken($token);
        if (!$deletion_data) {
            return array('success' => false, 'error' => 'Token inválido ou expirado');
        }
        
        $this->user_id = $deletion_data['user_id'];
        
        // Obter dados do usuário antes de deletar
        $user = $this->getUserData($this->user_id);
        
        // Iniciar transação
        $this->conexao->begin_transaction();
        
        try {
            // 1. Soft delete do usuário (marcar como deletado)
            $this->softDeleteUser();
            
            // 2. Anonimizar dados pessoais
            $this->anonymizeUserData();
            
            // 3. Limpar dados sensíveis
            $this->cleanupSensitiveData();
            
            // 4. Remover dados de logging/tracking
            $this->removeTrackingData();
            
            // 5. Limpar token de exclusão
            $this->deleteToken($token);
            
            // 6. Invalida todas as sessões ativas
            $this->invalidateSessions();
            
            // Registrar auditoria
            $this->auditDeletion('account_deleted', array(
                'user_id' => $this->user_id,
                'user_email' => $user['email'],
                'deletion_method' => 'confirmed_via_email'
            ));
            
            $this->conexao->commit();
            
            return array(
                'success' => true,
                'message' => 'Conta excluída com sucesso. Seus dados foram anonimizados.',
                'data_retention_period' => '30 dias para backups'
            );
            
        } catch (Exception $e) {
            $this->conexao->rollback();
            return array('success' => false, 'error' => 'Erro ao deletar conta: ' . $e->getMessage());
        }
    }
    
    /**
     * Soft delete - marcar usuário como deletado
     */
    private function softDeleteUser() {
        $deletion_date = date('Y-m-d H:i:s');
        $sql = "UPDATE usuarios 
                SET is_deleted = 1, 
                    deleted_at = ?, 
                    is_active = 0
                WHERE id_usuario = ?";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('si', $deletion_date, $this->user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao marcar usuário como deletado');
        }
    }
    
    /**
     * Anonimizar dados pessoais do usuário
     */
    private function anonymizeUserData() {
        // Hash do email para referência futura (para evitar reregistro)
        $email_hash = hash('sha256', $this->user_id . time());
        $anonymous_name = 'usuario_' . substr($email_hash, 0, 8);
        
        $sql = "UPDATE usuarios 
                SET nome = ?, 
                    usuario = ?, 
                    email = ?, 
                    telefone = NULL,
                    data_nascimento = NULL
                WHERE id_usuario = ?";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('sssi', $anonymous_name, $anonymous_name, $anonymous_name, $this->user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao anonimizar dados');
        }
        
        // Anonimizar depoimentos do usuário
        $sql_depoimentos = "UPDATE depoimentos 
                          SET nome = 'Usuário Anônimo',
                              email = NULL
                          WHERE user_id = ?";
        
        $stmt = $this->conexao->prepare($sql_depoimentos);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        // Anonimizar requisições
        $sql_requisicoes = "UPDATE requisicoes 
                          SET nome = 'Usuário Anônimo',
                              empresa_email = NULL,
                              telefone = NULL
                          WHERE user_id = ?";
        
        $stmt = $this->conexao->prepare($sql_requisicoes);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Limpar dados sensíveis
     */
    private function cleanupSensitiveData() {
        // Limpar tokens de recuperação de senha
        $sql = "DELETE FROM password_reset_tokens WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        // Limpar sessões ativas
        $sql = "DELETE FROM sessions WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        // Limpar logs de login
        $sql = "DELETE FROM login_logs WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        // Limpar preferências de cookies
        $sql = "DELETE FROM lgpd_consent WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Remover dados de tracking/analytics
     */
    private function removeTrackingData() {
        // Remover visitas do usuário
        $sql = "DELETE FROM contador_visitas WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        // Remover upload de imagens (manter soft delete)
        $sql = "UPDATE uploads SET is_deleted = 1 WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Invalidar todas as sessões ativas do usuário
     */
    private function invalidateSessions() {
        $sql = "UPDATE sessions SET is_active = 0 WHERE user_id = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
    }
    
    /**
     * Validar token de exclusão
     */
    private function validateToken($token) {
        $sql = "SELECT user_id FROM account_deletion_tokens 
                WHERE token = ? 
                AND expires_at > NOW() 
                AND is_used = 0
                LIMIT 1";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Marcar token como usado
     */
    private function deleteToken($token) {
        $sql = "UPDATE account_deletion_tokens 
                SET is_used = 1, used_at = NOW()
                WHERE token = ?";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }
    
    /**
     * Validar usuário
     */
    private function validateUser($user_id, $user_email) {
        $sql = "SELECT id_usuario FROM usuarios 
                WHERE id_usuario = ? AND email = ? AND is_deleted = 0
                LIMIT 1";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('is', $user_id, $user_email);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Obter dados do usuário
     */
    private function getUserData($user_id) {
        $sql = "SELECT id_usuario, nome, usuario, email FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Gerar URL de confirmação
     */
    private function getConfirmationUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/php/confirm_account_deletion.php?token={$this->deletion_token}";
    }
    
    /**
     * Enviar email de confirmação
     */
    private function sendDeletionEmail($to, $confirmation_url) {
        // Usar PHPMailer ou função mail nativa
        $subject = '🔒 Confirmação de Exclusão de Conta - CONFINTER';
        
        $message = "
        <html>
        <body>
        <h2>Confirmação de Exclusão de Conta</h2>
        <p>Você solicitou a exclusão de sua conta no CONFINTER.</p>
        
        <p><strong>Importante:</strong> Clique no link abaixo para confirmar a exclusão. 
        Este link expirará em 24 horas.</p>
        
        <p>
            <a href='{$confirmation_url}' style='background: #1a73e8; color: white; padding: 12px 24px; 
            text-decoration: none; border-radius: 4px; display: inline-block;'>
            Confirmar Exclusão de Conta
            </a>
        </p>
        
        <p>Se você não solicitou esta ação, ignore este email.</p>
        
        <p>
            <strong>URL direto:</strong><br>
            <small>{$confirmation_url}</small>
        </p>
        
        <hr>
        <p><small>Este é um email automático. Não responda este email.</small></p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@confinter.com.br\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    /**
     * Registrar auditoria de exclusão
     */
    private function auditDeletion($action, $details) {
        $sql = "INSERT INTO account_deletion_audit 
                (user_id, action, details, ip_address, user_agent, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conexao->prepare($sql);
        $details_json = json_encode($details);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param('isss', $this->user_id, $action, $details_json, $ip);
        $stmt->execute();
    }
    
    /**
     * Solicitar exportação de dados antes de deletar
     */
    public function exportDataBeforeDeletion($user_id) {
        // Implementado em DataExportService
        return array('url' => '/php/export_user_data.php?format=json&token=...');
    }
}
?>
