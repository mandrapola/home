<script>
    (function () {
        var KEY = 'aidvor_theme_mode';
        function resolveTheme(mode) {
            if (mode === 'dark' || mode === 'light') return mode;
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        function applyTheme(mode) {
            var resolved = resolveTheme(mode);
            document.documentElement.setAttribute('data-theme-mode', mode);
            document.documentElement.setAttribute('data-theme', resolved);
        }
        window.AidvorTheme = {
            getMode: function () { return localStorage.getItem(KEY) || 'system'; },
            setMode: function (mode) {
                localStorage.setItem(KEY, mode);
                applyTheme(mode);
            },
        };
        window.addEventListener('storage', function (e) {
            if (e.key === KEY) applyTheme(e.newValue || 'system');
        });
        var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        if (media) {
            var listener = function () {
                if ((localStorage.getItem(KEY) || 'system') === 'system') applyTheme('system');
            };
            if (media.addEventListener) media.addEventListener('change', listener);
            else if (media.addListener) media.addListener(listener);
        }
    })();
</script>
