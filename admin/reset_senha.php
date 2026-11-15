<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../php/verifica_login.php';
require_once '../php/conexao.php';

verificarLoginAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_senha'])) {
    $tokenValido = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
    if (!$tokenValido) {
        $errorMessage = 'Não foi possível validar a operação. Atualize a página e tente novamente.';
    } else {
        $idReset = isset($_POST['id_reset']) ? (int) $_POST['id_reset'] : 0;
        if ($idReset <= 0) {
            $errorMessage = 'Solicitação inválida.';
        } else {
            $novaSenha = bin2hex(random_bytes(4));
            $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmtReset = $conexao->prepare(
                "SELECT rs.id, rs.id_usuario, u.usuario
                 FROM reset_senha rs
                 INNER JOIN usuarios u ON rs.id_usuario = u.id
                 WHERE rs.id = ? AND rs.status = 'pendente'
                 LIMIT 1"
            );

            if ($stmtReset) {
                $stmtReset->bind_param('i', $idReset);
                $stmtReset->execute();
                $resultReset = $stmtReset->get_result();
                $dadosReset = $resultReset ? $resultReset->fetch_assoc() : null;
                $stmtReset->close();

                if ($dadosReset) {
                    $stmtUser = $conexao->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
                    $stmtHist = $conexao->prepare(
                        "UPDATE reset_senha
                         SET status = 'processado',
                             nova_senha = ?,
                             data_processamento = NOW()
                         WHERE id = ?"
                    );

                    if ($stmtUser && $stmtHist) {
                        $usuarioId = (int) $dadosReset['id_usuario'];
                        $stmtUser->bind_param('si', $senhaHash, $usuarioId);
                        $okUser = $stmtUser->execute();
                        $stmtUser->close();

                        $stmtHist->bind_param('si', $novaSenha, $idReset);
                        $okHist = $stmtHist->execute();
                        $stmtHist->close();

                        if ($okUser && $okHist) {
                            $successMessage = 'Nova senha gerada para o usuário ' . htmlspecialchars((string) $dadosReset['usuario'], ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($novaSenha, ENT_QUOTES, 'UTF-8');
                        } else {
                            $errorMessage = 'Erro ao processar a solicitação.';
                        }
                    } else {
                        if ($stmtUser) $stmtUser->close();
                        if ($stmtHist) $stmtHist->close();
                        $errorMessage = 'Erro interno ao preparar atualização.';
                    }
                } else {
                    $errorMessage = 'Solicitação não encontrada ou já processada.';
                }
            } else {
                $errorMessage = 'Erro interno ao consultar solicitação.';
            }
        }
    }
}

$resultPendentes = $conexao->query(
    "SELECT rs.id, rs.data_solicitacao, u.nome, u.usuario, u.email
     FROM reset_senha rs
     INNER JOIN usuarios u ON rs.id_usuario = u.id
     WHERE rs.status = 'pendente'
     ORDER BY rs.data_solicitacao DESC"
);

$resultHistorico = $conexao->query(
    "SELECT rs.id, rs.nova_senha, rs.data_processamento, u.nome, u.usuario
     FROM reset_senha rs
     INNER JOIN usuarios u ON rs.id_usuario = u.id
     WHERE rs.status = 'processado'
     ORDER BY rs.data_processamento DESC
     LIMIT 15"
);

$totalPendentes = $resultPendentes ? (int) $resultPendentes->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Reset de senha</title>
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
                    <h3 class="page-title">Reset de senha</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reset de senha</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <span class="badge bg-primary"><?php echo $totalPendentes; ?> pendentes</span>
                </div>
            </div>
        </div>

        <?php if ($successMessage !== null) : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== null) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Solicitações pendentes</h5>
            </div>
            <div class="card-body">
                <?php if ($resultPendentes && $resultPendentes->num_rows > 0) : ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Usuário</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $resultPendentes->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $row['data_solicitacao'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="id_reset" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" name="gerar_senha" class="btn btn-success btn-sm" onclick="return confirm('Gerar nova senha para este usuário?');">
                                                    <i class="fa fa-key me-1" aria-hidden="true"></i>Gerar senha
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="text-center py-4">
                        <i class="fa fa-check-circle text-success fa-2x" aria-hidden="true"></i>
                        <p class="text-muted mt-2 mb-0">Nenhuma solicitação pendente no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de resets processados</h5>
            </div>
            <div class="card-body">
                <?php if ($resultHistorico && $resultHistorico->num_rows > 0) : ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Data processamento</th>
                                    <th>Usuário</th>
                                    <th>Nome</th>
                                    <th>Nova senha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $resultHistorico->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) date('d/m/Y H:i', strtotime((string) $row['data_processamento'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code><?php echo htmlspecialchars((string) $row['nova_senha'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p class="text-muted mb-0">Nenhum reset processado até agora.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>


