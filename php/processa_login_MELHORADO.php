<?php
/**
 * ========================================
 * processa_login.php - VERSÃO MELHORADA
 * Autenticação segura com rate limiting
 * ========================================
 */

session_start();

// Inclua o arquivo de conexão
include_once('conexao.php');
include_once('rate_limiter.php');
include_once('auditoria.php');

// Inicializar componentes
$rate_limiter = new RateLimiter($conexao);
$auditoria = new Auditoria($conexao);

// Rate limit: 5 tentativas em 10 minutos
$rate_check = $rate_limiter->verificar('login_frontend', 5, 600);

if (!$rate_check['permitido']) {
    $_SESSION['form_error'] = 'Muitas tentativas de login. Tente novamente em ' . 
                              intval($rate_check['tempo_bloqueio']) . ' segundos.';
    header('Location: ../index.php?login=limite');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CSRF token
    if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Tentativa de ataque CSRF: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['form_error'] = 'Token de segurança inválido. Tente novamente.';
        header('Location: ../index.php');
        exit();
    }
    
    // Verificar se os campos de usuário e senha foram preenchidos
    if (isset($_POST['usuario']) && isset($_POST['senha'])) {
        $user = trim($_POST['usuario']);
        $senha = $_POST['senha'];

        // Validação básica de entrada
        if (strlen($user) < 3 || strlen($user) > 50) {
            $_SESSION['form_error'] = 'Usuário inválido';
            $auditoria->login($user, 'falha_validacao');
            header('Location: ../index.php');
            exit();
        }

        if (strlen($senha) < 8) {
            $_SESSION['form_error'] = 'Senha inválida';
            header('Location: ../index.php');
            exit();
        }

        // Consulta SQL preparada para buscar o usuário
        $stmt = $conexao->prepare("SELECT id_usuario, usuario, senha, nome, email FROM usuarios WHERE usuario = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $user);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                $hash = $row['senha'];
                $isValid = false;
                $rehash = false;

                // Verificar com bcrypt
                if (password_verify($senha, $hash)) {
                    $isValid = true;
                } 
                // Verificar com MD5 legado (apenas para migração)
                elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && hash_equals($hash, md5($senha))) {
                    $isValid = true;
                    $rehash = true; // Marcar para re-hash
                } 
                // Suporte a senhas em texto plano (APENAS durante transição - REMOVER APÓS MIGRAÇÃO)
                elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && hash_equals($hash, $senha)) {
                    $isValid = true;
                    $rehash = true; // Marcar para re-hash
                }

                if ($isValid) {
                    // Se a senha precisa ser re-hasheada, fazer isso agora
                    if ($rehash) {
                        $novo_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmtUpdate = $conexao->prepare("UPDATE usuarios SET senha = ? WHERE id_usuario = ?");
                        if ($stmtUpdate) {
                            $stmtUpdate->bind_param("si", $novo_hash, $row['id_usuario']);
                            $stmtUpdate->execute();
                            $stmtUpdate->close();
                        }
                    }

                    // Regenerar ID de sessão para evitar session fixation
                    session_regenerate_id(true);
                    
                    // Definir dados de sessão
                    $_SESSION['username'] = $user;
                    $_SESSION['user_id'] = $row['id_usuario'];
                    $_SESSION['nome'] = $row['nome'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'];
                    
                    // Registrar login bem-sucedido
                    $auditoria->login($user, 'sucesso', $row['id_usuario']);
                    error_log("Login bem-sucedido: " . $user . " (" . $_SERVER['REMOTE_ADDR'] . ")");
                    
                    // Limpar rate limit após sucesso
                    $rate_limiter->limparIP('login_frontend');
                    
                    // Redirecionar para página interna
                    header('Location: admin/admin.php');
                    exit();
                } else {
                    // Senha incorreta
                    $_SESSION['form_error'] = 'Usuário ou senha incorretos';
                    $auditoria->login($user, 'falha_senha', $row['id_usuario']);
                    error_log("Tentativa de login falhada: " . $user . " (" . $_SERVER['REMOTE_ADDR'] . ")");
                }
            } else {
                // Usuário não encontrado
                $_SESSION['form_error'] = 'Usuário ou senha incorretos';
                error_log("Tentativa de login com usuário inexistente: " . $user . " (" . $_SERVER['REMOTE_ADDR'] . ")");
                
                // Simular tempo de processamento para evitar timing attacks
                usleep(rand(100000, 500000));
            }

            // Redirecionar de volta para login com erro
            header('Location: ../index.php');
            exit();
        } else {
            error_log("Erro ao preparar consulta: " . $conexao->error);
            $_SESSION['form_error'] = 'Erro no servidor. Tente novamente.';
            header('Location: ../index.php');
            exit();
        }
    } else {
        $_SESSION['form_error'] = 'Preencha todos os campos';
        header('Location: ../index.php');
        exit();
    }
} else {
    // Se não for POST, redirecionar
    header('Location: ../index.php');
    exit();
}

?>
