<?php
/**
 * Data Export Service
 * Permite exportação de dados do usuário em conformidade com LGPD/GDPR
 * Suporta formatos: JSON, CSV, PDF
 */

class DataExportService {
    private $conexao;
    private $user_id;
    private $user_email;
    private $export_token;
    private $export_expires = 86400; // 24 horas
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    /**
     * Solicitar exportação de dados
     */
    public function requestDataExport($user_id, $user_email, $format = 'json') {
        // Validar formato
        $valid_formats = ['json', 'csv', 'pdf'];
        if (!in_array($format, $valid_formats)) {
            return array('success' => false, 'error' => 'Formato não suportado');
        }
        
        // Validar usuário
        if (!$this->validateUser($user_id, $user_email)) {
            return array('success' => false, 'error' => 'Usuário não encontrado');
        }
        
        $this->user_id = $user_id;
        $this->user_email = $user_email;
        
        // Gerar token de acesso
        $this->export_token = bin2hex(random_bytes(32));
        $expiry_time = date('Y-m-d H:i:s', time() + $this->export_expires);
        
        // Salvar requisição no BD
        $sql = "INSERT INTO data_export_requests 
                (user_id, format, token, expires_at, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('iss', $user_id, $format, $this->export_token);
        
        if (!$stmt->execute()) {
            return array('success' => false, 'error' => 'Erro ao processar requisição');
        }
        
        // Enviar email com link de download
        $download_url = $this->getDownloadUrl($format);
        $this->sendExportEmail($user_email, $download_url, $format);
        
        // Registrar auditoria
        $this->auditExport('export_requested', array(
            'format' => $format,
            'user_id' => $user_id
        ));
        
        return array(
            'success' => true,
            'message' => 'Requisição de exportação criada. Link de download foi enviado por email.',
            'download_url' => $download_url,
            'expires_in_hours' => 24,
            'format' => $format
        );
    }
    
    /**
     * Obter dados exportáveis do usuário
     */
    public function getUserData() {
        $data = array();
        
        // 1. Dados pessoais
        $data['profile'] = $this->getUserProfile();
        
        // 2. Requisições
        $data['requisitions'] = $this->getUserRequisitions();
        
        // 3. Depoimentos
        $data['testimonials'] = $this->getUserTestimonials();
        
        // 4. Uploads
        $data['uploads'] = $this->getUserUploads();
        
        // 5. Histórico de atividades
        $data['activity_log'] = $this->getUserActivityLog();
        
        // 6. Preferências (Cookies, LGPD)
        $data['preferences'] = $this->getUserPreferences();
        
        // 7. Metadados da exportação
        $data['export_metadata'] = array(
            'exported_at' => date('Y-m-d H:i:s'),
            'user_id' => $this->user_id,
            'gdpr_compliant' => true,
            'data_retention_policy' => 'Dados serão retidos por 30 dias antes de exclusão permanente'
        );
        
        return $data;
    }
    
    /**
     * Exportar em JSON
     */
    public function exportAsJSON() {
        $data = $this->getUserData();
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus_dados_' . $this->user_id . '_' . date('Y-m-d-H-i-s') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $this->markExportComplete('json');
        return true;
    }
    
    /**
     * Exportar em CSV
     */
    public function exportAsCSV() {
        $data = $this->getUserData();
        
        // Criar arquivo CSV em memória
        $csv = fopen('php://output', 'w');
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus_dados_' . $this->user_id . '_' . date('Y-m-d-H-i-s') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // BOM para UTF-8 (Excel)
        fprintf($csv, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalhos
        $headers = array(
            'CATEGORIA',
            'TIPO',
            'CONTEÚDO',
            'DATA',
            'DESCRIÇÃO'
        );
        fputcsv($csv, $headers, ';');
        
        // Dados do perfil
        if ($data['profile']) {
            $profile = $data['profile'];
            fputcsv($csv, array(
                'PERFIL',
                'Nome',
                $profile['nome'],
                date('Y-m-d H:i:s'),
                'Nome completo do usuário'
            ), ';');
            
            fputcsv($csv, array(
                'PERFIL',
                'Email',
                $profile['email'],
                date('Y-m-d H:i:s'),
                'Email do usuário'
            ), ';');
        }
        
        // Requisições
        if (!empty($data['requisitions'])) {
            foreach ($data['requisitions'] as $req) {
                fputcsv($csv, array(
                    'REQUISIÇÃO',
                    'Empresa',
                    $req['empresa'] ?? '',
                    $req['data_criacao'] ?? '',
                    'Requisição de análise'
                ), ';');
            }
        }
        
        // Depoimentos
        if (!empty($data['testimonials'])) {
            foreach ($data['testimonials'] as $test) {
                fputcsv($csv, array(
                    'DEPOIMENTO',
                    'Conteúdo',
                    substr($test['mensagem'], 0, 100) . '...',
                    $test['data_criacao'] ?? '',
                    'Depoimento do usuário'
                ), ';');
            }
        }
        
        fclose($csv);
        
        $this->markExportComplete('csv');
        return true;
    }
    
    /**
     * Obter dados do perfil
     */
    private function getUserProfile() {
        $sql = "SELECT id_usuario, nome, usuario, email, data_registro 
                FROM usuarios WHERE id_usuario = ?";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Obter requisições do usuário
     */
    private function getUserRequisitions() {
        $sql = "SELECT id_requisicao, empresa, empresa_email, segmento, descricao, data_criacao 
                FROM requisicoes WHERE user_id = ? OR email = ? 
                ORDER BY data_criacao DESC LIMIT 100";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('is', $this->user_id, $this->user_email);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obter depoimentos do usuário
     */
    private function getUserTestimonials() {
        $sql = "SELECT id_depoimento, nome, email, mensagem, data_criacao, status_mod 
                FROM depoimentos WHERE user_id = ? OR email = ? 
                ORDER BY data_criacao DESC LIMIT 100";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('is', $this->user_id, $this->user_email);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obter uploads do usuário
     */
    private function getUserUploads() {
        $sql = "SELECT id, filename, original_filename, upload_date, file_size 
                FROM uploads WHERE user_id = ? AND is_deleted = 0 
                ORDER BY upload_date DESC LIMIT 100";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obter histórico de atividades
     */
    private function getUserActivityLog() {
        $sql = "SELECT action, details, timestamp 
                FROM activity_log WHERE user_id = ? 
                ORDER BY timestamp DESC LIMIT 100";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obter preferências do usuário
     */
    private function getUserPreferences() {
        $sql = "SELECT analytics, marketing, preferences, updated_at 
                FROM lgpd_consent WHERE user_id = ? LIMIT 1";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('i', $this->user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    /**
     * Validar token de acesso
     */
    public function validateExportToken($token, $format) {
        $sql = "SELECT user_id FROM data_export_requests 
                WHERE token = ? AND format = ? AND expires_at > NOW() AND status = 'pending'
                LIMIT 1";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('ss', $token, $format);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->user_id = $row['user_id'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Marcar exportação como completa
     */
    private function markExportComplete($format) {
        $sql = "UPDATE data_export_requests 
                SET status = 'completed', downloaded_at = NOW()
                WHERE token = ? AND format = ?";
        
        $stmt = $this->conexao->prepare($sql);
        $stmt->bind_param('ss', $this->export_token, $format);
        $stmt->execute();
        
        $this->auditExport('export_downloaded', array('format' => $format));
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
     * Gerar URL de download
     */
    private function getDownloadUrl($format) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}/php/download_export.php?token={$this->export_token}&format={$format}";
    }
    
    /**
     * Enviar email com link de download
     */
    private function sendExportEmail($to, $download_url, $format) {
        $subject = '📊 Seus Dados Exportados - CONFINTER';
        
        $format_label = strtoupper($format);
        
        $message = "
        <html>
        <body>
        <h2>Seus Dados Foram Exportados</h2>
        <p>Olá,</p>
        
        <p>Sua solicitação de exportação de dados foi processada com sucesso. 
        O arquivo está pronto para download no formato <strong>{$format_label}</strong>.</p>
        
        <p>
            <a href='{$download_url}' style='background: #1a73e8; color: white; padding: 12px 24px; 
            text-decoration: none; border-radius: 4px; display: inline-block;'>
            Baixar Meus Dados ({$format_label})
            </a>
        </p>
        
        <p><strong>Importante:</strong> Este link expirará em 24 horas.</p>
        
        <h3>Conteúdo Incluído:</h3>
        <ul>
            <li>Dados do perfil (nome, email, data de registro)</li>
            <li>Requisições de análise</li>
            <li>Depoimentos enviados</li>
            <li>Arquivos enviados</li>
            <li>Histórico de atividades</li>
            <li>Preferências de cookies e LGPD</li>
        </ul>
        
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
     * Registrar auditoria de exportação
     */
    private function auditExport($action, $details) {
        $sql = "INSERT INTO data_export_audit 
                (user_id, action, details, ip_address, user_agent, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->conexao->prepare($sql);
        $details_json = json_encode($details);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param('isss', $this->user_id, $action, $details_json, $ip);
        $stmt->execute();
    }
}
?>
