<?php
/**
 * CONFINTER - Admin: Gerenciamento da Fila (IoT Totem)
 * Salvar em: admin/fila.php
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include_once '../php/conexao.php';
if (empty($_SESSION['username']) && empty($_SESSION['user_id'])) {
    header('Location: ../index.php'); exit();
}

// Ação: chamar próximo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'chamar_proximo') {
        $sqlAtual = "SELECT numero_chamado, total_espera FROM iot_fila ORDER BY id DESC LIMIT 1";
        $res = $conexao->query($sqlAtual);
        $atual = $res ? $res->fetch_assoc() : null;
        $novo  = ($atual['numero_chamado'] ?? 0) + 1;
        $espera = max(0, ($atual['total_espera'] ?? 0) - 1);
        $stmt = $conexao->prepare("INSERT INTO iot_fila (numero_chamado, total_espera, tempo_medio) VALUES (?,?,?) ON DUPLICATE KEY UPDATE numero_chamado=VALUES(numero_chamado), total_espera=VALUES(total_espera)");
        $tempo = 5;
        $stmt->bind_param('iii', $novo, $espera, $tempo);
        $stmt->execute();
        // Registrar histórico
        $conexao->query("INSERT INTO iot_fila_historico (numero_senha, evento) VALUES ($novo, 'chamado')");
        echo json_encode(['ok'=>true, 'numero'=>$novo]);
        exit();
    }

    if ($acao === 'resetar_fila') {
        $conexao->query("INSERT INTO iot_fila (numero_chamado, total_espera, tempo_medio) VALUES (0, 0, 5) ON DUPLICATE KEY UPDATE numero_chamado=0, total_espera=0");
        echo json_encode(['ok'=>true]);
        exit();
    }
}

// Buscar dados
$sqlFila = "SELECT * FROM iot_fila ORDER BY id DESC LIMIT 1";
$resFila = $conexao->query($sqlFila);
$fila = $resFila ? $resFila->fetch_assoc() : ['numero_chamado'=>0,'total_espera'=>0,'tempo_medio'=>5];

$sqlHist = "SELECT numero_senha, evento, criado_em FROM iot_fila_historico ORDER BY criado_em DESC LIMIT 20";
$resHist = $conexao->query($sqlHist);
$historico = [];
if ($resHist) while ($r = $resHist->fetch_assoc()) $historico[] = $r;

$sqlEspera = "SELECT COUNT(*) as total FROM iot_fila_historico WHERE DATE(criado_em)=CURDATE() AND evento='senha_retirada'";
$resE = $conexao->query($sqlEspera);
$totalRetiradas = $resE ? ($resE->fetch_assoc()['total'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONFINTER Admin — Fila IoT</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --azul:#0f4c81; --verde:#00c896; --amarelo:#f0a202; --vermelho:#e55353; }

        .iot-header {
            background: linear-gradient(135deg, var(--azul) 0%, #1a6ab1 100%);
            border-radius: 16px;
            color: #fff;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .iot-header h3 { margin: 0; font-size: 1.4rem; }
        .iot-header .badge-iot {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(0,200,150,.2); border: 1px solid rgba(0,200,150,.5);
            color: #00ffc2; border-radius: 999px;
            padding: .35rem .9rem; font-size: .75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .1em;
        }
        .dot-live {
            width: 8px; height: 8px; background: #00ffc2;
            border-radius: 50%; animation: pulse-d 1.4s infinite;
        }
        @keyframes pulse-d { 0%,100%{opacity:1}50%{opacity:.3} }

        .numero-grande {
            font-size: 6rem;
            font-weight: 900;
            line-height: 1;
            color: var(--azul);
        }

        .card-fila {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(15,76,129,.10);
        }

        .btn-chamar {
            background: linear-gradient(135deg, #00c896, #00a87a);
            color: #fff; border: none;
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            width: 100%;
        }
        .btn-chamar:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,200,150,.4); }
        .btn-chamar:disabled { background: #ccc; transform: none; box-shadow: none; cursor: not-allowed; }

        .btn-resetar {
            background: #fff;
            color: var(--vermelho);
            border: 2px solid var(--vermelho);
            border-radius: 12px;
            padding: .75rem 2rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all .2s;
        }
        .btn-resetar:hover { background: var(--vermelho); color: #fff; }

        .hist-row { padding: .6rem 0; border-bottom: 1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
        .hist-row:last-child { border-bottom: none; }

        .badge-evento {
            font-size: .7rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; border-radius: 6px; padding: .25rem .6rem;
        }
        .badge-chamado  { background: #e0f7f0; color: #00a87a; }
        .badge-retirada { background: #e8f0fb; color: #0f4c81; }
        .badge-reset    { background: #fde8e8; color: #e55353; }

        .stat-mini {
            background: #f8faff;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            text-align: center;
        }
        .stat-mini .v { font-size: 2rem; font-weight: 800; color: var(--azul); }
        .stat-mini .l { font-size: .72rem; color: #8f9fb3; text-transform: uppercase; letter-spacing: .08em; }

        .link-totem {
            display: inline-flex; align-items: center; gap: .5rem;
            background: #f0f7ff; border: 1px solid #c3d9f5;
            color: var(--azul); border-radius: 10px;
            padding: .5rem 1rem; font-size: .85rem; font-weight: 600;
            text-decoration: none; transition: background .2s;
        }
        .link-totem:hover { background: #dbeeff; color: var(--azul); }
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
            <h3><i class="fa fa-tower-broadcast me-2"></i>Totem de Atendimento — IoT</h3>
            <small style="opacity:.8">Gerenciamento da fila presencial simulada por sensor de presença</small>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div class="badge-iot"><span class="dot-live"></span>Sensor PIR Ativo</div>
            <a href="../totem.php" target="_blank" class="link-totem">
                <i class="fa fa-tv"></i> Abrir Totem
            </a>
        </div>
    </div>

    <div class="row g-3">
        <!-- Controle principal -->
        <div class="col-lg-4">
            <div class="card card-fila h-100">
                <div class="card-body text-center p-4">
                    <p class="text-muted mb-1" style="font-size:.75rem;letter-spacing:.1em;text-transform:uppercase;font-weight:700;">
                        Número atual chamado
                    </p>
                    <div class="numero-grande" id="adminNumeroChamado">
                        <?php echo str_pad($fila['numero_chamado'] ?? 0, 3, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="row g-2 mt-3 mb-4">
                        <div class="col-6">
                            <div class="stat-mini">
                                <div class="v" id="adminEspera"><?php echo $fila['total_espera'] ?? 0; ?></div>
                                <div class="l">Na fila</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-mini">
                                <div class="v"><?php echo $totalRetiradas; ?></div>
                                <div class="l">Senhas hoje</div>
                            </div>
                        </div>
                    </div>

                    <button class="btn-chamar mb-3" id="btnChamar" onclick="chamarProximo()">
                        <i class="fa fa-bell me-2"></i>Chamar Próximo
                    </button>
                    <button class="btn-resetar" onclick="resetarFila()">
                        <i class="fa fa-rotate-left me-2"></i>Resetar Fila
                    </button>
                </div>
            </div>
        </div>

        <!-- Histórico -->
        <div class="col-lg-8">
            <div class="card card-fila h-100">
                <div class="card-header bg-white border-0 pb-0 pt-3 px-4">
                    <h6 class="mb-0 fw-bold">Histórico de Atendimentos de Hoje</h6>
                </div>
                <div class="card-body px-4" id="historicoContainer">
                    <?php if (empty($historico)): ?>
                        <p class="text-muted text-center mt-4">Nenhum registro ainda.</p>
                    <?php else: ?>
                        <?php foreach ($historico as $h): ?>
                        <div class="hist-row">
                            <div>
                                <strong>Senha <?php echo str_pad($h['numero_senha'],3,'0',STR_PAD_LEFT); ?></strong>
                                <span class="ms-2 badge-evento badge-<?php echo htmlspecialchars($h['evento']); ?>">
                                    <?php echo $h['evento'] === 'chamado' ? 'Chamado' : ($h['evento'] === 'senha_retirada' ? 'Retirada' : 'Reset'); ?>
                                </span>
                            </div>
                            <small class="text-muted"><?php echo htmlspecialchars($h['criado_em']); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php include 'footer.php'; ?>

<script>
    function chamarProximo() {
        const btn = document.getElementById('btnChamar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Chamando...';

        fetch('fila.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'acao=chamar_proximo'
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('adminNumeroChamado').textContent =
                    String(data.numero).padStart(3, '0');
                const espera = parseInt(document.getElementById('adminEspera').textContent) || 0;
                document.getElementById('adminEspera').textContent = Math.max(0, espera - 1);
                // Adicionar ao histórico
                adicionarHistorico(data.numero, 'chamado');
            }
        })
        .catch(() => {})
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-bell me-2"></i>Chamar Próximo';
        });
    }

    function resetarFila() {
        if (!confirm('Resetar a fila para zero? Isso encerrará o atendimento atual.')) return;
        fetch('fila.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'acao=resetar_fila'
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('adminNumeroChamado').textContent = '000';
                document.getElementById('adminEspera').textContent = '0';
                adicionarHistorico(0, 'reset');
            }
        });
    }

    function adicionarHistorico(numero, evento) {
        const cont = document.getElementById('historicoContainer');
        const p = cont.querySelector('p');
        if (p) p.remove();

        const labels = { chamado:'Chamado', senha_retirada:'Retirada', reset:'Reset' };
        const agora = new Date().toLocaleString('pt-BR');
        const div = document.createElement('div');
        div.className = 'hist-row';
        div.innerHTML = `
            <div>
                <strong>Senha ${String(numero).padStart(3,'0')}</strong>
                <span class="ms-2 badge-evento badge-${evento}">${labels[evento]||evento}</span>
            </div>
            <small class="text-muted">${agora}</small>
        `;
        cont.insertBefore(div, cont.firstChild);
    }
</script>
</body>
</html>