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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    $tokenValido = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    if (!$tokenValido) {
        $_SESSION['form_error'] = 'Token inválido. Atualize a página e tente novamente.';
        header('Location: listarusuario.php');
        exit();
    }

    $idExcluir = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $resultado = excluirUsuario($conexao, $idExcluir);

    if ($resultado['sucesso']) {
        $_SESSION['form_success'] = $resultado['mensagem'];
    } else {
        $_SESSION['form_error'] = $resultado['mensagem'];
    }

    header('Location: listarusuario.php');
    exit();
}

$usuarios = listarUsuarios($conexao, 200, 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Gestão de usuários">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Usuários</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
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
                    <h3 class="page-title">Usuários do sistema</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Usuários</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <a href="addusuario.php" class="btn btn-primary btn-sm">
                        <i class="fa fa-user-plus me-1" aria-hidden="true"></i>Novo usuário
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Lista de usuários</h4>
                <span class="badge bg-light text-dark"><?php echo count($usuarios); ?> registros</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Usuário</th>
                                <th>Telefone</th>
                                <th>E-mail</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($usuario['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($usuario['usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($usuario['telefone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ucfirst((string) ($usuario['perfil'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ucfirst((string) ($usuario['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($usuario['data_cadastro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="editusuario.php?id=<?php echo (int) ($usuario['id'] ?? 0); ?>" aria-label="Editar usuário">
                                            <i class="fa fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <form method="post" class="d-inline-block" onsubmit="return confirm('Deseja realmente excluir este usuário?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?php echo (int) ($usuario['id'] ?? 0); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Excluir usuário">
                                                <i class="fa fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Nenhum usuário cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="assets/js/dataTables.min.js"></script>
<script src="assets/js/dataTables.bootstrap4.min.js"></script>
<script>
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDataTable);
    } else {
        initDataTable();
    }

    function initDataTable() {
        if ($.fn.DataTable.isDataTable('.datanew')) {
            return;
        }
        
        $('.datanew').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Portuguese-Brasil.json"
            },
            "pageLength": 25,
            "ordering": true,
            "searching": true,
            "paging": true,
            "info": true
        });
    }
})();
</script>
</body>
</html>
