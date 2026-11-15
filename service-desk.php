<?php
session_start();
require_once __DIR__ . '/php/conexao.php';
require_once __DIR__ . '/php/sd_service.php';

if (empty($_SESSION['sd_csrf'])) {
    $_SESSION['sd_csrf'] = bin2hex(random_bytes(24));
}

$message = '';
$result = null;
$issued = null;
$demoMode = defined('CONFINTER_DEMO_MODE') && CONFINTER_DEMO_MODE;

if ($demoMode && empty($_SESSION['sd_demo_tickets'])) {
    $_SESSION['sd_demo_tickets'] = [];
    $seedProtocol = 'PI6-' . date('Ymd') . '-DEMO123456';
    $_SESSION['sd_demo_tickets'][$seedProtocol] = [
        'protocol' => $seedProtocol,
        'consultation_token' => 'demo' . substr(hash('sha256', $seedProtocol), 0, 44),
        'origin' => 'iot',
        'title' => 'Alerta de temperatura crítica no sensor ESP32',
        'category' => 'IoT',
        'priority' => 'critica',
        'status' => 'em_atendimento',
        'created_at' => date('Y-m-d H:i:s', strtotime('-35 minutes')),
        'updated_at' => date('Y-m-d H:i:s', strtotime('-8 minutes')),
        'first_response_at' => date('Y-m-d H:i:s', strtotime('-28 minutes')),
        'resolved_at' => null,
        'requester_name' => 'Monitor IoT',
        'requester_email' => 'iot@confinter.local',
        'description' => 'Telemetria acima do limite configurado. Regra com cooldown aplicada e incidente gerado automaticamente.',
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals($_SESSION['sd_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Sessão expirada. Atualize a página.');
        }
        if (!empty($_POST['website'])) {
            throw new InvalidArgumentException('Requisição inválida.');
        }
        $times = array_filter($_SESSION['sd_attempts'] ?? [], fn($x) => $x > time() - 3600);
        if (count($times) >= 12) {
            throw new RuntimeException('Limite de consultas atingido nesta sessão.');
        }
        $times[] = time();
        $_SESSION['sd_attempts'] = $times;

        $action = (string)($_POST['action'] ?? '');
        if ($demoMode) {
            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $category = trim((string)($_POST['category'] ?? 'Geral'));
                $priority = trim((string)($_POST['priority'] ?? 'media'));
                if ($name === '' || $title === '' || $description === '') {
                    throw new InvalidArgumentException('Preencha nome, assunto e descrição.');
                }
                $protocol = 'PI6-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                $token = bin2hex(random_bytes(24));
                $_SESSION['sd_demo_tickets'][$protocol] = [
                    'protocol' => $protocol,
                    'consultation_token' => $token,
                    'origin' => 'manual',
                    'title' => $title,
                    'category' => $category,
                    'priority' => $priority,
                    'status' => 'aberto',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'first_response_at' => null,
                    'resolved_at' => null,
                    'requester_name' => $name,
                    'requester_email' => $email,
                    'description' => $description,
                ];
                $issued = ['protocol' => $protocol, 'consultation_token' => $token];
            } elseif ($action === 'lookup') {
                $protocol = trim((string)($_POST['protocol'] ?? ''));
                $token = trim((string)($_POST['token'] ?? ''));
                $ticket = $_SESSION['sd_demo_tickets'][$protocol] ?? null;
                if ($ticket && hash_equals((string)$ticket['consultation_token'], $token)) {
                    $result = $ticket;
                } else {
                    $message = 'Protocolo ou código de consulta inválido.';
                }
            }
        } else {
            if ($action === 'create') {
                $issued = sdCreate($conexao, $_POST);
            } elseif ($action === 'lookup') {
                $result = sdLookup($conexao, trim((string)($_POST['protocol'] ?? '')), trim((string)($_POST['token'] ?? '')));
                if (!$result) {
                    $message = 'Protocolo ou código de consulta inválido.';
                }
            }
        }
    } catch (Throwable $e) {
        $message = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Operação não concluída. Verifique a configuração do sistema.';
        error_log('[SD] ' . $e->getMessage());
    }
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Service Desk | CONFINTER</title>
<link rel="stylesheet" href="assets/css/sd.css">
<link rel="stylesheet" href="assets/css/confinter-accessibility.css">
<script defer src="assets/js/confinter-accessibility.js"></script>
<style>
body{background:linear-gradient(180deg,#edf4fb 0%,#f7fbff 100%)}
header.sd-header{padding:24px 0 6px}
.hero-note{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.hero-note span{background:#e8f5ff;border:1px solid #cbe6f8;padding:9px 12px;border-radius:999px;font-size:12px;font-weight:700;color:#0f4e83}
.demo-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:linear-gradient(135deg,#ffe7a6,#ffd66e);border:1px solid #f2cb5f;color:#3d3d1b;font-size:12px;font-weight:800}
.sd-card--accent{position:relative;overflow:hidden}.sd-card--accent:before{content:'';position:absolute;inset:auto -30px -30px auto;width:120px;height:120px;border-radius:26px;background:linear-gradient(135deg,rgba(17,78,160,.08),rgba(17,78,160,.02));transform:rotate(14deg)}
.sd-inline-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.sd-inline-grid article{background:#f6fbff;border:1px solid #d9eaf7;border-radius:16px;padding:15px}.sd-inline-grid strong{display:block;font-size:1.2rem;color:#114ea0}
@media(max-width:780px){.sd-inline-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="sd-wrap">
  <header class="sd-header">
    <a href="index.php">← Página principal</a>
    <h1>Central de atendimento CONFINTER</h1>
    <p>Abertura de chamados com protocolo, consulta pública controlada, acompanhamento por técnicos e integração com incidentes de IoT.</p>
    <div class="hero-note">
      <span>Protocolo seguro</span>
      <span>Histórico auditável</span>
      <span>Indicadores de SLA</span>
      <span>Integração ESP32 → API → regra → chamado</span>
      <?php if ($demoMode): ?><span class="demo-chip">Modo de demonstração ativo</span><?php endif; ?>
    </div>
  </header>

  <?php if ($message): ?><p role="alert" class="notice"><?=h($message)?></p><?php endif; ?>
  <?php if ($issued): ?>
    <section class="sd-card sd-card--accent" role="status">
      <h2>Chamado registrado</h2>
      <p>Protocolo: <strong><?=h($issued['protocol'])?></strong></p>
      <p>Código privado para consulta: <strong><?=h($issued['consultation_token'])?></strong></p>
      <p>Guarde ambos. Por segurança, o código não poderá ser recuperado depois.</p>
    </section>
  <?php endif; ?>
  <?php if ($result): ?>
    <section class="sd-card sd-card--accent" aria-live="polite">
      <h2><?=h($result['title'])?></h2>
      <div class="sd-inline-grid">
        <article><span>Protocolo</span><strong><?=h($result['protocol'])?></strong></article>
        <article><span>Status</span><strong><?=h($result['status'])?></strong></article>
        <article><span>Prioridade</span><strong><?=h($result['priority'])?></strong></article>
      </div>
      <p><strong>Origem:</strong> <?=h($result['origin'])?> · <strong>Categoria:</strong> <?=h($result['category'])?></p>
      <p><strong>Abertura:</strong> <?=h($result['created_at'])?> · <strong>Última atualização:</strong> <?=h($result['updated_at'])?></p>
      <p><strong>Descrição:</strong> <?=h($result['description'] ?? '')?></p>
    </section>
  <?php endif; ?>

  <div class="sd-grid">
    <section class="sd-card sd-card--accent">
      <h2>Abrir chamado</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['sd_csrf'])?>">
        <input type="hidden" name="action" value="create">
        <label>Nome<input required maxlength="130" name="name"></label>
        <label>E-mail<input type="email" name="email" maxlength="190"></label>
        <label>Assunto<input required name="title" maxlength="180"></label>
        <label>Categoria
          <select name="category">
            <option>Geral</option><option>Equipamentos</option><option>Rede</option><option>Sistema</option><option>Acesso</option><option>IoT</option>
          </select>
        </label>
        <label>Prioridade
          <select name="priority">
            <option value="baixa">Baixa</option><option value="media" selected>Média</option><option value="alta">Alta</option><option value="critica">Crítica</option>
          </select>
        </label>
        <label>Descrição<textarea required name="description" maxlength="10000" rows="5"></textarea></label>
        <label class="trap" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <button type="submit">Gerar protocolo</button>
      </form>
    </section>

    <section class="sd-card sd-card--accent">
      <h2>Consultar andamento</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=h($_SESSION['sd_csrf'])?>">
        <input type="hidden" name="action" value="lookup">
        <label>Protocolo<input required name="protocol" placeholder="PI6-AAAAMMDD-XXXXXXXXXX"></label>
        <label>Código privado<input required name="token" autocomplete="off"></label>
        <button>Consultar</button>
      </form>
      <p>Seus dados não aparecem publicamente nas consultas.</p>
      <?php if ($demoMode):
        $seed = reset($_SESSION['sd_demo_tickets']); ?>
        <div class="notice">
          <strong>Exemplo de teste:</strong><br>
          Protocolo demo: <code><?=h($seed['protocol'])?></code><br>
          Token demo: <code><?=h($seed['consultation_token'])?></code>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>
</body>
</html>
