<?php
/**
 * CONFINTER - Monitor de SLA em Tempo Real (IoT)
 * Salvar em: admin/sla.php
 * Simula dados vindos de sensores de fila/tempo físicos
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once '../php/conexao.php';
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit();
}

// ── SLA targets (configuráveis) ──
$SLA_RESPOSTA_H = 24;   // horas para 1ª resposta
$SLA_CONCLUSAO_H = 72;  // horas para conclusão
$SLA_ALERTA_PCT = 80;   // % do tempo → alerta amarelo

// ── Métricas das requisições ──
$sqlMet = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN status='em_andamento' THEN 1 ELSE 0 END) AS andamento,
        SUM(CASE WHEN status='concluido' THEN 1 ELSE 0 END) AS concluidos,
        AVG(TIMESTAMPDIFF(HOUR, data_requisicao, IFNULL(data_conclusao, NOW()))) AS media_horas,
        MAX(TIMESTAMPDIFF(HOUR, data_requisicao, NOW())) AS max_horas_aberto
    FROM requisicoes
    WHERE DATE(data_requisicao) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$resMet = $conexao->query($sqlMet);
$met = $resMet ? $resMet->fetch_assoc() : [];

$totalReq     = (int)   ($met['total']          ?? 0);
$pendentes    = (int)   ($met['pendentes']       ?? 0);
$andamento    = (int)   ($met['andamento']       ?? 0);
$concluidos   = (int)   ($met['concluidos']      ?? 0);
$mediaHoras   = round(  ($met['media_horas']     ?? 0), 1);
$maxHoras     = (int)   ($met['max_horas_aberto'] ?? 0);

$taxaRespostaSLA   = $totalReq > 0 ? round((($totalReq - $pendentes) / $totalReq) * 100, 1) : 0;
$taxaConclusaoSLA  = $totalReq > 0 ? round(($concluidos / $totalReq) * 100, 1) : 0;
$slaRespostaOk     = $mediaHoras <= $SLA_RESPOSTA_H;
$slaConclusaoOk    = $mediaHoras <= $SLA_CONCLUSAO_H;
$alertaAtivo       = ($maxHoras > $SLA_CONCLUSAO_H * ($SLA_ALERTA_PCT / 100));

// ── Requisições críticas (mais de 48h abertas sem conclusão) ──
$sqlCrit = "
    SELECT c.nome, c.email, r.id_requisicao, r.status, r.data_requisicao,
           TIMESTAMPDIFF(HOUR, r.data_requisicao, NOW()) AS horas_abertas
    FROM requisicoes r
    INNER JOIN clientes c ON c.id_cliente = r.id_cliente
    WHERE r.status != 'concluido'
      AND TIMESTAMPDIFF(HOUR, r.data_requisicao, NOW()) > 48
    ORDER BY horas_abertas DESC
    LIMIT 10
";
$resCrit = $conexao->query($sqlCrit);
$criticas = [];
if ($resCrit) while ($r = $resCrit->fetch_assoc()) $criticas[] = $r;

// ── Tendência últimos 7 dias ──
$sqlTrend = "
    SELECT DATE(data_requisicao) AS dia,
           COUNT(*) AS total,
           SUM(CASE WHEN status='concluido' THEN 1 ELSE 0 END) AS conc
    FROM requisicoes
    WHERE data_requisicao >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(data_requisicao)
    ORDER BY dia ASC
";
$resTrend = $conexao->query($sqlTrend);
$trendDias = []; $trendTotal = []; $trendConc = [];
$mapTrend = [];
if ($resTrend) while ($r = $resTrend->fetch_assoc()) $mapTrend[$r['dia']] = $r;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $trendDias[]  = date('d/m', strtotime($d));
    $trendTotal[] = $mapTrend[$d]['total'] ?? 0;
    $trendConc[]  = $mapTrend[$d]['conc']  ?? 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONFINTER Admin — Monitor SLA IoT</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <style>
        :root { --azul:#0f4c81; --verde:#00c896; --amarelo:#f0a202; --vermelho:#e55353; --roxo:#7b6dff; }

        /* ── Alerta SLA ── */
        .sla-alert-banner {
            display: flex; align-items: center; gap: 1rem;
            background: linear-gradient(135deg, #fff3cd, #ffe38c);
            border: 1.5px solid #f0a202;
            border-radius: 14px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: blink-border 2s ease-in-out infinite;
        }
        @keyframes blink-border {
            0%,100% { border-color: #f0a202; }
            50%      { border-color: #e55353; }
        }
        .sla-alert-banner.ok {
            background: linear-gradient(135deg, #e0f7f0, #b2f0df);
            border-color: #00c896;
            animation: none;
        }
        .sla-alert-banner .icon { font-size: 2rem; }
        .sla-alert-banner .txt h6 { margin: 0; font-weight: 700; }
        .sla-alert-banner .txt small { color: #555; }

        /* ── Gauge SLA ── */
        .sla-gauge-wrap {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 1rem 1rem;
        }
        .gauge-title {
            font-size: .72rem; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: #8f9fb3; margin-bottom: .5rem;
        }
        .gauge-value {
            font-size: 2.8rem; font-weight: 900; line-height: 1;
        }
        .gauge-label { font-size: .75rem; color: #8f9fb3; margin-top: .3rem; }
        .gauge-status {
            font-size: .7rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; border-radius: 6px;
            padding: .25rem .7rem; margin-top: .5rem;
        }
        .ok   { color: #00a87a; background: #e0f7f0; }
        .warn { color: #c88000; background: #fff3cd; }
        .fail { color: #e55353; background: #fde8e8; }

        /* ── Barra de progresso SLA ── */
        .sla-progress-wrap { margin: .5rem 0; }
        .sla-progress-label {
            display: flex; justify-content: space-between;
            font-size: .73rem; color: #8f9fb3; margin-bottom: .3rem;
        }
        .sla-bar {
            height: 10px; border-radius: 999px;
            background: #e9ecef; overflow: hidden;
        }
        .sla-bar-fill {
            height: 100%; border-radius: 999px;
            transition: width 1s ease;
        }
        .fill-verde  { background: linear-gradient(90deg, #00c896, #00e6b0); }
        .fill-amarelo{ background: linear-gradient(90deg, #f0a202, #ffca2c); }
        .fill-vermelho{background: linear-gradient(90deg, #e55353, #ff7f7f); }

        /* ── Tabela críticos ── */
        .critico-row td { vertical-align: middle; }
        .horas-badge {
            font-size: .75rem; font-weight: 700; border-radius: 8px;
            padding: .3rem .7rem; white-space: nowrap;
        }
        .horas-critico  { background: #fde8e8; color: #e55353; }
        .horas-alerta   { background: #fff3cd; color: #c88000; }
        .horas-normal   { background: #e0f7f0; color: #00a87a; }

        /* ── Sensor feed ── */
        .sensor-feed {
            background: #07192e;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            font-family: 'Courier New', monospace;
            font-size: .75rem;
            color: #00ffc2;
            max-height: 200px;
            overflow-y: auto;
        }
        .sensor-feed .line { margin-bottom: .25rem; opacity: .85; }
        .sensor-feed .line.new { color: #7fffdf; opacity: 1; }
        .sensor-feed .ts { color: #3a7a65; margin-right: .5rem; }

        .card-sla { border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(15,76,129,.08); }
    </style>
<link rel="stylesheet" href="assets/css/confinter-admin-hardening.css">
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="page-wrapper">
<div class="content container-fluid">

    <!-- Page header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm-8">
                <h3 class="page-title">
                    <i class="fa fa-gauge-high me-2 text-primary"></i>Monitor de SLA — IoT
                </h3>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Monitor SLA</li>
                </ul>
            </div>
            <div class="col-sm-4 text-end">
                <span class="badge bg-success text-white" id="badge-live">
                    <span style="display:inline-block;width:7px;height:7px;background:#fff;border-radius:50%;margin-right:5px;animation:pulse-d 1.4s infinite;"></span>
                    Atualizando a cada 10s
                </span>
            </div>
        </div>
    </div>

    <!-- Alerta banner -->
    <div class="sla-alert-banner <?php echo !$alertaAtivo ? 'ok' : ''; ?>" id="slaBanner">
        <div class="icon"><?php echo $alertaAtivo ? '⚠️' : '✅'; ?></div>
        <div class="txt">
            <h6 id="bannerTitle"><?php echo $alertaAtivo
                ? "Alerta SLA: {$maxHoras}h de espera detectada em requisição aberta"
                : 'SLA Dentro dos Limites — Todos os indicadores normais'; ?></h6>
            <small id="bannerSub">
                <?php echo $alertaAtivo
                    ? "Requisição aberta há mais de {$SLA_CONCLUSAO_H}h. Intervenção recomendada."
                    : "Sensor IoT simulado: nenhum desvio crítico detectado nos últimos 30 dias."; ?>
            </small>
        </div>
    </div>

    <!-- Gauges -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card card-sla">
                <div class="sla-gauge-wrap">
                    <div class="gauge-title">Taxa Resposta SLA</div>
                    <div class="gauge-value" style="color:<?php echo $slaRespostaOk?'#00c896':'#e55353'; ?>">
                        <?php echo $taxaRespostaSLA; ?>%
                    </div>
                    <div class="gauge-label">Meta: 90%</div>
                    <span class="gauge-status <?php echo $taxaRespostaSLA>=90?'ok':($taxaRespostaSLA>=70?'warn':'fail'); ?>">
                        <?php echo $taxaRespostaSLA>=90?'✓ OK':($taxaRespostaSLA>=70?'⚠ Alerta':'✗ Crítico'); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-sla">
                <div class="sla-gauge-wrap">
                    <div class="gauge-title">Taxa Conclusão SLA</div>
                    <div class="gauge-value" style="color:<?php echo $slaConclusaoOk?'#00c896':'#e55353'; ?>">
                        <?php echo $taxaConclusaoSLA; ?>%
                    </div>
                    <div class="gauge-label">Meta: 85%</div>
                    <span class="gauge-status <?php echo $taxaConclusaoSLA>=85?'ok':($taxaConclusaoSLA>=60?'warn':'fail'); ?>">
                        <?php echo $taxaConclusaoSLA>=85?'✓ OK':($taxaConclusaoSLA>=60?'⚠ Alerta':'✗ Crítico'); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-sla">
                <div class="sla-gauge-wrap">
                    <div class="gauge-title">Média de Atendimento</div>
                    <div class="gauge-value" style="color:<?php echo $mediaHoras<=$SLA_RESPOSTA_H?'#00c896':($mediaHoras<=$SLA_CONCLUSAO_H?'#f0a202':'#e55353'); ?>">
                        <?php echo $mediaHoras; ?>h
                    </div>
                    <div class="gauge-label">Meta: ≤ <?php echo $SLA_RESPOSTA_H; ?>h</div>
                    <span class="gauge-status <?php echo $mediaHoras<=$SLA_RESPOSTA_H?'ok':($mediaHoras<=$SLA_CONCLUSAO_H?'warn':'fail'); ?>">
                        <?php echo $mediaHoras<=$SLA_RESPOSTA_H?'✓ OK':($mediaHoras<=$SLA_CONCLUSAO_H?'⚠ Alerta':'✗ Crítico'); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-sla">
                <div class="sla-gauge-wrap">
                    <div class="gauge-title">Req. em Aberto</div>
                    <div class="gauge-value" style="color:<?php echo $pendentes+$andamento<=5?'#00c896':($pendentes+$andamento<=15?'#f0a202':'#e55353'); ?>">
                        <?php echo $pendentes + $andamento; ?>
                    </div>
                    <div class="gauge-label"><?php echo $pendentes; ?> pendentes · <?php echo $andamento; ?> andamento</div>
                    <span class="gauge-status <?php echo $pendentes+$andamento<=5?'ok':($pendentes+$andamento<=15?'warn':'fail'); ?>">
                        <?php echo $pendentes+$andamento<=5?'✓ OK':($pendentes+$andamento<=15?'⚠ Alerta':'✗ Crítico'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Barras de progresso SLA -->
    <div class="card card-sla mb-3">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">Conformidade SLA — Últimos 30 dias</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="sla-progress-wrap">
                        <div class="sla-progress-label"><span>1ª Resposta (≤24h)</span><span><?php echo $taxaRespostaSLA; ?>%</span></div>
                        <div class="sla-bar"><div class="sla-bar-fill <?php echo $taxaRespostaSLA>=90?'fill-verde':($taxaRespostaSLA>=70?'fill-amarelo':'fill-vermelho'); ?>" style="width:<?php echo $taxaRespostaSLA; ?>%"></div></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sla-progress-wrap">
                        <div class="sla-progress-label"><span>Conclusão (≤72h)</span><span><?php echo $taxaConclusaoSLA; ?>%</span></div>
                        <div class="sla-bar"><div class="sla-bar-fill <?php echo $taxaConclusaoSLA>=85?'fill-verde':($taxaConclusaoSLA>=60?'fill-amarelo':'fill-vermelho'); ?>" style="width:<?php echo $taxaConclusaoSLA; ?>%"></div></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="sla-progress-wrap">
                        <?php $satPct = $totalReq>0?round(($concluidos/$totalReq)*100,1):0; ?>
                        <div class="sla-progress-label"><span>Satisfação Geral</span><span><?php echo $satPct; ?>%</span></div>
                        <div class="sla-bar"><div class="sla-bar-fill <?php echo $satPct>=80?'fill-verde':($satPct>=60?'fill-amarelo':'fill-vermelho'); ?>" style="width:<?php echo $satPct; ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Gráfico tendência -->
        <div class="col-lg-7">
            <div class="card card-sla">
                <div class="card-header bg-white border-0 pt-3 pb-0 px-4">
                    <h6 class="fw-bold">Tendência de Requisições — 7 dias</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartSla" style="height:220px;max-height:220px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Feed sensor simulado -->
        <div class="col-lg-5">
            <div class="card card-sla h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Feed Sensor IoT — Tempo Real</h6>
                    <span class="badge" style="background:#07192e;color:#00ffc2;font-size:.7rem;">SIMULADO</span>
                </div>
                <div class="card-body">
                    <div class="sensor-feed" id="sensorFeed">
                        <div class="line"><span class="ts">[BOOT]</span> Sistema IoT CONFINTER iniciado</div>
                        <div class="line"><span class="ts">[INFO]</span> Sensor SLA conectado — leitura ativa</div>
                        <div class="line"><span class="ts">[DATA]</span> Total req: <?php echo $totalReq; ?> | Pendentes: <?php echo $pendentes; ?></div>
                        <div class="line"><span class="ts">[SLA]</span>  Tempo médio: <?php echo $mediaHoras; ?>h | Meta: <?php echo $SLA_RESPOSTA_H; ?>h</div>
                        <?php if ($alertaAtivo): ?>
                        <div class="line" style="color:#ffcc00"><span class="ts">[ALERTA]</span> Req. com <?php echo $maxHoras; ?>h sem conclusão!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela críticos -->
    <?php if (!empty($criticas)): ?>
    <div class="card card-sla mt-3">
        <div class="card-header bg-white border-0 pt-3 pb-0 px-4">
            <h6 class="fw-bold mb-0" style="color:#e55353;">
                <i class="fa fa-triangle-exclamation me-2"></i>Requisições Críticas — Acima de 48h em Aberto
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Cliente</th><th>E-mail</th>
                        <th>Status</th><th>Data abertura</th><th>Tempo aberto</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($criticas as $c):
                        $h = (int)$c['horas_abertas'];
                        $cls = $h > $SLA_CONCLUSAO_H ? 'horas-critico' : 'horas-alerta';
                    ?>
                    <tr class="critico-row">
                        <td><?php echo htmlspecialchars($c['nome']); ?></td>
                        <td><?php echo htmlspecialchars($c['email']); ?></td>
                        <td><span class="badge bg-warning text-dark"><?php echo ucfirst(str_replace('_',' ',$c['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($c['data_requisicao']); ?></td>
                        <td><span class="horas-badge <?php echo $cls; ?>"><?php echo $h; ?>h</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Gráfico ──
    const ctx = document.getElementById('chartSla');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($trendDias, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [
                    {
                        label: 'Total',
                        data: <?php echo json_encode($trendTotal); ?>,
                        backgroundColor: 'rgba(15,76,129,.7)',
                        borderRadius: 6
                    },
                    {
                        label: 'Concluídos',
                        data: <?php echo json_encode($trendConc); ?>,
                        backgroundColor: 'rgba(0,200,150,.8)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // ── Feed sensor simulado ──
    const feed = document.getElementById('sensorFeed');
    const sensorMsgs = [
        ['DATA',  () => `Polling requisições — ${Math.floor(Math.random()*5)+1} novas leituras`],
        ['SLA',   () => `Tempo médio atual: ${(Math.random()*20+2).toFixed(1)}h`],
        ['PIR',   () => `Movimento detectado no guichê — presença confirmada`],
        ['QUEUE', () => `Fila digital: ${Math.floor(Math.random()*8)} pessoas aguardando`],
        ['NET',   () => `Latência sensor→servidor: ${Math.floor(Math.random()*30)+5}ms`],
        ['TEMP',  () => `Temperatura ambiente: ${(Math.random()*4+20).toFixed(1)}°C`],
        ['SLA',   () => `Verificação SLA concluída — ${Math.random()>.3?'OK':'⚠ ALERTA'}`],
    ];

    function addFeedLine() {
        const [tag, msgFn] = sensorMsgs[Math.floor(Math.random() * sensorMsgs.length)];
        const agora = new Date().toLocaleTimeString('pt-BR');
        const div = document.createElement('div');
        div.className = 'line new';
        div.innerHTML = `<span class="ts">[${agora}]</span> [${tag}] ${msgFn()}`;
        feed.appendChild(div);
        // Remove old lines
        while (feed.children.length > 20) feed.removeChild(feed.firstChild);
        feed.scrollTop = feed.scrollHeight;
        setTimeout(() => div.classList.remove('new'), 1000);
    }

    setInterval(addFeedLine, 3500);

    // ── Polling SLA real ──
    function pollSla() {
        fetch('sla_status.php?t=' + Date.now())
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                // Atualizar banner se mudou
                const banner = document.getElementById('slaBanner');
                if (data.alerta) {
                    banner.classList.remove('ok');
                    document.getElementById('bannerTitle').textContent = `Alerta SLA: ${data.max_horas}h detectada`;
                    document.getElementById('bannerSub').textContent = 'Requisição em aberto. Intervenção recomendada.';
                } else {
                    banner.classList.add('ok');
                }
            })
            .catch(() => {});
    }

    setInterval(pollSla, 10000);
});
</script>
</body>
</html>
