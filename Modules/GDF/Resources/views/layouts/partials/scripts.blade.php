{{-- Bootstrap JS (UNA SOLA VEZ) --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- Scripts propios --}}
<script>
    (function() {
        // THEME
        const themeKey = 'gdf_theme';
        const body = document.body;
        const btnTheme = document.getElementById('toggleTheme');

        function applyTheme(mode) {
            body.dataset.theme = mode;
            if (btnTheme) {
                btnTheme.innerHTML = (mode === 'dark') ?
                    '<i class="bi bi-sun"></i>' :
                    '<i class="bi bi-moon-stars"></i>';
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
<script>
    (function() {
        const btn = document.getElementById('toggleTheme');
        if (!btn) return;

        function applyIcon(theme) {
            const icon = btn.querySelector('i');
            if (!icon) return;
            icon.classList.remove('bi-moon-stars', 'bi-sun');
            icon.classList.add(theme === 'light' ? 'bi-moon-stars' : 'bi-sun');
            // idea: en dark muestro "sun" (para pasar a light), en light muestro "moon"
        }

        function getTheme() {
            return document.documentElement.getAttribute('data-theme') || 'dark';
        }

        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('gdf_theme', theme);
            } catch (e) {}
            applyIcon(theme);
        }

        // init icon
        applyIcon(getTheme());

        btn.addEventListener('click', function() {
            const cur = getTheme();
            setTheme(cur === 'light' ? 'dark' : 'light');
        });
    })();
</script>


{{-- JS de vistas hijas --}}
@stack('js')
