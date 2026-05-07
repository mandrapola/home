<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brand Verification | {{ config('app.name', 'Home Aidvor') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
    @include('layouts.theme-init')
</head>
<body class="theme-body">
<div class="container py-4 py-lg-5">
    <div class="theme-shell">
        <header class="theme-header">
            <div class="container py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fs-5 fw-semibold">Home Aidvor</div>
                <div class="d-flex gap-2 flex-wrap">
                    @include('layouts.theme-switcher', ['compact' => true, 'id' => 'brand_theme_switcher'])
                    <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-sm">Главная</a>
                    <a href="{{ url('/home-arduino') }}" class="btn btn-outline-secondary btn-sm">Документация</a>
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-sm">Личный кабинет</a>
                </div>
            </div>
        </header>

        <main class="container py-4">
            <div class="card border-0 mb-3">
                <div class="card-body">
                    <h1 class="h3 mb-3">Подтверждение прав использования бренда</h1>
                    <p class="text-muted mb-0">
                        Данная страница размещена для верификации бренда и прав на публикацию интеграций
                        в экосистемах умного дома, включая Яндекс Алису.
                    </p>
                </div>
            </div>

            <div class="card border-0 mb-3">
                <div class="card-body">
                    <h2 class="h5 mb-3">Официальное заявление</h2>
                    <p class="text-muted mb-2">
                        Мы подтверждаем право использования наименования <strong>Home Aidvor</strong> для размещения
                        веб-сервиса, API и интеграций в платформах умного дома.
                    </p>
                    <p class="text-muted mb-0">
                        Данный сайт является официальной точкой публикации информации о бренде и интеграциях.
                    </p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Информация о бренде</h2>
                            <dl class="mb-0">
                                <dt class="mb-1">Название бренда</dt>
                                <dd class="text-muted mb-3">Home Aidvor</dd>

                                <dt class="mb-1">Официальный сайт</dt>
                                <dd class="text-muted mb-3">
                                    <a href="https://home.aidvor.ru" target="_blank" rel="noopener">https://home.aidvor.ru</a>
                                </dd>

                                <dt class="mb-1">Назначение сервиса</dt>
                                <dd class="text-muted mb-0">Управление контроллерами автоматизации, датчиками и сценариями умного дома.</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 h-100">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Подтверждение прав</h2>
                            <p class="text-muted mb-3">
                                Бренд <strong>Home Aidvor</strong> используется для данного веб-сервиса,
                                API-интеграций и навыков умного дома. Владельцы сервиса размещают и поддерживают
                                официальные интеграции от имени этого бренда.
                            </p>
                            <p class="text-muted mb-0">
                                Страница действительна для процедуры проверки прав использования бренда
                                при публикации и сопровождении интеграций.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Контакты и полезные ссылки</h2>
                            <ul class="mb-0 text-muted">
                                <li>Контактный email: <a href="mailto:{{ config('mail.from.address', 'hello@example.com') }}">{{ config('mail.from.address', 'hello@example.com') }}</a></li>
                                <li>Профиль пользователя: <a href="{{ url('/profile') }}">{{ url('/profile') }}</a></li>
                                <li>Серверный API-контракт: <a href="{{ route('home-arduino.server-contract') }}">{{ route('home-arduino.server-contract') }}</a></li>
                                <li>Базовый домен сервиса: <a href="{{ url('/') }}">{{ url('/') }}</a></li>
                                <li>Дата актуализации страницы: {{ now()->format('Y-m-d') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@include('layouts.theme-runtime')
</body>
</html>
