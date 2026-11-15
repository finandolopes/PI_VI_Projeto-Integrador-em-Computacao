<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once "../php/conexao.php";
if (empty($_SESSION["username"]) && empty($_SESSION["user_id"])) {
    header("Location: ../index.php"); exit();
}
$res = mysqli_query($conexao, "SELECT numero_chamado, total_espera, tempo_medio FROM iot_fila WHERE id = 1");
$fila = $res ? mysqli_fetch_assoc($res) : ["numero_chamado"=>0,"total_espera"=>0,"tempo_medio"=>5];
$resHist = mysqli_query($conexao, "SELECT numero_senha, evento, DATE_FORMAT(criado_em,\"%H:%i:%s\") AS hora FROM iot_fila_historico ORDER BY id DESC LIMIT 20");
$historico = [];
if ($resHist) while ($r = mysqli_fetch_assoc($resHist)) $historico[] = $r;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<meta name="robots" content="noindex, nofollow">
<title>CONFINTER | Painel da Fila</title>
<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.fila-numero { font-size: 6rem; font-weight: 900; color: #0d6efd; line-height: 1; }
.fila-card { border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
.badge-evento-chamado { background: #198754; }
.badge-evento-senha_retirada { background: #0d6efd; }
.hist-table td { font-size:.875rem; }
.btn-chamar { font-size: 1.25rem; padding: .75rem 2.5rem; border-radius: 50px; }
</style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">

        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title"><i class="fa fa-ticket-alt text-primary me-2"></i>Painel da Fila IoT</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Fila IoT</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <a href="../totem.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tv me-1"></i>Ver Totem
                    </a>
                </div>
            </div>
        </div>

        <!-- Cards de status -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card fila-card text-center p-4">
                    <div class="text-muted small mb-1">NÚMERO ATUAL</div>
                    <div class="fila-numero" id="numero-atual"><?php echo (int)$fila["numero_chamado"]; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card fila-card text-center p-4">
                    <div class="text-muted small mb-1">NA FILA</div>
                    <div class="fila-numero" id="total-espera" style="color:#fd7e14"><?php echo (int)$fila["total_espera"]; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card fila-card text-center p-4">
                    <div class="text-muted small mb-1">TEMPO MÉDIO (min)</div>
                    <div class="fila-numero" id="tempo-medio" style="color:#198754"><?php echo (int)$fila["tempo_medio"]; ?></div>
                </div>
            </div>
        </div>

        <!-- Botão chamar próximo -->
        <div class="text-center mb-4">
            <button class="btn btn-primary btn-chamar" id="btn-chamar" onclick="chamarProximo()">
                <i class="fa fa-bullhorn me-2"></i>CHAMAR PRÓXIMO
            </button>
            <div id="msg-chamada" class="mt-2 text-success fw-bold" style="font-size:1.1rem;min-height:1.5rem;"></div>
        </div>

        <!-- Histórico -->
        <div class="card fila-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fa fa-history me-2"></i>Histórico Recente</span>
                <span class="badge bg-secondary" id="badge-hist"><?php echo count($historico); ?> registros</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 hist-table">
                    <thead class="table-light"><tr><th>Hora</th><th>Senha</th><th>Evento</th></tr></thead>
                    <tbody id="tbody-hist">
                        <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($h["hora"]); ?></td>
                            <td><strong>#<?php echo (int)$h["numero_senha"]; ?></strong></td>
                            <td><span class="badge badge-evento-<?php echo htmlspecialchars($h["evento"]); ?>">
                                <?php echo $h["evento"] === "chamado" ? "Chamado" : "Senha Retirada"; ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>

<script>
function chamarProximo() {
    const btn = document.getElementById("btn-chamar");
    btn.disabled = true;
    btn.innerHTML = "<i class='fa fa-spinner fa-spin me-2'></i>Chamando...";
    fetch("../php/iot_chamar_proximo.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        credentials: "same-origin"
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            document.getElementById("numero-atual").textContent = data.numero_chamado;
            document.getElementById("total-espera").textContent = data.total_espera;
            document.getElementById("msg-chamada").textContent = "Senha #" + data.numero_chamado + " chamada!";
            setTimeout(() => document.getElementById("msg-chamada").textContent = "", 4000);
            atualizarHistorico();
        }
    })
    .catch(() => {})
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = "<i class='fa fa-bullhorn me-2'></i>CHAMAR PRÓXIMO";
    });
}

function atualizarHistorico() {
    fetch("../php/iot_fila_status.php", {credentials:"same-origin"})
    .then(r => r.json())
    .then(d => {
        document.getElementById("numero-atual").textContent = d.numero_chamado;
        document.getElementById("total-espera").textContent = d.total_espera;
        document.getElementById("tempo-medio").textContent  = d.tempo_medio;
    }).catch(()=>{});
}

setInterval(atualizarHistorico, 5000);
</script>
</body>
</html>
