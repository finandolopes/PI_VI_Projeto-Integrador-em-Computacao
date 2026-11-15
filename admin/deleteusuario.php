<?php
require_once __DIR__ . '/../php/sd_authorization.php';
sdRequireRole('admin');
?>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once('../php/conexao.php');
include_once('../php/funcoes_usuarios.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido. Utilize a lista de usuários para excluir.');
}
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$token = (string) ($_POST['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
    $_SESSION['form_error'] = 'Token invÃ¡lido para exclusÃ£o.';
    header('Location: listarusuario.php');
    exit();
}

$resultado = excluirUsuario($conexao, $id);
if ($resultado['sucesso']) {
    $_SESSION['form_success'] = $resultado['mensagem'];
} else {
    $_SESSION['form_error'] = $resultado['mensagem'];
}

header('Location: listarusuario.php');
exit();
?>
