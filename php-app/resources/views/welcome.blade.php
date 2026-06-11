<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Home Aidvor — управление Arduino и IoT-устройствами через интернет</title>
  <meta name="description" content="Home Aidvor — облачная платформа для удалённого управления Arduino, ESP и другими контроллерами. Автополив, реле, датчики, теплицы, двор и дача." />
  <link rel="stylesheet" href="{{ asset('assets/theme.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/landing-light.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/landing-dark.css') }}">
  @include('layouts.theme-init')
</head>
<body>
  <header class="header">
    <div class="container nav">
      <a href="/" class="logo" aria-label="Home Aidvor">
        <span class="logo-mark">A</span>
        <span>Home Aidvor</span>
      </a>
      <nav class="nav-links" aria-label="Главное меню">
        <a href="#cases">Применение</a>
        <a href="#how">Как работает</a>
        <a href="#plans">Тарифы</a>
        <a href="#kits">Комплекты</a>
        <a href="#faq">FAQ</a>
      </nav>
      <div class="nav-actions">
        <button
          type="button"
          class="landing-theme-toggle"
          id="landingThemeToggle"
          aria-label="{{ __('Theme') }}"
          title="{{ __('Theme') }}"
        >
          <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
          <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
        </button>
        <a class="btn btn-secondary" href="{{ route('login') }}">Войти</a>
        <a class="btn btn-primary" href="{{ route('register') }}">Подключить бесплатно</a>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="container hero-grid">
        <div>
          <div class="badge">Arduino • ESP • реле • датчики • автополив</div>
          <h1>Управляйте самодельными устройствами через интернет</h1>
          <p class="hero-text">
            Home Aidvor — облачная платформа для удалённого управления контроллерами Arduino, ESP8266, ESP32 и любыми устройствами, которые умеют отправлять данные на сервер по API-контракту.
          </p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="{{ route('register') }}">Подключить первое устройство</a>
            <a class="btn btn-secondary" href="{{ route('home-arduino') }}">Посмотреть пример Arduino</a>
          </div>
          <div class="hero-note">Бесплатный тариф позволяет полноценно подключить и проверить одно устройство.</div>
        </div>

        <div class="device-card" aria-label="Пример панели управления устройством">
          <div class="panel-header">
            <strong>Теплица №1</strong>
            <span class="online">online</span>
          </div>
          <div class="status-row">
            <div class="status">
              <strong>24.8°</strong>
              <span>температура</span>
            </div>
            <div class="status">
              <strong>61%</strong>
              <span>влажность</span>
            </div>
            <div class="status">
              <strong>38%</strong>
              <span>почва</span>
            </div>
          </div>
          <div class="panel">
            <div class="panel-header">
              <strong>Управление реле</strong>
              <span>4 канала</span>
            </div>
            <div class="relay-list">
              <div class="relay"><span>Насос полива</span><span class="toggle"></span></div>
              <div class="relay"><span>Вентиляция</span><span class="toggle"></span></div>
              <div class="relay"><span>Освещение</span><span class="toggle off"></span></div>
              <div class="relay"><span>Резерв</span><span class="toggle off"></span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="cases">
      <div class="container">
        <div class="section-head">
          <div class="section-kicker">Для чего подходит</div>
          <h2>Не просто IoT-платформа, а понятный инструмент для реальных задач</h2>
          <p class="section-desc">Подключайте датчики, реле, насосы, клапаны и исполнительные устройства. Смотрите состояние, управляйте вручную или запускайте сценарии.</p>
        </div>
        <div class="cards">
          <article class="card">
            <div class="card-icon">🌱</div>
            <h3>Автополив теплицы</h3>
            <p>Контроль влажности почвы, температуры и включение насоса или клапанов по расписанию и условиям.</p>
          </article>
          <article class="card">
            <div class="card-icon">⚡</div>
            <h3>Управление реле</h3>
            <p>Включайте свет, вентиляцию, насосы, обогреватели и другие устройства удалённо из личного кабинета.</p>
          </article>
          <article class="card">
            <div class="card-icon">🏡</div>
            <h3>Дача и двор</h3>
            <p>Следите за состоянием участка, питания, воды и температуры даже когда вас нет рядом.</p>
          </article>
          <article class="card">
            <div class="card-icon">🔧</div>
            <h3>DIY-проекты</h3>
            <p>Соберите устройство на Arduino или ESP и подключите его к готовому серверу без разработки backend.</p>
          </article>
          <article class="card">
            <div class="card-icon">📊</div>
            <h3>Мониторинг</h3>
            <p>Получайте телеметрию, смотрите последние значения, историю и события контроллера.</p>
          </article>
          <article class="card">
            <div class="card-icon">🧩</div>
            <h3>Любой контроллер</h3>
            <p>Подойдёт любое устройство, которое может обратиться к серверу и передать данные по заданному контракту.</p>
          </article>
        </div>
      </div>
    </section>

    <section id="how">
      <div class="container">
        <div class="section-head">
          <div class="section-kicker">Как это работает</div>
          <h2>Контроллер отправляет данные, сайт показывает состояние и отдаёт команды</h2>
          <p class="section-desc">Home Aidvor связывает ваши устройства, сценарии и интерфейс управления в одну понятную систему.</p>
        </div>
        <div class="steps">
          <div class="step">
            <h3>Соберите устройство</h3>
            <p>Arduino, ESP8266, ESP32 или другой контроллер с датчиками и реле.</p>
          </div>
          <div class="step">
            <h3>Подключите к серверу</h3>
            <p>Устройство отправляет отчёты на Home Aidvor по API-контракту.</p>
          </div>
          <div class="step">
            <h3>Настройте сценарии</h3>
            <p>Задайте правила: когда включать насос, свет, вентиляцию или другой выход.</p>
          </div>
          <div class="step">
            <h3>Управляйте онлайн</h3>
            <p>Открывайте кабинет с телефона или компьютера и контролируйте устройство удалённо.</p>
          </div>
        </div>
      </div>
    </section>

    <section>
      <div class="container split">
        <div class="card">
          <div class="section-kicker">Для пользователей</div>
          <h2>Запустите первый проект без покупки промышленной IoT-системы</h2>
          <p class="section-desc">Бесплатный тариф позволяет проверить идею, подключить контроллер и управлять устройством через сайт.</p>
          <div class="feature-list">
            <div class="feature-item">Подключение первого контроллера бесплатно</div>
            <div class="feature-item">Готовые примеры для Arduino и ESP</div>
            <div class="feature-item">Управление реле и просмотр данных</div>
            <div class="feature-item">Подходит для теплиц, дач, двора и DIY-проектов</div>
          </div>
        </div>
        <div class="card">
          <div class="section-kicker">Для разработчиков и интеграторов</div>
          <h2>Подключайте устройства через открытый контракт</h2>
          <p class="section-desc">Сервер не привязан к конкретной плате. Если устройство умеет отправлять данные и принимать команды, его можно интегрировать.</p>
          <div class="feature-list">
            <div class="feature-item">API-контракт для передачи состояния контроллера</div>
            <div class="feature-item">Группы устройств и профили</div>
            <div class="feature-item">Сценарии автоматического управления</div>
            <div class="feature-item">Возможность развивать решения для клиентов</div>
          </div>
        </div>
      </div>
    </section>

    <section id="plans">
      <div class="container">
        <div class="section-head">
          <div class="section-kicker">Тарифы</div>
          <h2>Начните бесплатно и переходите на платный тариф, когда проект вырастет</h2>
          <p class="section-desc">Бесплатный тариф нужен для запуска первого устройства. Платные тарифы добавляют больше устройств, историю, уведомления и удобство.</p>
        </div>
        @php
          $landingPlans = $plans ?? collect();
          if ($landingPlans->isEmpty()) {
              $landingPlans = \App\Models\Plan::query()
                  ->where('is_active', true)
                  ->orderBy('daily_price_units')
                  ->get();
          }
        @endphp
        <div class="pricing">
          @forelse ($landingPlans as $plan)
            @php($monthlyPriceUnits = (int) ($plan->daily_price_units ?? 0) * 31)
            <article class="price-card {{ $loop->iteration === 2 ? 'highlight' : '' }}">
              <div class="price-card-title">
                <h3>{{ $plan->name }}</h3>
              </div>
              <div class="price-card-price">
                <div class="price">{{ number_format($monthlyPriceUnits, 0, '.', ' ') }} <small>{{ __('units/month') }}</small></div>
              </div>
              <p class="price-card-description">
                @if ($plan->description)
                  {{ $plan->description }}
                @else
                  &nbsp;
                @endif
              </p>
              <ul class="price-card-limits">
                <li>{{ __('Report quota') }}: {{ (int) ($plan->report_max_requests_per_epoch ?? 0) > 0 ? number_format((int) $plan->report_max_requests_per_epoch, 0, '.', ' ') : __('Auto') }} / {{ (int) ($plan->report_epoch_seconds ?? 300) }} {{ __('sec') }}</li>
                <li>{{ __('pin_data limit') }}: {{ (int) ($plan->max_pin_data_rows ?? 0) > 0 ? number_format((int) $plan->max_pin_data_rows, 0, '.', ' ') : __('No limit') }}</li>
                <li>{{ __('Scenarios') }}: {{ (int) ($plan->max_scenarios ?? 0) > 0 ? number_format((int) $plan->max_scenarios, 0, '.', ' ') : __('No limit') }}</li>
                <li>{{ __('Scenario Conditions') }}: {{ (int) ($plan->max_scenario_conditions ?? 0) > 0 ? number_format((int) $plan->max_scenario_conditions, 0, '.', ' ') : __('No limit') }}</li>
              </ul>
              <div class="price-card-action">
                <a class="btn {{ $loop->iteration === 2 ? 'btn-primary' : 'btn-secondary' }}" href="{{ auth()->check() ? route('user.plans.index') : route('register') }}">
                  {{ auth()->check() ? __('Open plans') : __('Choose Plan') }}
                </a>
              </div>
            </article>
          @empty
            <article class="price-card">
              <div class="price-card-title">
                <h3>{{ __('No active plans available.') }}</h3>
              </div>
              <div class="price-card-price"></div>
              <p class="price-card-description">{{ __('Please check back later.') }}</p>
              <ul class="price-card-limits"></ul>
              <div class="price-card-action"></div>
            </article>
          @endforelse
        </div>
      </div>
    </section>

    <section id="kits">
      <div class="container">
        <div class="shop-banner">
          <div>
            <div class="section-kicker" style="color:#bce8c7">Готовые решения</div>
            <h2>Не хотите собирать сами? Используйте готовые комплекты</h2>
            <p>Платформа может работать не только как облачный сервис, но и как магазин совместимых устройств: контроллеров, датчиков, реле, корпусов и комплектов для теплиц.</p>
            <a class="btn btn-secondary" href="{{ route('register') }}">Оставить заявку на комплект</a>
          </div>
          <div class="kit-list">
            <div>Комплект автополива</div>
            <div>Контроллер теплицы</div>
            <div>Wi‑Fi реле для двора</div>
            <div>OpenWrt шлюз для устройств</div>
          </div>
        </div>
      </div>
    </section>

    <section id="faq">
      <div class="container">
        <div class="section-head">
          <div class="section-kicker">Вопросы</div>
          <h2>Коротко о главном</h2>
        </div>
        <div class="faq">
          <div class="faq-item">
            <h3>Обязательно использовать Arduino?</h3>
            <p>Нет. Arduino — только один из вариантов. Подойдёт любое устройство, которое может обращаться к серверу по заданному контракту.</p>
          </div>
          <div class="faq-item">
            <h3>Можно ли начать бесплатно?</h3>
            <p>Да. Бесплатный тариф позволяет полноценно подключить первое устройство и проверить работу платформы.</p>
          </div>
          <div class="faq-item">
            <h3>Что можно подключить?</h3>
            <p>Датчики температуры, влажности, освещённости, реле, насосы, клапаны, вентиляцию и другие исполнительные устройства.</p>
          </div>
          <div class="faq-item">
            <h3>Нужны ли навыки программирования?</h3>
            <p>Для DIY-подключения нужны базовые навыки. Для пользователей без опыта можно использовать готовые комплекты и инструкции.</p>
          </div>
        </div>
      </div>
    </section>

    <section>
      <div class="container">
        <div class="cta">
          <h2>Подключите первое устройство и проверьте Home Aidvor на реальном проекте</h2>
          <p>Начните с одного контроллера: отправьте данные на сервер, настройте управление реле и посмотрите состояние устройства в личном кабинете.</p>
          <div class="hero-actions cta-actions">
            <a class="btn btn-primary" href="{{ route('register') }}">Зарегистрироваться бесплатно</a>
            <a class="btn btn-secondary" href="{{ route('home-arduino') }}">Открыть инструкцию Arduino</a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container footer-grid">
      <div>© Home Aidvor</div>
      <div>Удалённое управление контроллерами, датчиками и реле</div>
    </div>
  </footer>
  @include('layouts.theme-runtime')
  <script>
    (function () {
      var toggle = document.getElementById('landingThemeToggle');
      if (!toggle) return;

      function resolveMode(mode) {
        if (mode === 'dark' || mode === 'light') return mode;
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
      }

      function applyState(mode) {
        var resolved = resolveMode(mode);
        toggle.setAttribute('data-mode', resolved);
        toggle.setAttribute('aria-pressed', resolved === 'dark' ? 'true' : 'false');
      }

      function getMode() {
        return (window.AidvorTheme && window.AidvorTheme.getMode)
          ? window.AidvorTheme.getMode()
          : (localStorage.getItem('aidvor_theme_mode') || 'system');
      }

      function setMode(mode) {
        if (window.AidvorTheme && window.AidvorTheme.setMode) {
          window.AidvorTheme.setMode(mode);
        } else {
          localStorage.setItem('aidvor_theme_mode', mode);
          document.documentElement.setAttribute('data-theme-mode', mode);
          document.documentElement.setAttribute('data-theme', resolveMode(mode));
        }
      }

      applyState(getMode());

      toggle.addEventListener('click', function () {
        var current = resolveMode(getMode());
        var next = current === 'dark' ? 'light' : 'dark';
        setMode(next);
        applyState(next);
      });

      window.addEventListener('storage', function (e) {
        if (e.key === 'aidvor_theme_mode') applyState(e.newValue || 'system');
      });
    })();
  </script>
</body>
</html>
