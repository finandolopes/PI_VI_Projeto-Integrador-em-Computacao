<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once '../php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    $sqlExport = 'SELECT id_cliente, nome, email, telefone, tipo, status, data_cadastro FROM clientes ORDER BY id_cliente DESC';
    $resultExport = $conexao->query($sqlExport);

    $xml = new SimpleXMLElement('<clientes></clientes>');
    if ($resultExport) {
        while ($row = $resultExport->fetch_assoc()) {
            $cliente = $xml->addChild('cliente');
            $cliente->addChild('id', (string) ($row['id_cliente'] ?? ''));
            $cliente->addChild('nome', htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $cliente->addChild('email', htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $cliente->addChild('telefone', htmlspecialchars((string) ($row['telefone'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $cliente->addChild('tipo', htmlspecialchars((string) ($row['tipo'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $cliente->addChild('status', htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $cliente->addChild('data_cadastro', htmlspecialchars((string) ($row['data_cadastro'] ?? ''), ENT_QUOTES, 'UTF-8'));
        }
    }

    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="clientes.xml"');
    echo $xml->asXML();
    exit();
}

$sql = 'SELECT id_cliente, nome, email, telefone, tipo, status, data_cadastro FROM clientes ORDER BY id_cliente DESC';
$result = $conexao->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Lista de clientes">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Clientes</title>
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
                    <h3 class="page-title">Lista de clientes</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Clientes</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <form method="post" class="d-inline-block">
                        <button type="submit" name="exportar" class="btn btn-primary btn-sm">
                            <i class="fa fa-file-export me-1" aria-hidden="true"></i>Exportar XML
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="card-title mb-0">Clientes cadastrados</h4>
                <span class="badge bg-light text-dark"><?php echo $result ? (int) $result->num_rows : 0; ?> registros</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Cadastro</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo (int) ($row['id_cliente'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['telefone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) strtoupper((string) ($row['tipo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ucfirst((string) ($row['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($row['data_cadastro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nenhum cliente encontrado.</td>
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
