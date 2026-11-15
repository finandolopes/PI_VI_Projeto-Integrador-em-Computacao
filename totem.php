<?php
/**
 * CONFINTER - Totem de Atendimento Simulado (IoT)
 * Tela pública: exibe número na fila, simula sensor de presença via JS
 * Admin chama próximo via admin/fila.php
 */
session_start();
include_once('php/conexao.php');

// Busca estado atual da fila
$sqlFila = "SELECT * FROM iot_fila ORDER BY id DESC LIMIT 1";
$resFila = mysqli_query($conexao, $sqlFila);
$fila = $resFila ? mysqli_fetch_assoc($resFila) : null;

$atualAtendendo = $fila['numero_chamado'] ?? 0;
$totalEspera    = $fila['total_espera']   ?? 0;
$tempoMedio     = $fila['tempo_medio']    ?? 5;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONFINTER | Painel de Atendimento</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --azul:    #0f4c81;
            --azul2:   #1a6ab1;
            --verde:   #00c896;
            --amarelo: #f0a202;
            --branco:  #f4f8ff;
            --cinza:   #8f9fb3;
            --escuro:  #07192e;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--escuro);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Background animado */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(15,76,129,.45) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 90%, rgba(0,200,150,.18) 0%, transparent 55%);
            pointer-events: none;
        }

        /* Grid de pontos decorativo */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,.07) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        /* ── Topo ── */
        .totem-header {
            text-align: center;
            margin-bottom: 2.5rem;
            z-index: 1;
        }
        .totem-header img {
            height: 52px;
            filter: brightness(0) invert(1);
            margin-bottom: 1rem;
        }
        .totem-header .iot-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(0,200,150,.15);
            border: 1px solid rgba(0,200,150,.4);
            color: var(--verde);
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: .35rem .9rem;
            border-radius: 999px;
        }
        .totem-header .iot-badge .dot {
            width: 8px; height: 8px;
            background: var(--verde);
            border-radius: 50%;
            animation: pulse-dot 1.4s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.4; transform:scale(.7); }
        }

        /* ── Card principal ── */
        .totem-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.10);
            backdrop-filter: blur(18px);
            border-radius: 28px;
            padding: 3rem 4rem;
            text-align: center;
            z-index: 1;
            min-width: 420px;
            box-shadow: 0 30px 80px rgba(0,0,0,.5);
            position: relative;
        }

        .totem-card .label-chamado {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--cinza);
            margin-bottom: .5rem;
        }

        .numero-chamado {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 9rem;
            line-height: 1;
            color: var(--branco);
            text-shadow: 0 0 60px rgba(26,106,177,.7);
            transition: all .4s cubic-bezier(.34,1.56,.64,1);
        }
        .numero-chamado.flash {
            color: var(--verde);
            text-shadow: 0 0 80px rgba(0,200,150,.8);
            transform: scale(1.08);
        }

        .totem-card .status-msg {
            font-size: 1.15rem;
            color: rgba(255,255,255,.65);
            margin-top: .8rem;
        }

        /* ── Divisor ── */
        .divider {
            width: 60px; height: 3px;
            background: linear-gradient(90deg, var(--azul2), var(--verde));
            border-radius: 3px;
            margin: 1.8rem auto;
        }

        /* ── Stats ── */
        .stats-row {
            display: flex;
            gap: 2rem;
            justify-content: center;
            z-index: 1;
            margin-top: 2rem;
        }
        .stat-box {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            padding: 1.2rem 2rem;
            text-align: center;
            min-width: 130px;
        }
        .stat-box .val {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.8rem;
            color: var(--branco);
            line-height: 1;
        }
        .stat-box .stat-label {
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--cinza);
            margin-top: .3rem;
        }

        /* ── Senha própria ── */
        .minha-senha-section {
            z-index: 1;
            margin-top: 2rem;
            text-align: center;
        }
        .minha-senha-section p {
            font-size: .78rem;
            color: var(--cinza);
            margin-bottom: .8rem;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .btn-pegar-senha {
            background: linear-gradient(135deg, var(--azul2), var(--azul));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: .85rem 2.2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            letter-spacing: .03em;
        }
        .btn-pegar-senha:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(15,76,129,.5);
        }
        .btn-pegar-senha:active { transform: scale(.97); }

        /* ── Ticket popup ── */
        .ticket-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.7);
            backdrop-filter: blur(6px);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .ticket-overlay.show { display: flex; }
        .ticket {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 3rem;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,.5);
            animation: ticket-in .4s cubic-bezier(.34,1.56,.64,1);
            max-width: 340px;
            width: 100%;
        }
        @keyframes ticket-in {
            from { opacity:0; transform:scale(.7) translateY(30px); }
            to   { opacity:1; transform:scale(1) translateY(0); }
        }
        .ticket .ticket-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: var(--cinza);
            margin-bottom: .5rem;
        }
        .ticket .ticket-number {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 7rem;
            line-height: 1;
            color: var(--azul);
        }
        .ticket .ticket-info {
            font-size: .9rem;
            color: #555;
            margin-top: .5rem;
        }
        .ticket .ticket-info strong { color: var(--azul); }
        .ticket .btn-fechar {
            margin-top: 1.5rem;
            background: var(--azul);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: .7rem 2rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: .95rem;
        }
        .ticket .btn-fechar:hover { background: var(--azul2); }

        /* ── Sensor simulado ── */
        .sensor-bar {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: .7rem;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 999px;
            padding: .5rem 1.2rem;
            font-size: .72rem;
            color: var(--cinza);
            letter-spacing: .08em;
            z-index: 10;
        }
        .sensor-bar .sensor-dot {
            width: 7px; height: 7px;
            background: var(--verde);
            border-radius: 50%;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        /* Relógio */
        #relogio {
            position: fixed;
            top: 1.5rem;
            right: 2rem;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2rem;
            color: rgba(255,255,255,.3);
            letter-spacing: .08em;
            z-index: 10;
        }
    </style>
</head>
<body>

<div id="relogio">00:00:00</div>

<!-- Header -->
<div class="totem-header">
    <img src="assets/img/logo.png" alt="CONFINTER" onerror="this.style.display='none'">
    <div class="iot-badge">
        <span class="dot"></span>
        Sensor de Presença Ativo — IoT
    </div>
</div>

<!-- Card principal -->
<div class="totem-card">
    <div class="label-chamado">Atendendo agora</div>
    <div class="numero-chamado" id="numeroChamado">
        <?php echo str_pad($atualAtendendo, 3, '0', STR_PAD_LEFT); ?>
    </div>
    <p class="status-msg" id="statusMsg">
        <?php echo $atualAtendendo > 0 ? 'Por favor dirija-se ao guichê' : 'Aguardando início do atendimento'; ?>
    </p>
    <div class="divider"></div>
    <p style="font-size:.8rem;color:var(--cinza);letter-spacing:.06em;text-transform:uppercase;">
        Aguarde sua vez — atendimento presencial e digital
    </p>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box">
        <div class="val" id="statEspera"><?php echo $totalEspera; ?></div>
        <div class="stat-label">Na fila</div>
    </div>
    <div class="stat-box">
        <div class="val" id="statTempo"><?php echo $tempoMedio; ?>min</div>
        <div class="stat-label">Tempo médio</div>
    </div>
    <div class="stat-box">
        <div class="val" id="statAtendidos">0</div>
        <div class="stat-label">Atendidos hoje</div>
    </div>
</div>

<!-- Pegar senha -->
<div class="minha-senha-section">
    <p>Retire sua senha digital</p>
    <button class="btn-pegar-senha" onclick="pegarSenha()">
        📋 Pegar Senha
    </button>
</div>

<!-- Ticket popup -->
<div class="ticket-overlay" id="ticketOverlay">
    <div class="ticket">
        <div class="ticket-label">Sua senha</div>
        <div class="ticket-number" id="ticketNumber">—</div>
        <p class="ticket-info">
            Há <strong id="ticketFrentes">—</strong> pessoa(s) à sua frente.<br>
            Tempo estimado: <strong id="ticketTempo">—</strong>
        </p>
        <button class="btn-fechar" onclick="fecharTicket()">Entendido</button>
    </div>
</div>

<!-- Sensor bar -->
<div class="sensor-bar">
    <span class="sensor-dot"></span>
    Sensor PIR simulado — leitura a cada 5s — CONFINTER IoT v1.0
</div>

<script>
    // ── Relógio ──
    function atualizarRelogio() {
        const agora = new Date();
        document.getElementById('relogio').textContent =
            agora.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }
    setInterval(atualizarRelogio, 1000);
    atualizarRelogio();

    // ── Estado local ──
    let ultimoNumero   = <?php echo (int)$atualAtendendo; ?>;
    let atendidosHoje  = <?php echo (int)$atualAtendendo; ?>;
    let proximaSenha   = Math.max(<?php echo (int)$atualAtendendo; ?>, 1) + <?php echo (int)$totalEspera; ?> + 1;

    // ── Polling da fila (simula MQTT via HTTP) ──
    function pollFila() {
        fetch('php/iot_fila_status.php?t=' + Date.now())
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;

                const el = document.getElementById('numeroChamado');
                if (data.numero_chamado !== ultimoNumero) {
                    ultimoNumero = data.numero_chamado;
                    el.classList.add('flash');
                    setTimeout(() => el.classList.remove('flash'), 800);
                    atendidosHoje = data.numero_chamado;
                    document.getElementById('statAtendidos').textContent = atendidosHoje;
                    document.getElementById('statusMsg').textContent =
                        data.numero_chamado > 0
                            ? 'Por favor dirija-se ao guichê'
                            : 'Aguardando início do atendimento';
                    // Efeito sonoro simulado
                    playBeep();
                }
                el.textContent = String(data.numero_chamado).padStart(3, '0');
                document.getElementById('statEspera').textContent  = data.total_espera;
                document.getElementById('statTempo').textContent   = data.tempo_medio + 'min';
                proximaSenha = data.numero_chamado + data.total_espera + 1;
            })
            .catch(() => {});
    }

    // Poll a cada 4 segundos (simula sensor PIR)
    setInterval(pollFila, 4000);

    // ── Sensor de presença simulado (movimento do mouse = presença) ──
    let presenceTimer;
    document.addEventListener('mousemove', () => {
        clearTimeout(presenceTimer);
        document.querySelector('.sensor-bar').style.borderColor = 'rgba(0,200,150,.5)';
        presenceTimer = setTimeout(() => {
            document.querySelector('.sensor-bar').style.borderColor = 'rgba(255,255,255,.1)';
        }, 3000);
    });

    // ── Pegar senha ──
    function pegarSenha() {
        const frentes = Math.max(0, proximaSenha - ultimoNumero - 1);
        const tempoMin = frentes * (parseInt(document.getElementById('statTempo').textContent) || 5);
        document.getElementById('ticketNumber').textContent = String(proximaSenha).padStart(3, '0');
        document.getElementById('ticketFrentes').textContent = frentes;
        document.getElementById('ticketTempo').textContent =
            tempoMin < 1 ? 'menos de 1 minuto' : tempoMin + ' minutos';
        document.getElementById('ticketOverlay').classList.add('show');

        // Registra no servidor
        fetch('php/iot_pegar_senha.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({senha: proximaSenha})
        }).catch(() => {});

        proximaSenha++;
    }

    function fecharTicket() {
        document.getElementById('ticketOverlay').classList.remove('show');
    }

    // ── Beep simulado (AudioContext) ──
    function playBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + .4);
        } catch(e) {}
    }
</script>
</body>
</html>
