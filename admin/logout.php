<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Incluir arquivos necessários
include_once('../php/security.php');
include_once('../php/conexao.php');

// Tenta registrar logout com AuditLogger se disponível
if (isset($_SESSION['user_id'])) {
    try {
        include_once('../php/AuditLogger.php');
        $auditLogger = new AuditLogger($conexao);
        $auditLogger->logLogout($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Erro ao registrar logout: " . $e->getMessage());
        // Continuar mesmo se houver erro
    }
}

// Log do logout (compatibilidade)
if (isset($_SESSION['username']) && function_exists('log_evento')) {
    log_evento('LOGOUT', 'Usuário realizou logout com sucesso', $_SESSION['username'], $_SERVER['REMOTE_ADDR']);
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se você deseja encerrar completamente a sessão, apague também o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrua a sessão
session_destroy();

// Redirecionar para a página de login com mensagem de sucesso
header("Location: ../index.php?logout=success");
exit();
?>
