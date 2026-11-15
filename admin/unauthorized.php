<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso nÃ£o autorizado</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background: #f4f7fb;
        }
        .card {
            max-width: 520px;
            margin: 12vh auto 0;
            border-radius: 14px;
            border: 1px solid #d7deea;
            box-shadow: 0 14px 30px -28px rgba(15, 23, 42, 0.8);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-body p-4 p-md-5 text-center">
            <h1 class="h4 mb-3">Acesso nÃ£o autorizado</h1>
            <p class="text-muted mb-4">VocÃª nÃ£o possui permissÃ£o para acessar esta funcionalidade.</p>
            <a href="admin.php" class="btn btn-primary">Voltar ao dashboard</a>
        </div>
    </div>
</body>
</html>

