<?php
/**
 * CONFINTER - Terminal de Biometria Simulado (IoT)
 * Tela pública: cliente gera QR code único para check-in
 * Admin vê check-ins em tempo real em admin/checkin.php
 * Salvar em: checkin.php (raiz do projeto)
 */
session_start();
include_once('php/conexao.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONFINTER | Terminal de Check-in</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --bg:      #0a0f1e;
            --panel:   #111827;
            --border:  #1e2d45;
            --azul:    #3b82f6;
            --azul2:   #60a5fa;
            --verde:   #10b981;
            --verde2:  #34d399;
            --cinza:   #6b7280;
            --branco:  #f1f5f9;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            font-family: 'Space Grotesk', sans-serif;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Fundo com grid */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(59,130,246,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,.06) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            top: -30%; left: -10%;
            width: 60%; height: 60%;
            background: radial-gradient(ellipse, rgba(59,130,246,.12) 0%, transparent 65%);
            pointer-events: none;
        }

        .terminal {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 520px;
            width: 100%;
            position: relative;
            z-index: 1;
            box-shadow: 0 40px 100px rgba(0,0,0,.6), 0 0 0 1px rgba(59,130,246,.1);
        }

        /* Barra de status topo */
        .terminal-topbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem;
        }
        .terminal-topbar .dots { display: flex; gap: .5rem; }
        .terminal-topbar .dots span {
            width: 12px; height: 12px; border-radius: 50%;
        }
        .d1 { background: #ff5f56; }
        .d2 { background: #ffbd2e; }
        .d3 { background: #27c93f; }
        .terminal-topbar .badge-iot {
            display: flex; align-items: center; gap: .5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .68rem; color: var(--verde2);
            letter-spacing: .08em;
        }
        .dot-live {
            width: 7px; height: 7px; background: var(--verde2);
            border-radius: 50%; animation: blink 1.4s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:.2} }

        /* Logo / título */
        .terminal-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .terminal-header img {
            height: 44px; filter: brightness(0) invert(1) opacity(.85);
            margin-bottom: 1rem;
        }
        .terminal-header h2 {
            color: var(--branco); font-size: 1.4rem; font-weight: 700;
        }
        .terminal-header p {
            color: var(--cinza); font-size: .85rem; margin-top: .3rem;
        }

        /* Formulário */
        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; color: #94a3b8;
            font-size: .75rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase;
            margin-bottom: .5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--branco);
            padding: .8rem 1rem;
            font-size: .95rem;
            font-family: 'Space Grotesk', sans-serif;
            outline: none;
            transition: border-color .2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        .form-group input::placeholder { color: #4b5563; }
        .form-group select option { background: #1f2937; color: var(--branco); }

        .btn-checkin {
            width: 100%;
            background: linear-gradient(135deg, var(--azul), #2563eb);
            color: #fff; border: none;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1rem; font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            cursor: pointer;
            transition: all .2s;
            letter-spacing: .02em;
            margin-top: .5rem;
        }
        .btn-checkin:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(59,130,246,.4); }
        .btn-checkin:active { transform: scale(.98); }

        /* QR section */
        .qr-section {
            display: none;
            text-align: center;
            animation: fade-up .5s ease;
        }
        @keyframes fade-up {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .qr-section.show { display: block; }

        .qr-box {
            background: #fff;
            border-radius: 20px;
            padding: 1.5rem;
            display: inline-block;
            margin: 1.5rem auto;
            box-shadow: 0 0 40px rgba(59,130,246,.3);
        }

        .checkin-info {
            background: rgba(16,185,129,.08);
            border: 1px solid rgba(16,185,129,.25);
            border-radius: 14px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .checkin-info .token {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.6rem; font-weight: 700;
            color: var(--verde2);
            letter-spacing: .15em;
        }
        .checkin-info p { color: #94a3b8; font-size: .85rem; margin-top: .5rem; }

        .status-area {
            background: rgba(255,255,255,.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .status-area .status-icon { font-size: 2rem; }
        .status-area .status-txt h6 { color: var(--branco); margin: 0; font-size: .95rem; }
        .status-area .status-txt small { color: var(--cinza); font-size: .8rem; }

        .btn-novo {
            background: transparent;
            border: 1px solid var(--border);
            color: #94a3b8;
            border-radius: 10px;
            padding: .75rem 2rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            width: 100%;
            transition: all .2s;
            font-family: 'Space Grotesk', sans-serif;
        }
        .btn-novo:hover { border-color: var(--azul); color: var(--azul2); }

        /* Rodapé sensor */
        .sensor-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .65rem;
            color: #374151;
            letter-spacing: .06em;
        }
        .sensor-footer span { color: #4b5563; }
    </style>
</head>
<body>

<div class="terminal">
    <!-- Topbar -->
    <div class="terminal-topbar">
        <div class="dots">
            <span class="d1"></span><span class="d2"></span><span class="d3"></span>
        </div>
        <div class="badge-iot">
            <span class="dot-live"></span>
            BIOMETRIC-SIM v2.1 — ATIVO
        </div>
    </div>

    <!-- Header -->
    <div class="terminal-header">
        <img src="assets/img/logo.png" alt="CONFINTER" onerror="this.style.display='none'">
        <h2>Terminal de Check-in</h2>
        <p>Identifique-se para gerar seu QR Code de acesso</p>
    </div>

    <!-- Formulário de check-in -->
    <div id="formSection">
        <div class="form-group">
            <label>Nome completo</label>
            <input type="text" id="inputNome" placeholder="Seu nome" autocomplete="off">
        </div>
        <div class="form-group">
            <label>CPF</label>
            <input type="text" id="inputCpf" placeholder="000.000.000-00" maxlength="14">
        </div>
        <div class="form-group">
            <label>Finalidade da visita</label>
            <select id="inputFinalidade">
                <option value="">Selecione...</option>
                <option value="Empréstimo Consignado">Empréstimo Consignado</option>
                <option value="Cartão de Crédito">Cartão de Crédito Consignado</option>
                <option value="Saque FGTS">Saque Aniversário FGTS</option>
                <option value="Consultoria">Consultoria Financeira</option>
                <option value="Refinanciamento">Refinanciamento</option>
            </select>
        </div>
        <button class="btn-checkin" onclick="gerarCheckin()">
            <i>🔐</i> Gerar QR Code de Acesso
        </button>
    </div>

    <!-- QR Code gerado -->
    <div class="qr-section" id="qrSection">
        <div class="checkin-info">
            <div class="token" id="tokenDisplay">——</div>
            <p id="checkinNome">—</p>
            <p id="checkinFinalidade">—</p>
        </div>
        <div class="qr-box">
            <div id="qrcode"></div>
        </div>
        <div class="status-area" id="statusArea">
            <div class="status-icon" id="statusIcon">⏳</div>
            <div class="status-txt">
                <h6 id="statusTitle">Aguardando leitura no guichê</h6>
                <small id="statusSub">Apresente este QR code ao atendente ou aproxime do leitor</small>
            </div>
        </div>
        <button class="btn-novo" onclick="novoCheckin()">← Novo Check-in</button>
    </div>

    <div class="sensor-footer">
        <span>CONFINTER IoT</span> · Terminal Biométrico Simulado ·
        <span id="footerTime">--:--:--</span>
    </div>
</div>

<script>
    // Formata CPF
    document.getElementById('inputCpf').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
             .replace(/\.(\d{3})(\d)/, '.$1-$2');
        this.value = v;
    });

    // Relógio rodapé
    setInterval(() => {
        document.getElementById('footerTime').textContent =
            new Date().toLocaleTimeString('pt-BR');
    }, 1000);

    let pollingTimer = null;
    let tokenAtual   = null;

    function gerarToken() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let t = '';
        for (let i = 0; i < 8; i++) t += chars[Math.floor(Math.random() * chars.length)];
        return t;
    }

    function gerarCheckin() {
        const nome       = document.getElementById('inputNome').value.trim();
        const cpf        = document.getElementById('inputCpf').value.trim();
        const finalidade = document.getElementById('inputFinalidade').value;

        if (!nome || !cpf || !finalidade) {
            alert('Por favor, preencha todos os campos.');
            return;
        }
        if (cpf.replace(/\D/g,'').length < 11) {
            alert('CPF inválido.');
            return;
        }

        tokenAtual = gerarToken();
        const payload = { token: tokenAtual, nome, cpf, finalidade };

        // Registrar no servidor
        fetch('php/iot_checkin_registrar.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        }).catch(() => {});

        // Exibir QR
        document.getElementById('tokenDisplay').textContent   = tokenAtual;
        document.getElementById('checkinNome').textContent    = '👤 ' + nome;
        document.getElementById('checkinFinalidade').textContent = '📋 ' + finalidade;

        // Gerar QR code
        document.getElementById('qrcode').innerHTML = '';
        new QRCode(document.getElementById('qrcode'), {
            text: JSON.stringify({ token: tokenAtual, nome, finalidade, ts: Date.now() }),
            width: 200, height: 200,
            colorDark: '#0a0f1e', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        document.getElementById('formSection').style.display = 'none';
        document.getElementById('qrSection').classList.add('show');

        // Polling para ver se admin confirmou
        iniciarPolling(tokenAtual);
    }

    function iniciarPolling(token) {
        if (pollingTimer) clearInterval(pollingTimer);
        pollingTimer = setInterval(() => {
            fetch(`php/iot_checkin_status.php?token=${token}&t=${Date.now()}`)
                .then(r => r.ok ? r.json() : null)
                .then(data => {
                    if (!data) return;
                    if (data.status === 'confirmado') {
                        document.getElementById('statusIcon').textContent  = '✅';
                        document.getElementById('statusTitle').textContent = 'Check-in confirmado!';
                        document.getElementById('statusSub').textContent   = `Atendente: ${data.atendente || 'Recepção'} — ${data.hora || ''}`;
                        document.getElementById('statusArea').style.background = 'rgba(16,185,129,.12)';
                        document.getElementById('statusArea').style.borderColor = 'rgba(16,185,129,.4)';
                        clearInterval(pollingTimer);
                    }
                })
                .catch(() => {});
        }, 4000);
    }

    function novoCheckin() {
        if (pollingTimer) clearInterval(pollingTimer);
        tokenAtual = null;
        document.getElementById('inputNome').value      = '';
        document.getElementById('inputCpf').value       = '';
        document.getElementById('inputFinalidade').value = '';
        document.getElementById('qrSection').classList.remove('show');
        document.getElementById('formSection').style.display = 'block';
        document.getElementById('statusIcon').textContent  = '⏳';
        document.getElementById('statusTitle').textContent = 'Aguardando leitura no guichê';
        document.getElementById('statusSub').textContent   = 'Apresente este QR code ao atendente ou aproxime do leitor';
        document.getElementById('statusArea').style.background = '';
        document.getElementById('statusArea').style.borderColor = '';
    }
</script>
</body>
</html>