export const renderDashboardHtml = () => `<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>home-openwrt | Локальное управление</title>
    <style>
      :root {
        --bg: #f6f8fb;
        --card: #ffffff;
        --line: #dce3ee;
        --text: #1f2937;
        --muted: #6b7280;
        --ok: #0a7f44;
        --warn: #b45309;
        --off: #9ca3af;
        --on: #0ea5e9;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: "Segoe UI", Roboto, Arial, sans-serif;
        color: var(--text);
        background: radial-gradient(circle at 20% 0%, #eef3ff 0, var(--bg) 55%);
      }
      .wrap { max-width: 980px; margin: 0 auto; padding: 20px; }
      .head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
      .title { margin: 0; font-size: 1.4rem; }
      .sub { margin: 4px 0 0; color: var(--muted); font-size: 0.95rem; }
      .panel {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 14px;
        margin-top: 14px;
      }
      .status { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
      .badge {
        display: inline-flex; align-items: center; gap: 8px;
        border-radius: 999px; padding: 6px 10px; font-weight: 600; font-size: 0.9rem;
      }
      .badge.online { background: #e8f8ef; color: var(--ok); }
      .badge.offline { background: #fff4e5; color: var(--warn); }
      .pins { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 10px; }
      .pin {
        border: 1px solid var(--line); border-radius: 12px; padding: 12px; background: #fff;
      }
      .pin h3 { margin: 0 0 6px; font-size: 1rem; }
      .meta { color: var(--muted); font-size: 0.85rem; margin: 0 0 10px; }
      .toggle {
        width: 100%; border: 1px solid var(--line); background: #f9fafb; color: var(--text);
        border-radius: 10px; padding: 8px 10px; cursor: pointer; font-weight: 600;
      }
      .toggle.on { border-color: #8fd7f8; background: #e8f7ff; color: #0369a1; }
      .toggle.off { border-color: #e5e7eb; background: #f3f4f6; color: #4b5563; }
      .toggle:disabled { opacity: 0.5; cursor: not-allowed; }
      .hint { margin: 10px 0 0; color: var(--muted); font-size: 0.9rem; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="head">
        <div>
          <h1 class="title">Локальный режим управления</h1>
          <p class="sub">home-openwrt gateway · автономное управление контроллерами</p>
        </div>
        <div id="modeBadge" class="badge offline">режим: ...</div>
      </div>

      <section class="panel">
        <div class="status">
          <div><strong>cloud_reachable:</strong> <span id="cloudReachable">...</span></div>
          <div><strong>storage_mode:</strong> <span id="storageMode">...</span></div>
          <div><strong>last_cloud_ok_at:</strong> <span id="lastCloudOk">...</span></div>
        </div>
      </section>

      <section class="panel">
        <strong>Ручное управление нагрузкой</strong>
        <div class="pins" id="pins"></div>
        <p class="hint" id="manualHint"></p>
      </section>
    </div>

    <script>
      const els = {
        modeBadge: document.getElementById('modeBadge'),
        cloudReachable: document.getElementById('cloudReachable'),
        storageMode: document.getElementById('storageMode'),
        lastCloudOk: document.getElementById('lastCloudOk'),
        pins: document.getElementById('pins'),
        manualHint: document.getElementById('manualHint')
      }

      let currentMode = 'offline'

      const formatTs = (value) => {
        if (!value) return '—'
        const date = new Date(value)
        if (Number.isNaN(date.getTime())) return String(value)
        return date.toLocaleString('ru-RU')
      }

      const setMode = (mode) => {
        currentMode = mode
        els.modeBadge.textContent = 'режим: ' + mode
        els.modeBadge.className = 'badge ' + (mode === 'online' ? 'online' : 'offline')
        els.manualHint.textContent =
          mode === 'online'
            ? 'Онлайн: ручное управление заблокировано, команды приходят от глобального сервера.'
            : 'Оффлайн: доступно ручное управление локальными пинами.'
      }

      const renderPins = (pins) => {
        els.pins.innerHTML = ''
        for (const pin of pins) {
          const wrapper = document.createElement('article')
          wrapper.className = 'pin'

          const title = document.createElement('h3')
          title.textContent = pin.pin
          wrapper.appendChild(title)

          const meta = document.createElement('p')
          meta.className = 'meta'
          meta.textContent = 'источник: ' + (pin.source || '—')
          wrapper.appendChild(meta)

          const button = document.createElement('button')
          button.className = 'toggle ' + (Number(pin.value) > 0 ? 'on' : 'off')
          button.textContent = Number(pin.value) > 0 ? 'Включено' : 'Выключено'
          button.disabled = currentMode === 'online'
          button.addEventListener('click', async () => {
            const next = Number(pin.value) > 0 ? 0 : 1
            try {
              const response = await fetch('/api/local/pins/' + pin.pin + '/state', {
                method: 'PUT',
                headers: { 'content-type': 'application/json' },
                body: JSON.stringify({ value: next, source: 'local_dashboard' })
              })
              if (!response.ok) {
                throw new Error('HTTP ' + response.status)
              }
              await refresh()
            } catch (error) {
              alert('Не удалось изменить состояние ' + pin.pin + ': ' + (error?.message || error))
            }
          })
          wrapper.appendChild(button)

          els.pins.appendChild(wrapper)
        }
      }

      const refresh = async () => {
        const [statusResponse, pinsResponse] = await Promise.all([
          fetch('/api/system/status'),
          fetch('/api/local/pins')
        ])
        const status = await statusResponse.json()
        const pins = await pinsResponse.json()

        setMode(status.mode || 'offline')
        els.cloudReachable.textContent = String(status.cloud_reachable)
        els.storageMode.textContent = status.storage_mode || '—'
        els.lastCloudOk.textContent = formatTs(status.last_cloud_ok_at)
        renderPins(Array.isArray(pins.pins) ? pins.pins : [])
      }

      refresh()
      setInterval(refresh, 5000)
    </script>
  </body>
</html>
`
