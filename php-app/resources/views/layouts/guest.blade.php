<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AiDvor SmartHome') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
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
</head>
<body class="theme-body">
    <style>
        .guest-brand {
            color: var(--text);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .guest-subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }
        .guest-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 12px 30px rgba(17, 34, 68, 0.08);
        }
    </style>
    <div class="container py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="text-center mb-3">
                    <a href="/" class="text-decoration-none guest-brand">AiDvor®</a>
                    <div class="guest-subtitle">{{ __('SmartHome Platform') }}</div>
                    <div class="d-flex justify-content-center mt-2">
                        @include('layouts.theme-switcher', ['compact' => true])
                    </div>
                </div>
                <div class="guest-card">
                    <div class="card-body p-4">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>
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
        })();
    </script>
</body>
</html>
