<?php
/**
 * Security Headers Configuration
 * Adiciona headers de segurança essenciais para proteção da aplicação
 * 
 * Implementa: CSP, X-Frame-Options, X-Content-Type-Options, HSTS, etc
 */

// ============================================================================
// Content Security Policy (CSP) - Previne XSS
// ============================================================================
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
    "img-src 'self' data: https: blob:; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "connect-src 'self' https: blob:; " .
    "frame-src 'self'; " .
    "object-src 'none'; " .
    "media-src 'self' blob:; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'self'"
);

// ============================================================================
// X-Frame-Options - Previne Clickjacking
// ============================================================================
header("X-Frame-Options: SAMEORIGIN");

// ============================================================================
// X-Content-Type-Options - Previne MIME Type Sniffing
// ============================================================================
header("X-Content-Type-Options: nosniff");

// ============================================================================
// X-XSS-Protection - Ativa proteção XSS do navegador
// ============================================================================
header("X-XSS-Protection: 1; mode=block");

// ============================================================================
// Referrer-Policy - Controla informações de referrer
// ============================================================================
header("Referrer-Policy: strict-origin-when-cross-origin");

// ============================================================================
// Permissions-Policy (Feature-Policy) - Controla features do navegador
// ============================================================================
header("Permissions-Policy: " .
    "accelerometer=(), " .
    "ambient-light-sensor=(), " .
    "autoplay=(), " .
    "battery=(), " .
    "camera=(), " .
    "cross-origin-isolated=(), " .
    "display-capture=(), " .
    "document-domain=(), " .
    "encrypted-media=(), " .
    "execution-while-not-rendered=(), " .
    "execution-while-out-of-viewport=(), " .
    "fullscreen=(), " .
    "geolocation=(), " .
    "gyroscope=(), " .
    "magnetometer=(), " .
    "microphone=(), " .
    "midi=(), " .
    "payment=(), " .
    "picture-in-picture=(), " .
    "publickey-credentials-get=(), " .
    "sync-xhr=(self), " .
    "usb=(), " .
    "vr=(), " .
    "xr-spatial-tracking=()"
);

// ============================================================================
// HSTS (HTTP Strict-Transport-Security) - Força HTTPS
// ============================================================================
// Apenas ativar em HTTPS em produção!
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// ============================================================================
// Public-Key-Pins - Certificate Pinning (Opcional, avançado)
// ============================================================================
// header("Public-Key-Pins: " .
//     "pin-sha256=\"base64-do-certificado\"; " .
//     "pin-sha256=\"base64-do-backup\"; " .
//     "max-age=2592000; " .
//     "includeSubDomains"
// );

// ============================================================================
// Expect-CT - Certificate Transparency
// ============================================================================
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Expect-CT: max-age=86400, enforce");
}

// ============================================================================
// Cross-Origin-Embedder-Policy - Isolamento de contexto
// ============================================================================
header("Cross-Origin-Embedder-Policy: require-corp");

// ============================================================================
// Cross-Origin-Opener-Policy - Abertura de janelas cross-origin
// ============================================================================
header("Cross-Origin-Opener-Policy: same-origin");

// ============================================================================
// Cross-Origin-Resource-Policy - Controla acesso a recursos
// ============================================================================
header("Cross-Origin-Resource-Policy: same-origin");

// ============================================================================
// X-Permitted-Cross-Domain-Policies - Política para Flash/Adobe
// ============================================================================
header("X-Permitted-Cross-Domain-Policies: none");

// ============================================================================
// Cache-Control - Controlagem de cache
// ============================================================================
// Para páginas dinâmicas:
if (basename($_SERVER['PHP_SELF']) !== 'index.php' || 
    (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
} else {
    // Para assets estáticos:
    header("Cache-Control: public, max-age=3600");
}

// ============================================================================
// Server Header - Remove informações do servidor
// ============================================================================
header_remove('Server');
header("Server: CONFINTER/1.0");

// ============================================================================
// X-Powered-By - Remove informações de tecnologia
// ============================================================================
header_remove('X-Powered-By');

// ============================================================================
// Security Headers Status Check Function
// ============================================================================

/**
 * Verificar status dos security headers
 */
function checkSecurityHeaders() {
    $headers_to_check = array(
        'Content-Security-Policy' => 'CSP implementado',
        'X-Frame-Options' => 'Proteção contra clickjacking',
        'X-Content-Type-Options' => 'Proteção MIME sniffing',
        'X-XSS-Protection' => 'Proteção XSS',
        'Strict-Transport-Security' => 'HTTPS forçado',
        'Referrer-Policy' => 'Política de referrer'
    );
    
    $report = array();
    
    foreach (getallheaders() as $name => $value) {
        if (isset($headers_to_check[$name])) {
            $report[$name] = array(
                'status' => '✅',
                'description' => $headers_to_check[$name],
                'value' => substr($value, 0, 100)
            );
            unset($headers_to_check[$name]);
        }
    }
    
    // Headers não encontrados
    foreach ($headers_to_check as $name => $description) {
        $report[$name] = array(
            'status' => '⚠️',
            'description' => $description,
            'value' => 'Não configurado'
        );
    }
    
    return $report;
}

// ============================================================================
// Security Headers Logger
// ============================================================================

/**
 * Logar headers de segurança para auditoria
 */
function logSecurityHeaders($user_id = null) {
    $log_data = array(
        'user_id' => $user_id,
        'url' => $_SERVER['REQUEST_URI'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
        'headers_sent' => array(
            'CSP' => isset($_SERVER['HTTP_CONTENT_SECURITY_POLICY']) ? 'present' : 'absent',
            'X-Frame-Options' => isset($_SERVER['HTTP_X_FRAME_OPTIONS']) ? 'present' : 'absent'
        )
    );
    
    // Poderia ser enviado para banco de dados ou arquivo de log
    // Para agora, apenas log no error_log
    error_log(json_encode($log_data));
}

// ============================================================================
// Content-Type Charset Configuration
// ============================================================================

/**
 * Configurar Content-Type apropriado com charset UTF-8
 */
function setContentType($type = 'html') {
    $types = array(
        'html' => 'text/html; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'pdf' => 'application/pdf',
        'file' => 'application/octet-stream'
    );
    
    $content_type = $types[$type] ?? $types['html'];
    header("Content-Type: {$content_type}");
}

// ============================================================================
// CORS Headers Configuration
// ============================================================================

/**
 * Configurar CORS de forma segura
 */
function setCORSHeaders($allowed_origins = array()) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Configuração padrão - aceitar apenas o domínio atual
    $default_origins = array(
        'http://localhost',
        'http://localhost:8080',
        'https://' . $_SERVER['HTTP_HOST'] ?? ''
    );
    
    $allowed = !empty($allowed_origins) ? $allowed_origins : $default_origins;
    
    if (in_array($origin, $allowed)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
        header("Access-Control-Max-Age: 3600");
    }
}

// ============================================================================
// Security Headers Test/Debug Mode (Apenas em desenvolvimento!)
// ============================================================================

if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1', 'localhost'))) {
    // Disponível apenas em localhost para debug
    if (isset($_GET['debug_security_headers'])) {
        header('Content-Type: application/json');
        echo json_encode(checkSecurityHeaders(), JSON_PRETTY_PRINT);
        exit;
    }
}

// ============================================================================
// Apply to Atual Request
// ============================================================================

// Definir Content-Type padrão
if (!headers_sent()) {
    setContentType('html');
}

// Log security headers (opcional - comentado para performance)
// logSecurityHeaders($_SESSION['user_id'] ?? null);
?>
