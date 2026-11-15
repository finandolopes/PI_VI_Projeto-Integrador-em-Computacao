<?php
/**
 * Consent Handler
 * Processa requisições AJAX para salvar preferências de consentimento
 */

session_start();
header('Content-Type: application/json');

// Incluir o gerenciador de consentimento
require_once('consent_manager.php');
require_once('conexao.php');

try {
    // Validar requisição
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    // Validar header AJAX
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        throw new Exception('Requisição inválida');
    }
    
    // Obter dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    // Validar ação
    $action = $data['action'] ?? null;
    if (!in_array($action, ['accept_all', 'reject_all', 'save_preferences'])) {
        throw new Exception('Ação não reconhecida');
    }
    
    // Inicializar gerenciador
    $consentManager = new ConsentManager($conexao);
    $consentManager->initialize();
    
    // Processar ação
    switch ($action) {
        case 'accept_all':
            $consent = $consentManager->saveConsent(true, true, true);
            $consentManager->auditConsent('accept_all', [
                'analytics' => true,
                'marketing' => true,
                'preferences' => true
            ]);
            break;
            
        case 'reject_all':
            $consent = $consentManager->saveConsent(false, false, false);
            $consentManager->auditConsent('reject_all', [
                'analytics' => false,
                'marketing' => false,
                'preferences' => false
            ]);
            break;
            
        case 'save_preferences':
            $analytics = (bool)($data['analytics'] ?? false);
            $marketing = (bool)($data['marketing'] ?? false);
            $preferences = (bool)($data['preferences'] ?? false);
            
            $consent = $consentManager->saveConsent($analytics, $marketing, $preferences);
            $consentManager->auditConsent('save_preferences', [
                'analytics' => $analytics,
                'marketing' => $marketing,
                'preferences' => $preferences
            ]);
            break;
    }
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Preferências salvas com sucesso',
        'consent' => $consent,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
