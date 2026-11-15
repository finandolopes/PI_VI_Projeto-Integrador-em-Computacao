<?php
/**
 * ========================================
 * SEGURANÇA GLOBAL - Inicialização
 * Arquivo para ser incluído em todas as páginas
 * Data: 07/02/2026
 * ========================================
 */

// Função para sanitizar input
function sanitizar_entrada($entrada) {
    $entrada = trim($entrada);
    $entrada = stripslashes($entrada);
    $entrada = htmlspecialchars($entrada, ENT_QUOTES, 'UTF-8');
    return $entrada;
}

// Função para escape de output
function esc_attr($attr) {
    return htmlspecialchars($attr, ENT_QUOTES, 'UTF-8');
}

// Função para escape HTML
function esc_html($html) {
    return htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8');
}

// Função para validar email
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para validar URL
function validar_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Função para validar CPF
function validar_cpf($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se não é uma sequência de números iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    // Calcula primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $digito1 = 11 - ($soma % 11);
    $digito1 = $digito1 >= 10 ? 0 : $digito1;
    
    // Calcula segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $digito2 = 11 - ($soma % 11);
    $digito2 = $digito2 >= 10 ? 0 : $digito2;
    
    // Verifica se os dígitos calculados correspondem aos dígitos informados
    return $cpf[9] == $digito1 && $cpf[10] == $digito2;
}

// Função para gerar hash de senha
function gerar_hash_senha($senha) {
    return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Função para verificar senha
function verificar_senha($senha, $hash) {
    return password_verify($senha, $hash);
}

// Função para logging seguro
function log_evento($tipo, $descricao, $usuario = null, $ip = null) {
    $log_dir = __DIR__ . '/../logs';
    
    // Criar diretório de logs se não existir
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $arquivo_log = $log_dir . '/eventos_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $usuario = $usuario ?? ($_SESSION['username'] ?? 'Anonymous');
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
    
    $mensagem = "[$timestamp] [" . strtoupper($tipo) . "] User: $usuario | IP: $ip | Descricao: $descricao\n";
    
    // Escrever no arquivo de log
    file_put_contents($arquivo_log, $mensagem, FILE_APPEND);
    
    // Limitar tamanho do arquivo de log (máximo 10MB)
    if (filesize($arquivo_log) > 10485760) {
        $arquivo_backup = $log_dir . '/eventos_' . date('Y-m-d_H-i-s') . '.log.bak';
        copy($arquivo_log, $arquivo_backup);
        file_put_contents($arquivo_log, $mensagem); // Reiniciar log
    }
}

// Função para validar requisição XHR
function eh_requisicao_xhr() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Função para rate limit por IP
function verificar_rate_limit_ip($acao, $max_requests = 100, $intervalo = 3600) {
    $cache_dir = sys_get_temp_dir() . '/rate_limit_cache';
    
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $cache_file = $cache_dir . '/rl_' . md5($ip . $acao) . '.cache';
    
    $dados = ['count' => 0, 'timestamp' => time()];
    
    if (file_exists($cache_file)) {
        $dados = json_decode(file_get_contents($cache_file), true);
        
        // Se passou do intervalo, resetar
        if (time() - $dados['timestamp'] > $intervalo) {
            $dados = ['count' => 1, 'timestamp' => time()];
        } else {
            $dados['count']++;
        }
    }
    
    // Salvar dados atualizados
    file_put_contents($cache_file, json_encode($dados));
    
    // Verificar se excedeu limite
    if ($dados['count'] > $max_requests) {
        log_evento('RATE_LIMIT_EXCEEDED', "Acao: $acao | Requests: " . $dados['count']);
        return false;
    }
    
    return true;
}

// Função para validar CSRF
function validar_csrf($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Função para gerar CSRF token
function gerar_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para sanitizar JSON
function sanitizar_json($dados) {
    return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// Função para validar IP
function validar_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

// Função para validar integridade de sessão
function validar_integridade_sessao() {
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        return true;
    }
    
    // Verificar mudança de User Agent
    if ($_SESSION['user_agent'] !== substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)) {
        error_log("Possível session hijacking detectado");
        return false;
    }
    
    return true;
}

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' code.jquery.com cdn.jsdelivr.net cdnjs.cloudflare.com vlibras.gov.br; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; img-src \'self\' data: https:; font-src \'self\' fonts.gstatic.com cdn.jsdelivr.net cdnjs.cloudflare.com data:; connect-src \'self\' https:; frame-src \'self\' https://www.google.com https://www.google.com.br; frame-ancestors \'self\'; object-src \'none\'; base-uri \'self\'; form-action \'self\';');

// Segurança de cookies
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
}

?>
