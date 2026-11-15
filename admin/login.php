<?php
/**
 * ========================================
 * LOGIN ADMIN - Versão Segura
 * Autenticação com bcrypt e rate limiting
 * ========================================
 */

session_start();

// Iniciar CSRF token se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include_once('../php/conexao.php');

// CORRIGIDO: ternário com ? correto
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
    if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Tentativa de ataque CSRF: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $_SESSION['nao_autenticado'] = true;
        // CORRIGIDO: redirect com ? correto
        header('Location: login.php?erro=csrf');
        exit();
    }

    // Verificar rate limit por IP
    $chave_rate = 'admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rate = verificarRateLimitLogin($chave_rate);

    if ($rate['bloqueado']) {
        error_log("Rate limit admin excedido: " . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $_SESSION['nao_autenticado'] = true;
        header('Location: login.php?erro=bloqueado');
        exit();
    }

    $login = trim($_POST['usuario']);
    $senha = $_POST['senha'];

    if (strlen($login) < 3 || strlen($senha) < 3) {
        $_SESSION['nao_autenticado'] = true;
        header('Location: login.php?erro=invalido');
        exit();
    }

    // CORRIGIDO: placeholder ? adicionado na query
    $stmt = $db->prepare("SELECT id_usuario, senha FROM adm WHERE usuario = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();
        // CORRIGIDO: fetch_assoc correto
        $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
        $stmt->close();

        $hash = $row['senha'] ?? '';
        $isValid = false;
        $rehash  = false;

        if ($row && $hash !== '') {
            if (password_verify($senha, $hash)) {
                $isValid = true;
                if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    $rehash = true;
                }
            } elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && strlen($hash) === 32 && hash_equals(strtolower($hash), md5($senha))) {
                $isValid = true;
                $rehash  = true;
            } elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && hash_equals($hash, $senha)) {
                $isValid = true;
                $rehash  = true;
            }
        }

        if ($isValid) {
            if ($rehash) {
                $novo_hash   = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                // CORRIGIDO: placeholders ? adicionados
                $stmtUpdate  = $db->prepare("UPDATE adm SET senha = ? WHERE id_usuario = ?");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("si", $novo_hash, $row['id_usuario']);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
            }

            session_regenerate_id(true);
            $_SESSION['username']            = $login;
            $_SESSION['user_id']             = $row['id_usuario'];
            $_SESSION['usuario']             = $login;
            $_SESSION['usuario_id']          = $row['id_usuario'];
            $_SESSION['login_time']          = time();
            $_SESSION['is_admin']            = true;
            $_SESSION['perfil']              = 'admin';
            $_SESSION['user_agent']          = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
            $_SESSION['last_security_check'] = time();

            // Limpar rate limit após login bem-sucedido
            $arquivo_rate = sys_get_temp_dir() . '/rate_limit_' . md5($chave_rate);
            if (file_exists($arquivo_rate)) {
                unlink($arquivo_rate);
            }

            error_log("Login admin bem-sucedido: " . $login . " (" . ($_SERVER['REMOTE_ADDR'] ?? '') . ")");
            header('Location: admin.php');
            exit();
        } else {
            error_log("Falha de login admin: " . $login . " (" . ($_SERVER['REMOTE_ADDR'] ?? '') . ")");
        }
    } else {
        error_log("Erro ao preparar statement: " . $db->error);
    }

    $_SESSION['nao_autenticado'] = true;
    header('Location: login.php?erro=autenticacao');
    exit();
}

// Se os campos de login não foram enviados via POST
header('Location: ../index.php');
exit();
?>
