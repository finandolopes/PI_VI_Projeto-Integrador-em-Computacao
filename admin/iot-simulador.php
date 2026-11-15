<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['username']) && !empty($_SESSION['usuario'])) {
    $_SESSION['username'] = (string) $_SESSION['usuario'];
}
if (empty($_SESSION['user_id']) && !empty($_SESSION['usuario_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['usuario_id'];
}

if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'FaÃ§a login para acessar o painel administrativo.';
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="CONFINTER - Simulador IoT">
    <meta name="robots" content="noindex, nofollow">
    <title>CONFINTER - Simulador IoT</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/bootstrap5-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <style>
        .iot-step {
            border: 1px solid #dce7f8;
            border-radius: 14px;
            background: #fff;
            height: 100%;
        }
        .iot-step h6 {
            margin-bottom: 0.4rem;
            font-weight: 700;
        }
        .iot-step p {
            margin-bottom: 0;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .iot-kpi {
            border: 1px solid #dce7f8;
            border-radius: 12px;
            background: #fff;
            padding: 0.9rem;
            height: 100%;
        }
        .iot-kpi .value {
            font-size: 1.55rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        .iot-kpi .label {
            color: #6b7280;
            margin-bottom: 0;
        }
        .badge-quality-ok { background: #daf5e5; color: #0a7a40; }
        .badge-quality-warning { background: #fff4cf; color: #806000; }
        .badge-quality-alerta { background: #f9d7da; color: #8b1f2b; }
        .chart-holder {
            height: 320px;
        }
    </style>
    <link rel="stylesheet" href="assets/css/confinter-admin-refinement.css">
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h3 class="page-title">Simulador IoT</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Simulador IoT</li>
                    </ul>
                </div>
                <div class="col-sm-4 text-sm-end">
                    <span class="badge bg-info-subtle text-info-emphasis">Captura, anÃ¡lise e tratamento em tempo real</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="iot-step p-3">
                    <h6><i class="fa-solid fa-satellite-dish me-2"></i>Captura</h6>
                    <p>Gera telemetria sintÃ©tica de dispositivos e envia para `api/iot_ingest.php`.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="iot-step p-3">
                    <h6><i class="fa-solid fa-chart-line me-2"></i>AnÃ¡lise</h6>
                    <p>Consolida leituras por janela de tempo e monitora tendÃªncia das mÃ©tricas.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="iot-step p-3">
                    <h6><i class="fa-solid fa-shield-heart me-2"></i>Tratamento</h6>
                    <p>Classifica qualidade (`ok`, `warning`, `alerta`) com limites de referÃªncia.</p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-3 col-sm-6">
                <div class="iot-kpi">
                    <p class="value" id="kpi-devices">0</p>
                    <p class="label">Devices online</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="iot-kpi">
                    <p class="value" id="kpi-readings">0</p>
                    <p class="label">Leituras na Ãºltima hora</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="iot-kpi">
                    <p class="value" id="kpi-alerts">0</p>
                    <p class="label">Alertas (24h)</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="iot-kpi">
                    <p class="value" id="kpi-avg">0,00</p>
                    <p class="label">Valor mÃ©dio (24h)</p>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">ConfiguraÃ§Ã£o da simulaÃ§Ã£o</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label for="device-prefix" class="form-label">Prefixo do device</label>
                            <input id="device-prefix" type="text" class="form-control" value="SENSOR-PI">
                        </div>
                        <div class="mb-2">
                            <label for="metric-type" class="form-label">MÃ©trica</label>
                            <select id="metric-type" class="form-select">
                                <option value="temperatura">Temperatura</option>
                                <option value="umidade">Umidade</option>
                                <option value="vibracao">VibraÃ§Ã£o</option>
                                <option value="consumo_energia">Consumo de energia</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label for="metric-min" class="form-label">MÃ­n.</label>
                                <input id="metric-min" type="number" class="form-control" value="10" step="0.1">
                            </div>
                            <div class="col-6">
                                <label for="metric-max" class="form-label">MÃ¡x.</label>
                                <input id="metric-max" type="number" class="form-control" value="80" step="0.1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sim-interval" class="form-label">Intervalo (ms)</label>
                            <input id="sim-interval" type="number" class="form-control" value="1500" min="500" step="100">
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button id="btn-once" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> Enviar 1 leitura
                            </button>
                            <button id="btn-start" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-play me-1"></i> Iniciar fluxo
                            </button>
                            <button id="btn-stop" class="btn btn-warning btn-sm" disabled>
                                <i class="fa-solid fa-stop me-1"></i> Parar
                            </button>
                            <button id="btn-clear" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-trash me-1"></i> Limpar painel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">TendÃªncia das leituras simuladas</h5>
                        <small class="text-muted">AtualizaÃ§Ã£o automÃ¡tica a cada 5s</small>
                    </div>
                    <div class="card-body">
                        <div class="chart-holder">
                            <canvas id="iotSimChart" aria-label="TendÃªncia de telemetria simulada" role="img"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Eventos simulados (captura + tratamento)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Device</th>
                            <th>MÃ©trica</th>
                            <th>Valor</th>
                            <th>Qualidade</th>
                            <th>Timestamp</th>
                        </tr>
                        </thead>
                        <tbody id="sim-events-body">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Nenhum evento gerado.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const eventsBody = document.getElementById('sim-events-body');
    const devicePrefixEl = document.getElementById('device-prefix');
    const metricTypeEl = document.getElementById('metric-type');
    const minEl = document.getElementById('metric-min');
    const maxEl = document.getElementById('metric-max');
    const intervalEl = document.getElementById('sim-interval');
    const btnOnce = document.getElementById('btn-once');
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnClear = document.getElementById('btn-clear');

    const chartCtx = document.getElementById('iotSimChart');
    let simTimer = null;
    let localSamples = [];

    const chart = new Chart(chartCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Valor',
                data: [],
                borderColor: '#0f4c81',
                backgroundColor: 'rgba(15, 76, 129, 0.15)',
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    function qualityBadge(quality) {
        const normalized = String(quality || 'ok').toLowerCase();
        const css = normalized === 'alerta'  'badge-quality-alerta' : (normalized === 'warning'  'badge-quality-warning' : 'badge-quality-ok');
        return '<span class="badge ' + css + '">' + normalized + '</span>';
    }

    function addEventRow(row) {
        if (!eventsBody) {
            return;
        }

        if (eventsBody.children.length === 1 && eventsBody.children[0].textContent.indexOf('Nenhum evento') !== -1) {
            eventsBody.innerHTML = '';
        }

        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + row.device_id + '</td>' +
            '<td>' + row.metric + '</td>' +
            '<td>' + Number(row.value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (row.unit || '') + '</td>' +
            '<td>' + qualityBadge(row.quality_status) + '</td>' +
            '<td>' + (row.captured_at || '-') + '</td>';

        eventsBody.prepend(tr);
        while (eventsBody.children.length > 15) {
            eventsBody.removeChild(eventsBody.lastChild);
        }
    }

    function updateChart(sample) {
        localSamples.push(sample);
        if (localSamples.length > 30) {
            localSamples = localSamples.slice(-30);
        }
        chart.data.labels = localSamples.map(function (item) { return item.time; });
        chart.data.datasets[0].data = localSamples.map(function (item) { return item.value; });
        chart.update('none');
    }

    function buildPayload() {
        const metric = metricTypeEl.value;
        const min = Number(minEl.value || 0);
        const max = Number(maxEl.value || 0);
        const deviceId = (devicePrefixEl.value || 'SENSOR-PI') + '-' + String(Math.floor(Math.random() * 6) + 1).padStart(2, '0');

        let value = min + (Math.random() * (max - min));
        if (Math.random() > 0.85) {
            value = max + (Math.random() * (max * 0.2));
        }

        const unit = metric === 'temperatura'  'C' : (metric === 'umidade'  '%' : (metric === 'vibracao'  'Hz' : 'kWh'));

        return {
            device_id: deviceId,
            metric: metric,
            value: Number(value.toFixed(2)),
            unit: unit,
            min: min,
            max: max,
            captured_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
    }

    async function sendPayload() {
        const payload = buildPayload();
        const response = await fetch('../api/iot_ingest.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!response.ok || !result.ok) {
            throw new Error(result.error || 'Falha ao enviar telemetria.');
        }

        addEventRow(result.data);
        updateChart({
            time: new Date().toLocaleTimeString('pt-BR'),
            value: Number(result.data.value || 0)
        });
    }

    async function refreshMetrics() {
        try {
            const response = await fetch('../api/iot_metrics.phpwindow=24', { credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                return;
            }

            const metrics = result.metrics || {};
            document.getElementById('kpi-devices').textContent = Number(metrics.devices_online || 0).toLocaleString('pt-BR');
            document.getElementById('kpi-readings').textContent = Number(metrics.readings_last_hour || 0).toLocaleString('pt-BR');
            document.getElementById('kpi-alerts').textContent = Number(metrics.alerts_window || 0).toLocaleString('pt-BR');
            document.getElementById('kpi-avg').textContent = Number(metrics.avg_value_window || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (error) {
            // silencioso
        }
    }

    btnOnce.addEventListener('click', function () {
        sendPayload().then(refreshMetrics).catch(function (error) {
            alert(error.message);
        });
    });

    btnStart.addEventListener('click', function () {
        const interval = Math.max(500, Number(intervalEl.value || 1500));
        if (simTimer) {
            clearInterval(simTimer);
        }
        simTimer = setInterval(function () {
            sendPayload().then(refreshMetrics).catch(function () {
                // silencioso para nÃ£o travar a simulaÃ§Ã£o.
            });
        }, interval);

        btnStart.disabled = true;
        btnStop.disabled = false;
    });

    btnStop.addEventListener('click', function () {
        if (simTimer) {
            clearInterval(simTimer);
            simTimer = null;
        }
        btnStart.disabled = false;
        btnStop.disabled = true;
    });

    btnClear.addEventListener('click', function () {
        eventsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Nenhum evento gerado.</td></tr>';
        localSamples = [];
        chart.data.labels = [];
        chart.data.datasets[0].data = [];
        chart.update();
    });

    refreshMetrics();
    setInterval(refreshMetrics, 5000);
});
</script>
</body>
</html>



