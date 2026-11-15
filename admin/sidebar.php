<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

// $_nb is set by navbar.php (always included before sidebar.php).
// Fallback keeps sidebar safe if ever included standalone.
if (!isset($_nb)) {
    $_navbarAdminDir  = rtrim(str_replace('\\', '/', __DIR__), '/');
    $_navbarDocRoot   = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $_nb = ($_navbarDocRoot !== '' && strpos($_navbarAdminDir, $_navbarDocRoot) === 0)
        ? '/' . ltrim(substr($_navbarAdminDir, strlen($_navbarDocRoot)), '/') . '/'
        : '/admin/';
}

if (!function_exists('menuActive')) {
    function menuActive(string $current, array $targets): string
    {
        return in_array($current, $targets, true) ? 'is-active' : '';
    }
}
?>
<aside class="admin-sidebar" id="sidebar" aria-label="Menu lateral">
    <div class="admin-sidebar-inner">
        <div class="admin-sidebar-title">Navegação</div>

        <?php require_once __DIR__.'/../php/access_policy_v7.php'; ?>
        <div class="admin-menu-group">
            <h6>V7 · Gestão integrada</h6>
            <ul class="admin-menu-list">
                <li><a class="<?php echo menuActive($currentPage, ['meu-trabalho-v7.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'meu-trabalho-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-home" aria-hidden="true"></i><span>Meu trabalho</span></a></li>
                <?php if(cfCan('timeline.view',$_SESSION)): ?><li><a class="<?php echo menuActive($currentPage, ['historico-operacional-v7.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'historico-operacional-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-history" aria-hidden="true"></i><span>Histórico operacional</span></a></li><?php endif; ?>
                <?php if(cfCan('crm.view',$_SESSION)): ?><li><a href="<?php echo htmlspecialchars($_nb . 'crm/ficha-360-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-address-card" aria-hidden="true"></i><span>Cliente 360°</span></a></li><?php endif; ?>
                <?php if(cfCan('finance.view',$_SESSION)): ?><li><a href="<?php echo htmlspecialchars($_nb . 'financeiro/lancamentos-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-receipt" aria-hidden="true"></i><span>Livro financeiro</span></a></li><?php endif; ?>
                <?php if(cfCan('admin.manage',$_SESSION)): ?><li><a class="<?php echo menuActive($currentPage, ['diagnostico-v7.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'diagnostico-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-heartbeat" aria-hidden="true"></i><span>Diagnóstico</span></a></li><li><a href="<?php echo htmlspecialchars($_nb . 'catalogo-componentes-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-th-large" aria-hidden="true"></i><span>Componentes visuais</span></a></li><?php endif; ?>
                <li><a href="<?php echo htmlspecialchars($_nb . 'pesquisa-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-search" aria-hidden="true"></i><span>Pesquisa global</span></a></li>
                <li><a href="<?php echo htmlspecialchars($_nb . 'preferencias-v7.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-sliders-h" aria-hidden="true"></i><span>Preferências</span></a></li>
            </ul>
        </div>

        <div class="admin-menu-group">
            <h6>Principal</h6>
            <ul class="admin-menu-list">
                <li>
                    <a class="<?php echo menuActive($currentPage, ['admin.php', 'admin_CORRIGIDO.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'admin.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-chart-pie" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['requisicoes.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'requisicoes.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-file-signature" aria-hidden="true"></i>
                        <span>Requisições</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['monitoramento.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'monitoramento.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-chart-line" aria-hidden="true"></i>
                        <span>Monitoramento</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="admin-menu-group">
            <h6>Relacionamento</h6>
            <ul class="admin-menu-list">
                <li>
                    <a class="<?php echo menuActive($currentPage, ['listaclientes.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'listaclientes.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-users" aria-hidden="true"></i>
                        <span>Clientes</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['mod_depoimentos.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'mod_depoimentos.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-comments" aria-hidden="true"></i>
                        <span>Depoimentos</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="admin-menu-group">
            <h6>Administração</h6>
            <ul class="admin-menu-list">
                <li>
                    <a class="<?php echo menuActive($currentPage, ['listarusuario.php', 'editusuario.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'listarusuario.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-user-cog" aria-hidden="true"></i>
                        <span>Usuários</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['addusuario.php', 'novousuario.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'addusuario.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-user-plus" aria-hidden="true"></i>
                        <span>Novo usuário</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['reset_senha.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'reset_senha.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-key" aria-hidden="true"></i>
                        <span>Reset de senha</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['perfil.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'perfil.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-id-card" aria-hidden="true"></i>
                        <span>Meu perfil</span>
                    </a>
                </li>
            </ul>
        </div>

        <details class="admin-menu-group admin-menu-advanced">
            <summary>
                <i class="fa fa-flask" aria-hidden="true"></i>
                <span>Módulos avançados</span>
            </summary>
            <ul class="admin-menu-list compact">
                <li><a href="<?php echo htmlspecialchars($_nb . 'dashboard/dashboard-inteligente.php', ENT_QUOTES, 'UTF-8'); ?>">Dashboard Inteligente</a></li>
                <li><a href="<?php echo htmlspecialchars($_nb . 'crm/pipeline.php', ENT_QUOTES, 'UTF-8'); ?>">Pipeline CRM</a></li>
                <li><a href="<?php echo htmlspecialchars($_nb . 'analytics/advanced-analytics.php', ENT_QUOTES, 'UTF-8'); ?>">Analytics</a></li>
                <li><a href="<?php echo htmlspecialchars($_nb . 'financeiro/finance-dashboard.php', ENT_QUOTES, 'UTF-8'); ?>">Financeiro</a></li>
            </ul>
        </details>

        <div class="admin-menu-group">
            <h6><i class="fa fa-microchip me-1"></i> IoT &amp; Atendimento</h6>
            <ul class="admin-menu-list">
                <li><a class="<?php echo menuActive($currentPage, ['central-operacoes.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'central-operacoes.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-th-large" aria-hidden="true"></i><span>Central V5</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['service-desk-kanban.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'service-desk-kanban.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-columns" aria-hidden="true"></i><span>Kanban</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['pesquisa-global.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'pesquisa-global.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-search" aria-hidden="true"></i><span>Pesquisa operacional</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['iot-dispositivo.php','iot-monitoramento.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'iot-monitoramento.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-heartbeat" aria-hidden="true"></i><span>Saúde IoT</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['service-desk.php','service-desk-detalhe.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'service-desk.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-headset" aria-hidden="true"></i><span>Service Desk</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['service-desk-notificacoes.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'service-desk-notificacoes.php',ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-bell" aria-hidden="true"></i><span>Alertas operacionais</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['iot-simulador.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'iot-simulador.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-microchip" aria-hidden="true"></i><span>Simulador IoT</span></a></li>
                <li><a class="<?php echo menuActive($currentPage, ['service-desk-regras.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'service-desk-regras.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-sliders" aria-hidden="true"></i><span>Regras IoT</span></a></li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['fila.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'fila.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-ticket-alt" aria-hidden="true"></i>
                        <span>Fila de Atendimento</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['sla.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'sla.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-tachometer-alt" aria-hidden="true"></i>
                        <span>Monitor SLA</span>
                    </a>
                </li>
                <li>
                    <a class="<?php echo menuActive($currentPage, ['checkin.php', 'checkin_poll.php']); ?>" href="<?php echo htmlspecialchars($_nb . 'checkin.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa fa-qrcode" aria-hidden="true"></i>
                        <span>Check-in / QR Code</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>
