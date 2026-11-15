<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('../php/conexao.php');

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'Faça login para acessar o painel administrativo.';
    header('Location: ../index.php');
    exit();
}

function scalarInt(mysqli $conexao, string $sql): int
{
    $result = mysqli_query($conexao, $sql);
    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

function scalarString(mysqli $conexao, string $sql, string $field): string
{
    $result = mysqli_query($conexao, $sql);
    if (!$result) {
        return '';
    }

    $row = mysqli_fetch_assoc($result);
    return (string) ($row[$field] ?? '');
}

$visitasHoje = scalarInt($conexao, "SELECT COUNT(*) AS total FROM contador_visitas WHERE DATE(data_visita) = CURDATE()");
$requisicoesHoje = scalarInt($conexao, "SELECT COUNT(*) AS total FROM requisicoes WHERE DATE(data_requisicao) = CURDATE()");
$depoimentosPendentes = scalarInt($conexao, "SELECT COUNT(*) AS total FROM depoimentos WHERE status_mod = 'pendente' OR status = 'pendente'");
$tempoMedio = scalarString($conexao, "SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo))) AS media FROM contador_visitas", 'media');
$tempoMedio = $tempoMedio !== '' ? $tempoMedio : '00:00:00';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Monitoramento em Tempo Real">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Monitoramento</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/bootstrap5-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <style>
        .metric-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 10px 28px -24px rgba(15, 37, 70, 0.8);
            height: 100%;
        }
        .metric-card .metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.05rem;
        }
        .metric-card .metric-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0;
            line-height: 1.1;
        }
        .metric-card .metric-label {
            color: #6b7280;
            margin-bottom: 0;
        }
        .metric-icon.blue { background: #0d6efd; }
        .metric-icon.green { background: #198754; }
        .metric-icon.orange { background: #fd7e14; }
        .metric-icon.indigo { background: #6610f2; }
        .metric-icon.cyan { background: #0dcaf0; color: #003347; }
        .metric-icon.red { background: #dc3545; }

        .iot-table td,
        .iot-table th {
            white-space: nowrap;
            vertical-align: middle;
        }

        .chart-wrapper {
            min-height: 320px;
        }
    </style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="page-title">Monitoramento em Tempo Real</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Monitoramento</li>
                    </ul>
                </div>
                <div class="col-sm-5 text-sm-end">
                    <button id="btn-atualizar" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-rotate me-1"></i> Atualizar agora
                    </button>
                </div>
            </div>
        </div>

        <div id="alerts-container" class="mb-3"></div>

        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="visitas-hoje"><?php echo $visitasHoje; ?></p?>
                            <p class="metric-label">Visitas hoje</p>
                        </div>
                        <span class="metric-icon blue"><i class="fa-solid fa-users"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="requisicoes-hoje"><?php echo $requisicoesHoje; ?></p?>
                            <p class="metric-label">Requisições hoje</p>
                        </div>
                        <span class="metric-icon green"><i class="fa-solid fa-file-circle-check"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="depoimentos-pendentes"><?php echo $depoimentosPendentes; ?></p?>
                            <p class="metric-label">Depoimentos pendentes</p>
                        </div>
                        <span class="metric-icon orange"><i class="fa-solid fa-comment-dots"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="tempo-medio"><?php echo htmlspecialchars($tempoMedio, ENT_QUOTES, 'UTF-8'); ?></p?>
                            <p class="metric-label">Tempo médio no site</p>
                        </div>
                        <span class="metric-icon indigo"><i class="fa-solid fa-clock"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="iot-devices-online">0</p>
                            <p class="metric-label">IoT devices online</p>
                        </div>
                        <span class="metric-icon cyan"><i class="fa-solid fa-microchip"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="iot-readings-hour">0</p>
                            <p class="metric-label">Leituras IoT (1h)</p>
                        </div>
                        <span class="metric-icon blue"><i class="fa-solid fa-wave-square"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12">
                <div class="card metric-card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <p class="metric-value" id="iot-alerts-window">0</p>
                            <p class="metric-label">Alertas IoT (24h)</p>
                        </div>
                        <span class="metric-icon red"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Visitas em tempo real</h5>
                        <small class="text-muted">Última atualização: <span id="ultima-atualizacao"><?php echo date('H:i:s'); ?></span></small?>
                    </div>
                    <div class="card-body chart-wrapper">
                        <canvas id="tempoRealChart" aria-label="Gráfico de visitas" role="img"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Controles</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">Próxima atualização em <strong id="proxima-atualizacao">30s</strong></p>
                        <div class="d-flex flex-wrap gap-2">
                            <button id="btn-pausar" class="btn btn-warning btn-sm">
                                <i class="fa-solid fa-pause me-1"></i> Pausar
                            </button>
                            <button id="btn-retomar" class="btn btn-success btn-sm d-none">
                                <i class="fa-solid fa-play me-1"></i> Retomar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Atividades recentes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Evento</th>
                                    </tr>
                                </thead>
                                <tbody id="atividades-recentes"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Últimas leituras IoT</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover iot-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Dispositivo</th>
                                <th>Métrica</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Capturado em</th>
                            </tr>
                        </thead>
                        <tbody id="iot-latest-body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Sem dados IoT no momento.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="assets/js/jquery-3.6.0.min.js" defer></script>
<script src="assets/js/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/dataTables.min.js" defer></script>
<script src="assets/js/dataTables.bootstrap4.min.js" defer></script>
<script src="assets/js/feather.min.js" defer></script>
<script src="assets/js/script.js" defer></script>

<script>
let chart;
let updateInterval;
let countdownInterval;
let isPaused = false;
const atividades = [];

function initChart() {
    const ctx = document.getElementById('tempoRealChart').getContext('2d');
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Visitas por minuto',
                data: [],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,.15)',
                fill: true,
                tension: .35,
                pointRadius: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
            }
        }
    });
}

function addActivity(evento) {
    const hora = new Date().toLocaleTimeString();
    atividades.unshift({ hora, evento });
    if (atividades.length > 8) atividades.pop();

    const tbody = document.getElementById('atividades-recentes');
    tbody.innerHTML = '';
    atividades.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${item.hora}</td><td>${item.evento}</td>`;
        tbody.appendChild(tr);
    });
}

function updateAlerts(alertas) {
    const container = document.getElementById('alerts-container');
    container.innerHTML = '';

    if (!Array.isArray(alertas) || alertas.length === 0) {
        return;
    }

    alertas.forEach(alerta => {
        const texto = typeof alerta === 'string' ? alerta : (alerta.mensagem || alerta.titulo || JSON.stringify(alerta));
        const div = document.createElement('div');
        div.className = 'alert alert-warning alert-dismissible fade show';
        div.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            ${texto}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        `;
        container.appendChild(div);
    });
}

function updateIotTable(list) {
    const tbody = document.getElementById('iot-latest-body');
    tbody.innerHTML = '';

    if (!Array.isArray(list) || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sem dados IoT no momento.</td></tr>';
        return;
    }

    list.forEach(item => {
        const status = (item.quality_status || 'ok').toLowerCase();
        const badgeClass = status === 'alerta' ? 'bg-danger' : (status === 'warning' ? 'bg-warning text-dark' : 'bg-success');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.device_id || '-'}</td>
            <td>${item.metric || '-'}</td>
            <td>${item.value ? item.value : '-'} ${item.unit || ''}</td>
            <td><span class="badge ${badgeClass}">${status}</span></td>
            <td>${item.captured_at || '-'}</td>
        `;
        tbody.appendChild(tr);
    });
}

function startCountdown() {
    let seconds = 30;
    const target = document.getElementById('proxima-atualizacao');

    if (countdownInterval) {
        clearInterval(countdownInterval);
    }

    target.textContent = `${seconds}s`;
    countdownInterval = setInterval(() => {
        seconds -= 1;
        if (seconds <= 0) {
            target.textContent = 'atualizando...';
            clearInterval(countdownInterval);
            return;
        }
        target.textContent = `${seconds}s`;
    }, 1000);
}

function refreshData() {
    if (isPaused) {
        return;
    }

    fetch('../api/get_dados_tempo_real.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('visitas-hoje').textContent = data.visitas_hoje || 0;
            document.getElementById('requisicoes-hoje').textContent = data.requisicoes_hoje || 0;
            document.getElementById('depoimentos-pendentes').textContent = data.depoimentos_pendentes || 0;
            document.getElementById('tempo-medio').textContent = data.tempo_medio || '00:00:00';
            document.getElementById('ultima-atualizacao').textContent = new Date().toLocaleTimeString();

            const iot = data.iot || {};
            document.getElementById('iot-devices-online').textContent = iot.devices_online || 0;
            document.getElementById('iot-readings-hour').textContent = iot.readings_last_hour || 0;
            document.getElementById('iot-alerts-window').textContent = iot.alerts_window || 0;
            updateIotTable(iot.latest || []);

            const now = new Date();
            const label = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
            if (chart.data.labels.length >= 20) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
            }
            chart.data.labels.push(label);
            chart.data.datasets[0].data.push(data.visitas_hoje || 0);
            chart.update();

            updateAlerts(data.alertas || []);
            addActivity('Dados sincronizados');
            startCountdown();
        })
        .catch(() => {
            addActivity('Falha ao sincronizar dados');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    initChart();
    refreshData();
    updateInterval = setInterval(refreshData, 30000);

    document.getElementById('btn-atualizar').addEventListener('click', () => {
        addActivity('Atualização manual');
        refreshData();
    });

    document.getElementById('btn-pausar').addEventListener('click', () => {
        isPaused = true;
        document.getElementById('btn-pausar').classList.add('d-none');
        document.getElementById('btn-retomar').classList.remove('d-none');
        addActivity('Monitoramento pausado');
    });

    document.getElementById('btn-retomar').addEventListener('click', () => {
        isPaused = false;
        document.getElementById('btn-retomar').classList.add('d-none');
        document.getElementById('btn-pausar').classList.remove('d-none');
        addActivity('Monitoramento retomado');
        refreshData();
    });
});

window.addEventListener('beforeunload', () => {
    if (updateInterval) clearInterval(updateInterval);
    if (countdownInterval) clearInterval(countdownInterval);
});
</script>
</body>
</html>


