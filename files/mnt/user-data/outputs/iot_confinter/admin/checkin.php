<?php
/**
 * CONFINTER - Admin: Painel de Check-ins Biométricos (IoT)
 * Salvar em: admin/checkin.php
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once '../php/conexao.php';
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit();
}

// Ação: confirmar check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json');
    if ($_POST['acao'] === 'confirmar' && !empty($_POST['token'])) {
        $token     = $conexao->real_escape_string($_POST['token']);
        $atendente = $conexao->real_escape_string($_SESSION['username'] ?? 'Admin');
        $hora      = date('H:i');
        $sql = "UPDATE iot_checkins SET status='confirmado', atendente='$atendente', hora_confirmacao='$hora' WHERE token='$token'";
        $ok = $conexao->query($sql);
        echo json_encode(['ok' => (bool)$ok]);
        exit();
    }
}

// Buscar check-ins de hoje
$sqlCheckins = "
    SELECT * FROM iot_checkins
    WHERE DATE(criado_em) = CURDATE()
    ORDER BY criado_em DESC
    LIMIT 50
";
$resCheckins = $conexao->query($sqlCheckins);
$checkins = [];
if ($resCheckins) while ($r = $resCheckins->fetch_assoc()) $checkins[] = $r;

$totalHoje   = count($checkins);
$confirmados = count(array_filter($checkins, fn($c) => $c['status'] === 'confirmado'));
$pendentes   = $totalHoje - $confirmados;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONFINTER Admin — Check-ins IoT</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --azul:#0f4c81; --verde:#00c896; --amarelo:#f0a202; }

        .iot-header {
            background: linear-gradient(135deg, #07192e 0%, #0f4c81 100%);
            border-radius: 16px; color: #fff;
            padding: 1.5rem 2rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
        }
        .iot-header h3 { margin:0; font-size:1.4rem; }
        .badge-bio {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(59,130,246,.2); border: 1px solid rgba(59,130,246,.5);
            color: #93c5fd; border-radius: 999px;
            padding: .35rem .9rem; font-size: .75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .1em;
        }
        .dot-live { width:8px;height:8px;background:#93c5fd;border-radius:50%;animation:pulse-b 1.4s infinite; }
        @keyframes pulse-b { 0%,100%{opacity:1}50%{opacity:.2} }

        .stat-card {
            border: none; border-radius: 14px;
            box-shadow: 0 4px 20px rgba(15,76,129,.08);
            padding: 1.2rem 1.5rem;
        }
        .stat-card .n { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .stat-card .l { font-size: .72rem; color: #8f9fb3; text-transform: uppercase; letter-spacing: .1em; margin-top: .3rem; }

        /* Tabela check-ins */
        .checkin-card {
            border: none; border-radius: 16px;
            box-shadow: 0 4px 24px rgba(15,76,129,.08);
        }
        .status-pendente  { background: #fff3cd; color: #c88000; }
        .status-confirmado{ background: #e0f7f0; color: #00a87a; }

        .btn-confirmar {
            background: linear-gradient(135deg, #00c896, #00a87a);
            color: #fff; border: none;
            border-radius: 8px; padding: .4rem .9rem;
            font-size: .8rem; font-weight: 700;
            cursor: pointer; transition: all .2s;
            white-space: nowrap;
        }
        .btn-confirmar:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,200,150,.4); }
        .btn-confirmar:disabled { background: #ccc; transform: none; box-shadow: none; cursor: not-allowed; }

        .link-terminal {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
            color: #fff; border-radius: 10px;
            padding: .5rem 1rem; font-size: .85rem; font-weight: 600;
            text-decoration: none; transition: background .2s;
        }
        .link-terminal:hover { background: rgba(255,255,255,.2); color: #fff; }

        .pulse-row { animation: rowFlash .8s ease; }
        @keyframes rowFlash { 0%,100%{background:transparent} 50%{background:rgba(0,200,150,.12)} }

        /* QR preview */
        .qr-preview-wrap {
            display: flex; flex-direction: column; align-items: center;
            font-size: .65rem; color: #8f9fb3;
        }
        .qr-mini {
            width: 50px; height: 50px;
            background: #eee; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; color: #bbb;
            margin-bottom: .25rem;
            overflow: hidden;
        }
        .qr-mini svg { width: 50px; height: 50px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="page-wrapper">
<div class="content container-fluid">

    <!-- Header -->
    <div class="iot-header">
        <div>
            <h3><i class="fa fa-fingerprint me-2"></i>Terminal de Biometria — Check-ins IoT</h3>
            <small style="opacity:.8">Leitura de QR codes via terminal simulado · Identidade por token único</small>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div class="badge-bio"><span class="dot-live"></span>Leitor QR Ativo</div>
            <a href="../checkin.php" target="_blank" class="link-terminal">
                <i class="fa fa-qrcode"></i> Abrir Terminal
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card" style="background:#f0f7ff;">
                <div class="n" style="color:var(--azul);" id="statTotal"><?php echo $totalHoje; ?></div>
                <div class="l">Check-ins hoje</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="background:#e0f7f0;">
                <div class="n" style="color:#00a87a;" id="statConfirmados"><?php echo $confirmados; ?></div>
                <div class="l">Confirmados</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="background:#fff3cd;">
                <div class="n" style="color:#c88000;" id="statPendentes"><?php echo $pendentes; ?></div>
                <div class="l">Aguardando confirmação</div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card checkin-card">
        <div class="card-header bg-white border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Check-ins de Hoje — Atualiza automaticamente</h6>
            <span class="badge bg-dark text-white" style="font-size:.7rem;" id="badgeLive">
                <span class="dot-live" style="background:#00ffc2;"></span> Live
            </span>
        </div>
        <div class="card-body px-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaCheckins">
                    <thead>
                        <tr>
                            <th class="ps-4">Token</th>
                            <th>Nome</th>
                            <th>CPF (mascarado)</th>
                            <th>Finalidade</th>
                            <th>Horário</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="corpoTabela">
                        <?php if (empty($checkins)): ?>
                        <tr id="rowVazio">
                            <td colspan="7" class="text-center text-muted py-4">Nenhum check-in registrado hoje ainda.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($checkins as $c):
                            $cpfMask = strlen($c['cpf']) >= 11
                                ? '***.' . substr($c['cpf'], 3, 3) . '.***-**'
                                : $c['cpf'];
                            $statusCls = $c['status'] === 'confirmado' ? 'status-confirmado' : 'status-pendente';
                            $statusTxt = $c['status'] === 'confirmado' ? '✓ Confirmado' : '⏳ Aguardando';
                            $btnDis    = $c['status'] === 'confirmado' ? 'disabled' : '';
                        ?>
                        <tr id="row-<?php echo htmlspecialchars($c['token']); ?>">
                            <td class="ps-4">
                                <code style="background:#f0f7ff;color:#0f4c81;padding:.2rem .5rem;border-radius:6px;font-size:.8rem;">
                                    <?php echo htmlspecialchars($c['token']); ?>
                                </code>
                            </td>
                            <td><?php echo htmlspecialchars($c['nome']); ?></td>
                            <td><?php echo htmlspecialchars($cpfMask); ?></td>
                            <td><?php echo htmlspecialchars($c['finalidade']); ?></td>
                            <td><?php echo date('H:i', strtotime($c['criado_em'])); ?></td>
                            <td>
                                <span class="badge <?php echo $statusCls; ?>" id="status-<?php echo htmlspecialchars($c['token']); ?>">
                                    <?php echo $statusTxt; ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn-confirmar" <?php echo $btnDis; ?>
                                    onclick="confirmarCheckin('<?php echo htmlspecialchars($c['token']); ?>', this)">
                                    <i class="fa fa-check me-1"></i>Confirmar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
    let ultimoCheckinId = <?php echo empty($checkins) ? 0 : (int)$checkins[0]['id']; ?>;

    // ── Confirmar check-in ──
    function confirmarCheckin(token, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>...';

        fetch('checkin.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: `acao=confirmar&token=${encodeURIComponent(token)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const badge = document.getElementById('status-' + token);
                if (badge) {
                    badge.className = 'badge status-confirmado';
                    badge.textContent = '✓ Confirmado';
                }
                btn.innerHTML = '✓ Confirmado';

                // Atualizar contadores
                const conf = document.getElementById('statConfirmados');
                const pend = document.getElementById('statPendentes');
                conf.textContent = parseInt(conf.textContent) + 1;
                pend.textContent = Math.max(0, parseInt(pend.textContent) - 1);

                // Flash na linha
                const row = document.getElementById('row-' + token);
                if (row) row.classList.add('pulse-row');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check me-1"></i>Confirmar';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check me-1"></i>Confirmar';
        });
    }

    // ── Polling: novos check-ins ──
    function pollCheckins() {
        fetch(`checkin_poll.php?ultimo_id=${ultimoCheckinId}&t=${Date.now()}`)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || !data.novos || data.novos.length === 0) return;

                const tbody = document.getElementById('corpoTabela');
                const rowVazio = document.getElementById('rowVazio');
                if (rowVazio) rowVazio.remove();

                data.novos.forEach(c => {
                    if (document.getElementById('row-' + c.token)) return;

                    if (c.id > ultimoCheckinId) ultimoCheckinId = c.id;

                    const cpfMask = c.cpf.length >= 11
                        ? '***.' + c.cpf.slice(3,6) + '.***-**'
                        : c.cpf;

                    const tr = document.createElement('tr');
                    tr.id = 'row-' + c.token;
                    tr.className = 'pulse-row';
                    tr.innerHTML = `
                        <td class="ps-4">
                            <code style="background:#f0f7ff;color:#0f4c81;padding:.2rem .5rem;border-radius:6px;font-size:.8rem;">${c.token}</code>
                        </td>
                        <td>${c.nome}</td>
                        <td>${cpfMask}</td>
                        <td>${c.finalidade}</td>
                        <td>${c.hora}</td>
                        <td><span class="badge status-pendente" id="status-${c.token}">⏳ Aguardando</span></td>
                        <td class="text-end pe-4">
                            <button class="btn-confirmar" onclick="confirmarCheckin('${c.token}', this)">
                                <i class="fa fa-check me-1"></i>Confirmar
                            </button>
                        </td>
                    `;
                    tbody.insertBefore(tr, tbody.firstChild);

                    // Atualizar contadores
                    document.getElementById('statTotal').textContent =
                        parseInt(document.getElementById('statTotal').textContent) + 1;
                    document.getElementById('statPendentes').textContent =
                        parseInt(document.getElementById('statPendentes').textContent) + 1;
                });
            })
            .catch(() => {});
    }

    setInterval(pollCheckins, 5000);
</script>
</body>
</html>