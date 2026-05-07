@php
    $compact = $compact ?? false;
    $id = $id ?? ('theme_switcher_' . uniqid());
@endphp
<div class="d-flex align-items-center gap-2">
    @if (! $compact)
        <label for="{{ $id }}" class="small text-muted mb-0">{{ __('Theme') }}</label>
    @endif
    <select
        id="{{ $id }}"
        class="form-select form-select-sm theme-select"
        aria-label="{{ __('Theme') }}"
        onchange="
            if (window.AidvorTheme) {
                window.AidvorTheme.setMode(this.value);
            } else {
                localStorage.setItem('aidvor_theme_mode', this.value);
                var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var resolved = this.value === 'system' ? (isDark ? 'dark' : 'light') : this.value;
                document.documentElement.setAttribute('data-theme-mode', this.value);
                document.documentElement.setAttribute('data-theme', resolved);
            }
        "
    >
        <option value="light">{{ __('Light') }}</option>
        <option value="dark">{{ __('Dark') }}</option>
        <option value="system">{{ __('System') }}</option>
    </select>
</div>
<script>
    (function () {
        var el = document.getElementById(@json($id));
        if (!el) return;
        var mode = (window.AidvorTheme && window.AidvorTheme.getMode)
            ? window.AidvorTheme.getMode()
            : (localStorage.getItem('aidvor_theme_mode') || 'system');
        el.value = mode;
    })();
</script>
