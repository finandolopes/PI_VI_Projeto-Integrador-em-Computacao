<?php
require_once __DIR__ . '/../php/sd_authorization.php';
sdRequireRole('admin');
?>
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once('../php/conexao.php');

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Faça login para acessar o painel administrativo.';
    header('Location: ../index.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderar_depoimento'], $_POST['id_depoimento'])) {
    $tokenValido = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    if (!$tokenValido) {
        $_SESSION['form_error'] = 'Token inválido. Atualize a página e tente novamente.';
        header('Location: mod_depoimentos.php');
        exit();
    }

    $idDepoimento = (int) $_POST['id_depoimento'];
    $acao = (string) $_POST['moderar_depoimento'];
    $moderadorId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    if (!in_array($acao, ['aprovado', 'reprovado'], true)) {
        $_SESSION['form_error'] = 'Ação de moderação inválida.';
        header('Location: mod_depoimentos.php');
        exit();
    }

    $status   = $acao === 'aprovado' ? 'aprovado' : 'reprovado';
    $aprovado = $acao === 'aprovado' ? 1 : 0;
    $reprovado = $acao === 'reprovado' ? 1 : 0;

    $sqlUpdate = 'UPDATE depoimentos SET status_mod = ?, status = ?, aprovado = ?, reprovado = ?, data_moderacao = NOW(), moderador_id = ? WHERE id = ? LIMIT 1';
    $stmtUpdate = $conexao->prepare($sqlUpdate);
    if ($stmtUpdate) {
        $stmtUpdate->bind_param('ssiiii', $status, $status, $aprovado, $reprovado, $moderadorId, $idDepoimento);
        $stmtUpdate->execute();
        $affected = $stmtUpdate->affected_rows;
        $stmtUpdate->close();

        if ($affected > 0) {
            $_SESSION['form_success'] = 'Depoimento moderado com sucesso.';
        } else {
            $_SESSION['form_error'] = 'Não foi possível moderar o depoimento selecionado.';
        }
    } else {
        $_SESSION['form_error'] = 'Erro interno ao preparar moderação.';
    }

    header('Location: mod_depoimentos.php');
    exit();
}

$sql = "SELECT id, COALESCE(NULLIF(nome, ''), NULLIF(nome_cliente, ''), 'Anônimo') AS nome_exibicao, mensagem, data_envio, status_mod FROM depoimentos WHERE status_mod = 'pendente' OR status = 'pendente' OR (aprovado = 0 AND reprovado = 0) ORDER BY data_envio DESC";
$result = $conexao->query($sql);

$formSuccess = $_SESSION['form_success'] ?? null;
$formError   = $_SESSION['form_error']   ?? null;
unset($_SESSION['form_success'], $_SESSION['form_error']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Moderação de depoimentos</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
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
                        <h3 class="page-title">Moderação de depoimentos</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Depoimentos</li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($formSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($formSuccess, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($formError): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Depoimentos pendentes</h4>
                    <span class="badge bg-light text-dark"><?php echo $result ? (int) $result->num_rows : 0; ?> pendentes</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table datanew">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Mensagem</th>
                                    <th>Data de envio</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0) : ?>
                                    <?php while ($row = $result->fetch_assoc()) : ?>
                                        <tr>
                                            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($row['nome_exibicao'] ?? 'Anônimo'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="max-width: 460px;">
                                                <?php echo htmlspecialchars((string) ($row['mensagem'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) ($row['data_envio'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end">
                                                <form method="post" class="d-inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="id_depoimento" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                                    <button type="submit" name="moderar_depoimento" value="aprovado" class="btn btn-success btn-sm">
                                                        <i class="fa fa-check me-1" aria-hidden="true"></i>Aprovar
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline-block">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="id_depoimento" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                                    <button type="submit" name="moderar_depoimento" value="reprovado" class="btn btn-danger btn-sm">
                                                        <i class="fa fa-times me-1" aria-hidden="true"></i>Reprovar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Nenhum depoimento pendente de moderação.</td>
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


