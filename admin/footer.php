<footer class="admin-footer border-top bg-white py-2 px-3 text-center text-muted small">
    &copy; <?php echo date('Y'); ?> CONFINTER. Todos os direitos reservados.
</footer>
</div>

<script src="assets/js/jquery-3.6.0.min.js" defer></script>
<script src="assets/js/feather.min.js" defer></script>
<script src="assets/js/jquery.slimscroll.min.js" defer></script>
<script src="assets/js/bootstrap.bundle.min.js" defer></script>
<script src="assets/js/script.js" defer></script>
<script>
(function () {
    function hideLoader() {
        var loader = document.getElementById('global-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    function toggleMenu(forceOpen) {
        var body = document.body;
        var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !body.classList.contains('admin-menu-open');
        body.classList.toggle('admin-menu-open', shouldOpen);
    }

    function applySidebarState(toggleBtn) {
        var collapsed = localStorage.getItem('adminSidebarCollapsed') === '1';
        document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    function toggleSidebarCollapse(toggleBtn) {
        var collapsed = !document.body.classList.contains('admin-sidebar-collapsed');
        document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
        localStorage.setItem('adminSidebarCollapsed', collapsed ? '1' : '0');
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var mobileBtn = document.getElementById('mobile_btn');
        var collapseBtn = document.getElementById('toggle_btn');
        var overlay = document.getElementById('admin-sidebar-overlay');

        applySidebarState(collapseBtn);

        if (mobileBtn) {
            mobileBtn.addEventListener('click', function (event) {
                event.preventDefault();
                toggleMenu();
            });
        }

        if (collapseBtn) {
            collapseBtn.addEventListener('click', function (event) {
                event.preventDefault();
                toggleSidebarCollapse(collapseBtn);
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                toggleMenu(false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                toggleMenu(false);
            }
        });

        hideLoader();
    });

    window.addEventListener('load', function () {
        hideLoader();
    });

    setTimeout(hideLoader, 3000);
})();
</script>
