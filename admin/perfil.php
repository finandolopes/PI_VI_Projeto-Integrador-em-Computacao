<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once '../php/conexao.php';

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Faça login para acessar o perfil.';
    header('Location: ../index.php');
    exit();
}

function fetchProfileRow(mysqli $conexao, string $sql, ?array $bind = null): ?array
{
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($bind !== null) {
        $stmt->bind_param(...$bind);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

$username = (string) ($_SESSION['username'] ?? '');
$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$usuario = null;

if ($userId > 0) {
    $usuario = fetchProfileRow($conexao, 'SELECT * FROM usuarios WHERE id = ? LIMIT 1', ['i', $userId]);
}

if (!$usuario && $username !== '') {
    $usuario = fetchProfileRow($conexao, 'SELECT * FROM usuarios WHERE usuario = ? LIMIT 1', ['s', $username]);
}

if (!$usuario && $username !== '') {
    $usuario = fetchProfileRow($conexao, 'SELECT * FROM adm WHERE usuario = ? LIMIT 1', ['s', $username]);
}

if (!$usuario) {
    $_SESSION['form_error'] = 'Não foi possível carregar os dados do perfil.';
    header('Location: admin.php');
    exit();
}

$nome = (string) ($usuario['nome'] ?? $username ?? 'Usuário');
$email = (string) ($usuario['email'] ?? '');
$login = (string) ($usuario['usuario'] ?? $username);
$perfil = (string) ($usuario['perfil'] ?? ($_SESSION['perfil'] ?? 'usuário'));
$createdAt = (string) ($usuario['data_cadastro'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Perfil do usuário">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Perfil</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .profile-header-card {
            border: 0;
            border-radius: 1rem;
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: #fff;
        }
        .profile-avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.45);
        }
        .readonly-input {
            background-color: #f8f9fa;
        }
    </style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Meu perfil</h4>
                <h6>Dados da conta logada</h6>
            </div>
        </div>

        <div class="card profile-header-card mb-4">
            <div class="card-body d-flex align-items-center gap-3">
                <img src="assets/img/perfil/1.png" class="profile-avatar" alt="Foto de perfil">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <p class="mb-1"><?php echo htmlspecialchars($email !== '' ? $email : 'E-mail não informado', ENT_QUOTES, 'UTF-8'); ?></p>
                    <span class="badge bg-light text-primary text-uppercase"><?php echo htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control readonly-input" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Login</label>
                        <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($login, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-control readonly-input" value="********" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Perfil</label>
                        <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cadastro</label>
                        <input type="text" class="form-control readonly-input" value="<?php echo htmlspecialchars($createdAt !== '' ? $createdAt : '-', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <a href="admin.php" class="btn btn-primary">
                        <i class="fa-solid fa-house me-1"></i> Voltar ao painel
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Encerrar sessão
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
