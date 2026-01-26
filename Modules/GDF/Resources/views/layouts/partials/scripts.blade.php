<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<script>
(function () {
    // THEME
    const themeKey = 'gdf_theme';
    const body = document.body;
    const btnTheme = document.getElementById('toggleTheme');

    function applyTheme(mode) {
        body.dataset.theme = mode;
        if (btnTheme) {
            btnTheme.innerHTML = (mode === 'dark')
                ? '<i class="bi bi-sun"></i>'
                : '<i class="bi bi-moon-stars"></i>';
        }
    }

    applyTheme(localStorage.getItem(themeKey) || 'dark');

    btnTheme?.addEventListener('click', () => {
        const next = (body.dataset.theme === 'dark') ? 'light' : 'dark';
        localStorage.setItem(themeKey, next);
        applyTheme(next);
    });

    // SIDEBAR
    const sidebarKey = 'gdf_sidebar';
    const sidebar = document.getElementById('gdfSidebar');
    const btnSidebar = document.getElementById('toggleSidebar');

    function applySidebar(state) {
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed', state === 'collapsed');
    }

    applySidebar(localStorage.getItem(sidebarKey) || 'expanded');

    btnSidebar?.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.contains('collapsed');
        const next = isCollapsed ? 'expanded' : 'collapsed';
        localStorage.setItem(sidebarKey, next);
        applySidebar(next);
    });
})();
</script>

@stack('js')
