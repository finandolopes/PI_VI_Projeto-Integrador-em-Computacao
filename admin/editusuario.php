<?php
require_once __DIR__ . '/../php/sd_authorization.php';
sdRequireRole('admin');
?>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once '../php/conexao.php';
include_once '../php/funcoes_usuarios.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: listarusuario.php');
    exit();
}

$usuario = buscarUsuarioPorId($conexao, $id);
if (!$usuario) {
    $_SESSION['form_error'] = 'Usuário não encontrado.';
    header('Location: listarusuario.php');
    exit();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
    $tokenValido = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    if (!$tokenValido) {
        $error = 'Token inválido. Atualize a página e tente novamente.';
    } else {
        $nome = (string) ($_POST['nome'] ?? '');
        $login = (string) ($_POST['usuario'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $telefone = (string) ($_POST['telefone'] ?? '');
        $perfil = (string) ($_POST['perfil'] ?? 'usuario');
        $senha = isset($_POST['senha']) && trim((string) $_POST['senha']) !== '' ? (string) $_POST['senha'] : null;

        $resultado = editarUsuario($conexao, $id, $nome, $login, $senha, $email, $perfil, $telefone, (string) ($usuario['img_perfil'] ?? ''));
        if ($resultado['sucesso']) {
            $_SESSION['form_success'] = $resultado['mensagem'];
            header('Location: listarusuario.php');
            exit();
        }

        $error = $resultado['mensagem'];
        $usuario = buscarUsuarioPorId($conexao, $id) ?: $usuario;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Edição de usuário">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Editar usuário</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title">Editar usuário</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="listarusuario.php">Usuários</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) ($usuario['id'] ?? 0); ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nome">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars((string) ($usuario['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="usuario">Usuário</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars((string) ($usuario['usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="telefone">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars((string) ($usuario['telefone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="perfil">Perfil</label>
                            <select class="form-select" id="perfil" name="perfil">
                                <option value="usuario" <?php echo (($usuario['perfil'] ?? '') === 'usuario') ? 'selected' : ''; ?>>Usuário</option>
                                <option value="admin" <?php echo (($usuario['perfil'] ?? '') === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="senha">Nova senha (opcional)</label>
                            <input type="password" class="form-control" id="senha" name="senha" minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" name="editar_usuario" class="btn btn-primary">
                            <i class="fa fa-save me-1" aria-hidden="true"></i>Salvar alterações
                        </button>
                        <a href="listarusuario.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
