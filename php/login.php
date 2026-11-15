<?php
/**
 * ========================================
 * LOGIN - Versão Segura
 * Autenticação com bcrypt e rate limiting
 * Data: 07/02/2026
 * ========================================
 */

session_start();

// Iniciar CSRF token se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include_once('../php/conexao.php');

$db = isset($conexao) ? $conexao : (isset($con) ? $con : null);

// Rate limit por IP
function verificarRateLimitLogin($chave, $tentativas_max = 5, $intervalo = 600) {
    $arquivo_rate = sys_get_temp_dir() . '/rate_limit_' . md5($chave);
    
    if (file_exists($arquivo_rate)) {
        $dados = unserialize(file_get_contents($arquivo_rate));
        
        if (time() - $dados['timestamp'] < $intervalo) {
            if ($dados['count'] >= $tentativas_max) {
                return ['bloqueado' => true, 'tempo_restante' => $intervalo - (time() - $dados['timestamp'])];
            }
            $dados['count']++;
        } else {
            $dados['count'] = 1;
            $dados['timestamp'] = time();
        }
    } else {
        $dados = ['count' => 1, 'timestamp' => time()];
    }
    
    file_put_contents($arquivo_rate, serialize($dados));
    return ['bloqueado' => false, 'tentativas_restantes' => 5 - $dados['count']];
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['usuario']) && isset($_POST['senha']) && $db) {
    // Validar CSRF token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Tentativa de ataque CSRF: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['nao_autenticado'] = true;
        header('Location: ../index.php?login=erro');
        exit();
    }
    
    // Verificar rate limit por IP
    $chave_rate = 'login_' . $_SERVER['REMOTE_ADDR'];
    $rate = verificarRateLimitLogin($chave_rate);
    
    if ($rate['bloqueado']) {
        error_log("Rate limit excedido: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['nao_autenticado'] = true;
        $_SESSION['erro_rate_limit'] = true;
        header('Location: ../index.php?login=bloqueado');
        exit();
    }
    
    $login = trim($_POST['usuario']);
    $senha = $_POST['senha'];
    
    // Validar entrada
    if (strlen($login) < 3 || strlen($senha) < 8) {
        error_log("Credenciais inválidas: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['nao_autenticado'] = true;
        header('Location: ../index.php?login=erro');
        exit();
    }
    
    // Buscar usuário
    $stmt = $db->prepare("SELECT id_usuario, senha FROM adm WHERE usuario = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        
        if ($row && password_verify($senha, $row['senha'])) {
            // Senha correta - Login bem-sucedido
            session_regenerate_id(true);
            $_SESSION['username'] = $login;
            $_SESSION['user_id'] = $row['id_usuario'];
            $_SESSION['login_time'] = time();
            
            // Limpar rate limit após login bem-sucedido
            $arquivo_rate = sys_get_temp_dir() . '/rate_limit_' . md5($chave_rate);
            if (file_exists($arquivo_rate)) {
                unlink($arquivo_rate);
            }
            
            error_log("Login bem-sucedido: " . $login . " (" . $_SERVER['REMOTE_ADDR'] . ")");
            header('Location: ../admin/admin.php');
            exit();
        } else {
            // Falha de login - Registrar tentativa
            error_log("Falha de login: " . $login . " (" . $_SERVER['REMOTE_ADDR'] . ")");
        }
    } else {
        error_log("Erro ao preparar statement: " . $db->error);
    }
    
    $_SESSION['nao_autenticado'] = true;
    header('Location: ../index.php?login=erro');
    exit();
}

header('Location: ../index.php');
exit();
?>
