<?php
/**
 * ========================================
 * processa_login.php - VERSAO MELHORADA
 * Autenticacao segura com rate limiting
 * ========================================
 */

session_start();

include_once('conexao.php');
include_once('rate_limiter.php');
include_once('auditoria.php');

$rate_limiter = new RateLimiter($conexao);
$auditoria = new Auditoria($conexao);

/**
 * Registra erro de login e redireciona.
 */
function redirectLoginError(string $mensagem, string $destino = '../index.php'): void
{
    $_SESSION['login_error'] = $mensagem;
    header('Location: ' . $destino);
    exit();
}

/**
 * Registra auditoria de login sem interromper o fluxo da autenticacao.
 */
function safeAuditLogin($auditoria, string $usuario, string $resultado, $idUsuario = null): void
{
    try {
        if ($auditoria instanceof Auditoria) {
            $auditoria->login($usuario, $resultado, $idUsuario);
        }
    } catch (Throwable $e) {
        error_log('Falha ao registrar auditoria de login: ' . $e->getMessage());
    }
}

/**
 * Busca usuario em uma tabela de autenticacao.
 */
function buscarUsuarioLogin(mysqli $conexao, string $usuario, string $tabela): ?array
{
    if ($tabela === 'adm') {
        $sql = "SELECT id_usuario, usuario, senha, nome, email, perfil FROM adm WHERE usuario = ? LIMIT 1";
    } else {
        $sql = "SELECT * FROM usuarios WHERE usuario = ? LIMIT 1";
    }

    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

// Rate limit: 5 tentativas em 10 minutos.
$rate_check = $rate_limiter->verificar('login_frontend', 5, 600);
if (!$rate_check['permitido']) {
    redirectLoginError(
        'Muitas tentativas de login. Tente novamente em ' . intval($rate_check['tempo_bloqueio']) . ' segundos.',
        '../index.php?login=limite'
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

if (
    empty($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    error_log('Tentativa de ataque CSRF: ' . ($_SERVER['REMOTE_ADDR'] ?? 'IP_DESCONHECIDO'));
    redirectLoginError('Token de seguranca invalido. Tente novamente.');
}

if (!isset($_POST['usuario'], $_POST['senha'])) {
    redirectLoginError('Preencha todos os campos.');
}

$user = trim((string) $_POST['usuario']);
$senha = (string) $_POST['senha'];

if (strlen($user) < 3 || strlen($user) > 50) {
    safeAuditLogin($auditoria, $user, 'falha_validacao');
    redirectLoginError('Usuario invalido.');
}

// Compatibilidade com contas legadas (ex.: admin/admin).
if (strlen($senha) < 3) {
    redirectLoginError('Senha invalida.');
}

$origem = 'usuarios';
$row = buscarUsuarioLogin($conexao, $user, 'usuarios');

if (!$row) {
    $origem = 'adm';
    $row = buscarUsuarioLogin($conexao, $user, 'adm');
}

if (!$row) {
    safeAuditLogin($auditoria, $user, 'falha_usuario_inexistente');
    error_log('Tentativa de login com usuario inexistente: ' . $user . ' (' . ($_SERVER['REMOTE_ADDR'] ?? 'IP_DESCONHECIDO') . ')');
    usleep(random_int(100000, 500000));
    redirectLoginError('Usuario ou senha incorretos.');
}

$userId = null;
if (isset($row['id_usuario'])) {
    $userId = (int) $row['id_usuario'];
} elseif (isset($row['id'])) {
    $userId = (int) $row['id'];
}

$nomeUsuario = $row['nome'] ?? ($row['nome_usuario'] ?? $user);
$emailUsuario = $row['email'] ?? null;
$perfilUsuario = strtolower((string) ($row['perfil'] ?? ($origem === 'adm' ? 'admin' : 'usuario')));
$hash = (string) ($row['senha'] ?? '');

if ($hash === '') {
    error_log('Hash de senha ausente para usuario: ' . $user);
    redirectLoginError('Erro no servidor. Tente novamente.');
}

$isValid = false;
$rehash = false;

if (password_verify($senha, $hash)) {
    $isValid = true;
    if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
        $rehash = true;
    }
} elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && strlen($hash) === 32 && hash_equals(strtolower($hash), md5($senha))) {
    $isValid = true;
    $rehash = true;
} elseif (filter_var(getenv('ALLOW_LEGACY_PASSWORDS') ?: '0', FILTER_VALIDATE_BOOLEAN) && hash_equals($hash, $senha)) {
    $isValid = true;
    $rehash = true;
}

if (!$isValid) {
    safeAuditLogin($auditoria, $user, 'falha_senha', $userId);
    error_log('Tentativa de login falhada: ' . $user . ' (' . ($_SERVER['REMOTE_ADDR'] ?? 'IP_DESCONHECIDO') . ')');
    redirectLoginError('Usuario ou senha incorretos.');
}

if ($rehash && $userId !== null) {
    $novo_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($origem === 'adm') {
        $stmtUpdate = $conexao->prepare('UPDATE adm SET senha = ? WHERE id_usuario = ?');
    } else {
        $idColumn = array_key_exists('id_usuario', $row) ? 'id_usuario' : 'id';
        $stmtUpdate = $conexao->prepare("UPDATE usuarios SET senha = ? WHERE {$idColumn} = ?");
    }

    if ($stmtUpdate) {
        $stmtUpdate->bind_param('si', $novo_hash, $userId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

session_regenerate_id(true);

$_SESSION['username'] = $user;
$_SESSION['user_id'] = $userId;
$_SESSION['usuario'] = $user;
$_SESSION['usuario_id'] = $userId;
$_SESSION['nome'] = $nomeUsuario;
$_SESSION['email'] = $emailUsuario;
$_SESSION['perfil'] = $perfilUsuario;
$_SESSION['is_admin'] = ($perfilUsuario === 'admin' || $origem === 'adm');
$_SESSION['role'] = $row['role'] ?? ($_SESSION['is_admin'] ? 'admin' : 'viewer');
$_SESSION['login_time'] = time();
$_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
$_SESSION['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
$_SESSION['last_security_check'] = time();

safeAuditLogin($auditoria, $user, 'sucesso', $userId);
error_log('Login bem-sucedido: ' . $user . ' (' . ($_SERVER['REMOTE_ADDR'] ?? 'IP_DESCONHECIDO') . ')');

$rate_limiter->limparIP('login_frontend');

header('Location: ../admin/admin.php');
exit();
?>
