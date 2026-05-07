<script>
    (function () {
        var KEY = 'aidvor_theme_mode';
        var mode = localStorage.getItem(KEY) || 'system';
        var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var resolved = mode === 'system' ? (isDark ? 'dark' : 'light') : mode;
        document.documentElement.setAttribute('data-theme-mode', mode);
        document.documentElement.setAttribute('data-theme', resolved);
    })();
</script>
