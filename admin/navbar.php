<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$displayName = trim((string) ($_SESSION['nome'] ?? $_SESSION['username'] ?? 'Usuário'));
$displayEmail = trim((string) ($_SESSION['email'] ?? ''));
$displayPerfil = strtolower((string) ($_SESSION['perfil'] ?? 'usuario'));
$displayPerfilLabel = $displayPerfil === 'admin' ? 'Administrador' : 'Usuário';

$parts = preg_split('/\s+/', $displayName) ?: [];
$first = isset($parts[0]) ? (function_exists('mb_substr') ? mb_substr((string) $parts[0], 0, 1) : substr((string) $parts[0], 0, 1)) : 'U';
$second = isset($parts[1]) ? (function_exists('mb_substr') ? mb_substr((string) $parts[1], 0, 1) : substr((string) $parts[1], 0, 1)) : '';
$initials = strtoupper($first . $second);
if ($initials === '') {
    $initials = 'US';
}

$cssVersion = (string) (@filemtime(__DIR__ . '/assets/css/admin-ui.css') ?: time());
$jsVersion  = (string) (@filemtime(__DIR__ . '/assets/js/admin-tailwind-enhancer.js') ?: time());

// ── Caminho base absoluto para /admin/ ────────────────────────────────────
// Funciona independentemente da profundidade do arquivo que inclui este navbar
// (ex.: /admin/admin.php  OU  /admin/crm/pipeline.php, etc.)
$_navbarAdminDir   = rtrim(str_replace('\\', '/', __DIR__), '/');          // caminho físico de /admin
$_navbarDocRoot    = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$_navbarAdminBase  = ($_navbarDocRoot !== '' && strpos($_navbarAdminDir, $_navbarDocRoot) === 0)
    ? '/' . ltrim(substr($_navbarAdminDir, strlen($_navbarDocRoot)), '/') . '/'
    : '/admin/';   // fallback seguro
$_nb = $_navbarAdminBase; // atalho usado nas tags abaixo
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($_nb . 'assets/css/admin-ui.css?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
<script src="<?php echo htmlspecialchars($_nb . 'assets/js/admin-tailwind-enhancer.js?v=' . $jsVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<link rel="stylesheet" href="<?php echo htmlspecialchars($_nb . 'assets/css/confinter-admin-design-system.css?v=' . (string)(@filemtime(__DIR__ . '/assets/css/confinter-admin-design-system.css') ?: 1), ENT_QUOTES, 'UTF-8'); ?>">
<script defer src="<?php echo htmlspecialchars($_nb . 'assets/js/confinter-admin-design-system.js?v=' . (string)(@filemtime(__DIR__ . '/assets/js/confinter-admin-design-system.js') ?: 1), ENT_QUOTES, 'UTF-8'); ?>"></script>

<div id="global-loader">
    <div class="admin-loader-spinner"></div>
</div>

<div class="main-wrapper admin-shell">
    <header class="admin-topbar">
        <div class="admin-topbar-inner">
            <button id="mobile_btn" class="admin-mobile-btn" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menu lateral">
                <i class="fa fa-bars" aria-hidden="true"></i>
            </button>

            <a href="<?php echo htmlspecialchars($_nb . 'admin.php', ENT_QUOTES, 'UTF-8'); ?>" class="admin-brand">
                <img src="<?php echo htmlspecialchars($_nb . 'assets/img/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="CONFINTER" width="38" height="38">
                <span>CONFINTER Admin</span>
            </a>

            <form action="<?php echo htmlspecialchars($_nb . 'pesquisa-v7.php',ENT_QUOTES,'UTF-8'); ?>" method="get" class="v5-topsearch" role="search"><label class="sr-only" for="v5-global-q">Pesquisar protocolos, clientes e dispositivos</label><input id="v5-global-q" type="search" name="q" maxlength="100" placeholder="Buscar registros" aria-label="Buscar registros"><button type="submit" aria-label="Pesquisar">⌕</button></form>
            <div class="admin-topbar-actions">
                <button
                    id="toggle_btn"
                    class="admin-link-chip admin-collapse-trigger"
                    type="button"
                    aria-label="Retrair ou expandir menu lateral"
                    aria-expanded="true">
                    <i class="fa fa-bars" aria-hidden="true"></i>
                    <span>Menu</span>
                </button>

                <a href="../index.php" class="admin-link-chip <?php echo $currentPage === 'index.php' ? 'is-active' : ''; ?>">
                    <i class="fa fa-globe" aria-hidden="true"></i>
                    <span>Ver site</span>
                </a>

                <div class="dropdown">
                    <button class="admin-user-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end admin-user-menu">
                        <div class="admin-user-meta">
                            <strong><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : 'Sem e-mail cadastrado', ENT_QUOTES, 'UTF-8'); ?></small>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($displayPerfilLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?php echo htmlspecialchars($_nb . 'perfil.php', ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa fa-user me-2" aria-hidden="true"></i> Meu perfil
                        </a>
                        <a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($_nb . 'logout.php', ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa fa-sign-out-alt me-2" aria-hidden="true"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div id="admin-sidebar-overlay" class="admin-sidebar-overlay" aria-hidden="true"></div>

    <?php if (!empty($_SESSION['form_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show admin-alerts" role="alert">
            <i class="fa fa-check-circle me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars((string) $_SESSION['form_success'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['form_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['form_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show admin-alerts" role="alert">
            <i class="fa fa-exclamation-triangle me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars((string) $_SESSION['form_error'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['form_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_error'])): ?>
        <div class="alert alert-warning alert-dismissible fade show admin-alerts" role="alert">
            <i class="fa fa-exclamation-circle me-2" aria-hidden="true"></i>
            <?php echo htmlspecialchars((string) $_SESSION['login_error'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

