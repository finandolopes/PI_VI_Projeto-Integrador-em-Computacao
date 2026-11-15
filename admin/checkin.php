<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once '../php/conexao.php';
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit();
}
$res = mysqli_query($conexao,
    "SELECT id, token, nome, cpf, finalidade, confirmado,
            DATE_FORMAT(criado_em,'%d/%m/%Y %H:%i') AS hora
     FROM iot_checkins ORDER BY id DESC LIMIT 50");
$checkins = [];
if ($res) while ($r = mysqli_fetch_assoc($res)) $checkins[] = $r;
$total     = count($checkins);
$pendentes = count(array_filter($checkins, fn($c) => !$c['confirmado']));
$conf      = $total - $pendentes;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<meta name="robots" content="noindex, nofollow">
<title>CONFINTER | Check-in / QR Code</title>
<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
.token-badge{font-family:monospace;font-size:.85rem;letter-spacing:.1em;background:#e9ecef;padding:2px 8px;border-radius:6px}
.cpf-masked{color:#6c757d;font-size:.85rem}
.status-confirmado{color:#198754;font-weight:600}
.status-pendente{color:#fd7e14;font-weight:600}
.live-dot{width:10px;height:10px;border-radius:50%;background:#198754;display:inline-block;animation:pulse 1.5s infinite;margin-right:6px}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
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
                    <h3 class="page-title"><i class="fa fa-qrcode text-primary me-2"></i>Check-in / QR Code</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Check-in</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end d-flex align-items-center justify-content-end gap-2">
                    <span class="live-dot"></span>
                    <span class="text-muted small">Atualização automática</span>
                    <a href="../checkin.php" target="_blank" class="btn btn-outline-secondary btn-sm ms-2">
                        <i class="fa fa-external-link-alt me-1"></i>Terminal público
                    </a>
                </div>
            </div>
        </div>

        <!-- Contadores -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card text-center p-3 border-0 shadow-sm">
                    <div class="text-muted small">TOTAL</div>
                    <div style="font-size:2.5rem;font-weight:900;color:#0d6efd" id="stat-total"><?php echo $total; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3 border-0 shadow-sm">
                    <div class="text-muted small">AGUARDANDO</div>
                    <div style="font-size:2.5rem;font-weight:900;color:#fd7e14" id="stat-pendentes"><?php echo $pendentes; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center p-3 border-0 shadow-sm">
                    <div class="text-muted small">CONFIRMADOS</div>
                    <div style="font-size:2.5rem;font-weight:900;color:#198754" id="stat-conf"><?php echo $conf; ?></div>
                </div>
            </div>
        </div>

        <!-- Tabela -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <span><i class="fa fa-list me-2"></i>Chegadas Registradas</span>
                <span class="badge bg-primary" id="badge-total"><?php echo $total; ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Hora</th><th>Token</th><th>Nome</th><th>CPF</th><th>Finalidade</th><th>Status</th><th>Ação</th></tr>
                        </thead>
                        <tbody id="tbody-checkins">
                            <?php foreach ($checkins as $c):
                                $cpf = preg_replace('/[^0-9]/', '', $c['cpf']);
                                $cpfMask = strlen($cpf) === 11
                                    ? '***.' . substr($cpf,3,3) . '.' . substr($cpf,6,3) . '-**'
                                    : '***';
                            ?>
                            <tr id="row-<?php echo htmlspecialchars($c['token']); ?>">
                                <td class="text-muted small"><?php echo $c['hora']; ?></td>
                                <td><span class="token-badge"><?php echo htmlspecialchars($c['token']); ?></span></td>
                                <td><?php echo htmlspecialchars($c['nome']); ?></td>
                                <td class="cpf-masked"><?php echo $cpfMask; ?></td>
                                <td><?php echo htmlspecialchars($c['finalidade']); ?></td>
                                <td>
                                    <?php if ($c['confirmado']): ?>
                                        <span class="status-confirmado"><i class="fa fa-check-circle me-1"></i>Confirmado</span>
                                    <?php else: ?>
                                        <span class="status-pendente"><i class="fa fa-clock me-1"></i>Aguardando</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$c['confirmado']): ?>
                                    <button class="btn btn-success btn-sm"
                                        onclick="confirmar(this,'<?php echo htmlspecialchars($c['token']); ?>')">
                                        <i class="fa fa-check me-1"></i>Confirmar
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($checkins)): ?>
                            <tr id="row-vazio"><td colspan="7" class="text-center text-muted py-4">Nenhum check-in registrado ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>

<script>
let ultimoId   = <?php echo empty($checkins) ? 0 : (int)$checkins[0]['id']; ?>;
let totalCount = <?php echo $total; ?>;
let pendCount  = <?php echo $pendentes; ?>;
let confCount  = <?php echo $conf; ?>;

function atualizarContadores() {
    document.getElementById('stat-total').textContent     = totalCount;
    document.getElementById('stat-pendentes').textContent = pendCount;
    document.getElementById('stat-conf').textContent      = confCount;
    document.getElementById('badge-total').textContent    = totalCount;
}

function confirmar(btn, token) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    fetch('../php/iot_checkin_confirmar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({token})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            const row = document.getElementById('row-' + token);
            if (row) {
                row.cells[5].innerHTML = '<span class="status-confirmado"><i class="fa fa-check-circle me-1"></i>Confirmado</span>';
                row.cells[6].innerHTML = '<span class="text-muted small">—</span>';
            }
            pendCount = Math.max(0, pendCount - 1);
            confCount++;
            atualizarContadores();
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check me-1"></i>Confirmar';
    });
}

function pollNovos() {
    fetch('checkin_poll.php?ultimo_id=' + ultimoId, {credentials: 'same-origin'})
    .then(r => r.json())
    .then(d => {
        if (!d.novos || d.novos.length === 0) return;
        const tbody = document.getElementById('tbody-checkins');
        const vazio = document.getElementById('row-vazio');
        if (vazio) vazio.remove();
        d.novos.forEach(c => {
            if (document.getElementById('row-' + c.token)) return;
            const cpfMask = (c.cpf || '').length === 11
                ? '***.' + c.cpf.substring(3,6) + '.' + c.cpf.substring(6,9) + '-**' : '***';
            const tr = document.createElement('tr');
            tr.id = 'row-' + c.token;
            tr.innerHTML = `
                <td class="text-muted small">${c.hora}</td>
                <td><span class="token-badge">${c.token}</span></td>
                <td>${c.nome}</td>
                <td class="cpf-masked">${cpfMask}</td>
                <td>${c.finalidade}</td>
                <td><span class="status-pendente"><i class="fa fa-clock me-1"></i>Aguardando</span></td>
                <td><button class="btn btn-success btn-sm" onclick="confirmar(this,'${c.token}')">
                    <i class="fa fa-check me-1"></i>Confirmar</button></td>`;
            tbody.insertBefore(tr, tbody.firstChild);
            ultimoId = Math.max(ultimoId, c.id);
            totalCount++; pendCount++;
        });
        atualizarContadores();
    })
    .catch(() => {});
}

setInterval(pollNovos, 4000);
</script>
</body>
</html>
