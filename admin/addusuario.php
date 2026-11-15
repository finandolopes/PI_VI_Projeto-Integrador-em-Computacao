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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenValido = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    if (!$tokenValido) {
        $_SESSION['form_error'] = 'Token inválido. Atualize a página e tente novamente.';
        header('Location: addusuario.php');
        exit();
    }

    $nome = (string) ($_POST['nome'] ?? '');
    $usuario = (string) ($_POST['usuario'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');
    $email = (string) ($_POST['email'] ?? '');
    $telefone = (string) ($_POST['telefone'] ?? '');
    $perfil = (string) ($_POST['perfil'] ?? 'usuario');

    $imgPerfilPath = '';
    if (isset($_FILES['img_perfil']) && (int) ($_FILES['img_perfil']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmpFile = (string) $_FILES['img_perfil']['tmp_name'];
        $original = (string) $_FILES['img_perfil']['name'];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $permitidos, true)) {
            $novoNome = 'perfil_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destinoRel = 'assets/img/perfil/' . $novoNome;
            $destinoAbs = __DIR__ . '/' . $destinoRel;

            if (@move_uploaded_file($tmpFile, $destinoAbs)) {
                $imgPerfilPath = $destinoRel;
            }
        }
    }

    $resultado = criarUsuario($conexao, $nome, $usuario, $senha, $email, $perfil, $telefone, $imgPerfilPath);
    if ($resultado['sucesso']) {
        $_SESSION['form_success'] = $resultado['mensagem'];
        header('Location: listarusuario.php');
        exit();
    }

    $_SESSION['form_error'] = $resultado['mensagem'];
    header('Location: addusuario.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Novo usuário">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Novo usuário</title>
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
                    <h3 class="page-title">Novo usuário</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="listarusuario.php">Usuários</a></li>
                        <li class="breadcrumb-item active">Novo usuário</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" required minlength="3">
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" name="usuario" required minlength="3">
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control" name="telefone" maxlength="20">
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Senha</label>
                            <input type="password" class="form-control" name="senha" required minlength="8">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Perfil</label>
                            <select class="form-select" name="perfil">
                                <option value="usuario">Usuário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Imagem de perfil</label>
                            <input type="file" class="form-control" name="img_perfil" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save me-1" aria-hidden="true"></i>Salvar
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
