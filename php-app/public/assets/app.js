(() => {
  const state = {
    controllers: [],
    selectedControllerId: null,
    refreshTimer: null,
    editingControllerId: null,
    settingsSavePending: false,
    pinConfigs: [],
    pinSettingsSavePending: false,
    historyClearPendingPin: null,
    editingPin: null,
    historyRangeHours: 1,
  };

  const chartPalette = {
    thermometer: { line: '#d66b2c', fill: 'rgba(214, 107, 44, 0.22)' },
    pressure: { line: '#2a6f97', fill: 'rgba(42, 111, 151, 0.22)' },
    humidity: { line: '#2d936c', fill: 'rgba(45, 147, 108, 0.22)' },
    air_temperature: { line: '#d66b2c', fill: 'rgba(214, 107, 44, 0.22)' },
    air_humidity: { line: '#2d936c', fill: 'rgba(45, 147, 108, 0.22)' },
  };

  const elControllers = document.getElementById('controllers');
  const elCurrentController = document.getElementById('currentController');
  const elCards = document.getElementById('cards');
  const elMessage = document.getElementById('message');
  const elError = document.getElementById('error');
  const elRefreshInfo = document.getElementById('refreshInfo');
  const elHistoryCharts = document.getElementById('historyCharts');
  const elHistoryMessage = document.getElementById('historyMessage');
  const elHistoryRangeControls = document.getElementById('historyRangeControls');

  const elSettingsDialog = document.getElementById('controllerSettingsDialog');
  const elSettingsForm = document.getElementById('controllerSettingsForm');
  const elSettingsTitle = document.getElementById('controllerSettingsTitle');
  const elSettingsError = document.getElementById('controllerSettingsError');
  const elSettingsCancelBtn = document.getElementById('controllerSettingsCancelBtn');
  const elSettingsSaveBtn = document.getElementById('controllerSettingsSaveBtn');

  const elPinSettingsDialog = document.getElementById('pinSettingsDialog');
  const elPinSettingsForm = document.getElementById('pinSettingsForm');
  const elPinSettingsTitle = document.getElementById('pinSettingsTitle');
  const elPinSettingsError = document.getElementById('pinSettingsError');
  const elPinSettingsCancelBtn = document.getElementById('pinSettingsCancelBtn');
  const elPinSettingsSaveBtn = document.getElementById('pinSettingsSaveBtn');

  const isDigitalPin = (pin) => /^D\d+$/i.test(String(pin || '').trim());
  const isAnalogPin = (pin) => {
    const normalized = String(pin || '').trim().toLowerCase();
    return /^a\d+$/.test(normalized) || normalized === 'air_temperature' || normalized === 'air_humidity';
  };

  const toNumberOr = (value, fallback) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const setError = (message) => {
    elError.textContent = message || '';
  };

  const setMessage = (message) => {
    elMessage.textContent = message || '';
  };

  const setSettingsError = (message) => {
    if (elSettingsError) {
      elSettingsError.textContent = message || '';
    }
  };

  const setPinSettingsError = (message) => {
    if (elPinSettingsError) {
      elPinSettingsError.textContent = message || '';
    }
  };

  const updateHistoryRangeButtons = () => {
    if (!elHistoryRangeControls) return;
    const buttons = elHistoryRangeControls.querySelectorAll('[data-range-hours]');
    for (const button of buttons) {
      const range = Number(button.getAttribute('data-range-hours') || 0);
      if (range === state.historyRangeHours) {
        button.classList.add('active');
      } else {
        button.classList.remove('active');
      }
    }
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      ...options,
    });

    const text = await response.text();
    let data = null;
    try {
      data = text ? JSON.parse(text) : null;
    } catch (_) {
      data = null;
    }

    if (!response.ok) {
      throw new Error(data?.message || data?.error || `HTTP ${response.status}`);
    }

    return data;
  };

  const buildPinConfigForSave = (config) => {
    const base = {
      pin: String(config.pin || '').trim(),
      label: String(config.label || config.pin || '').trim(),
      unit: config.unit === null || config.unit === undefined ? null : String(config.unit).trim() || null,
      multiplier: toNumberOr(config.multiplier, 1),
      offset: toNumberOr(config.offset, 0),
      precision: Math.max(0, Math.trunc(toNumberOr(config.precision, 0))),
      average_interval_minutes: Math.max(1, Math.trunc(toNumberOr(config.average_interval_minutes, 5))),
      value_labels: {
        '0': String((config.value_labels && config.value_labels['0']) || 'Выключен'),
        '1': String((config.value_labels && config.value_labels['1']) || 'Включен'),
      },
      digital_style: String(config.digital_style || (isDigitalPin(config.pin) ? 'power' : 'sensor')),
      invert_digital_logic: Boolean(config.invert_digital_logic),
      desired_digital_value: config.desired_digital_value === null || config.desired_digital_value === undefined
        ? (isDigitalPin(config.pin) ? 0 : null)
        : (Number(config.desired_digital_value) > 0 ? 1 : 0),
      power_on_duration_seconds:
        config.power_on_duration_seconds === null ||
        config.power_on_duration_seconds === undefined ||
        config.power_on_duration_seconds === ''
          ? null
          : Math.max(0, Math.trunc(toNumberOr(config.power_on_duration_seconds, 0))),
      show_on_dashboard: Boolean(config.show_on_dashboard),
      show_on_chart: Boolean(config.show_on_chart),
      chart_range_hours: Math.max(1, Math.trunc(toNumberOr(config.chart_range_hours, 1))),
      sort_order: Math.trunc(toNumberOr(config.sort_order, 0)),
    };

    if (!isDigitalPin(base.pin)) {
      base.digital_style = 'sensor';
      base.invert_digital_logic = false;
      base.desired_digital_value = null;
    }

    return base;
  };

  const getSelectedController = () =>
    state.controllers.find((controller) => controller.id === state.selectedControllerId) || null;

  const loadControllerSettings = async (controllerId) => {
    if (!controllerId) {
      state.pinConfigs = [];
      return null;
    }
    const data = await fetchJson(`/api/controllers/${controllerId}/settings`);
    state.pinConfigs = Array.isArray(data?.pinConfigs) ? data.pinConfigs : [];
    return data;
  };

  const saveFullControllerSettings = async (controllerId, controllerPatch = {}, pinConfigsOverride = null) => {
    const controller = getSelectedController();
    if (!controller || !controllerId) {
      throw new Error('Controller not selected');
    }

    const pinConfigs = (pinConfigsOverride || state.pinConfigs).map((config) => buildPinConfigForSave(config));

    await fetchJson(`/api/controllers/${controllerId}/settings`, {
      method: 'PUT',
      body: JSON.stringify({
        name: controllerPatch.name ?? controller.name,
        discription: controllerPatch.discription ?? controller.discription,
        send_interval_seconds: controllerPatch.send_interval_seconds ?? controller.send_interval_seconds,
        pinConfigs,
      }),
    });
  };

  const renderControllers = () => {
    elControllers.innerHTML = '';

    if (state.controllers.length === 0) {
      const p = document.createElement('p');
      p.className = 'muted';
      p.textContent = 'Контроллеры не найдены';
      elControllers.appendChild(p);
      return;
    }

    for (const controller of state.controllers) {
      const card = document.createElement('article');
      card.className = 'controller' + (controller.id === state.selectedControllerId ? ' active' : '');

      const selectButton = document.createElement('button');
      selectButton.type = 'button';
      selectButton.className = 'controller-select-btn';
      selectButton.innerHTML = `
        <strong>${controller.name}</strong><br>
        <span class="muted">ID: ${controller.id}</span><br>
        <span class="muted">Интервал: ${controller.send_interval_seconds} сек</span><br>
        <span class="muted">${controller.discription || 'Без описания'}</span>
      `;
      selectButton.addEventListener('click', async () => {
        state.selectedControllerId = controller.id;
        renderControllers();
        try {
          await loadControllerSettings(controller.id);
        } catch (error) {
          setError(`Не удалось загрузить настройки контроллера: ${error.message}`);
        }
        await refreshReadings();
      });

      const actions = document.createElement('div');
      actions.className = 'row controller-actions';

      const settingsBtn = document.createElement('button');
      settingsBtn.type = 'button';
      settingsBtn.className = 'switch';
      settingsBtn.textContent = 'Настроить';
      settingsBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        openControllerSettings(controller.id);
      });

      actions.appendChild(document.createElement('span'));
      actions.appendChild(settingsBtn);

      card.appendChild(selectButton);
      card.appendChild(actions);
      elControllers.appendChild(card);
    }
  };

  const togglePin = async (pin, nextValue) => {
    if (!state.selectedControllerId) return;

    try {
      await fetchJson(`/api/controllers/${state.selectedControllerId}/pins/${encodeURIComponent(pin)}/state`, {
        method: 'PUT',
        body: JSON.stringify({ value: nextValue }),
      });
      await refreshReadings();
    } catch (error) {
      setError(`Не удалось переключить ${pin}: ${error.message}`);
    }
  };

  const clearHistory = async (pin) => {
    if (!state.selectedControllerId || state.historyClearPendingPin === pin) {
      return;
    }

    state.historyClearPendingPin = pin;
    try {
      await fetchJson(`/api/controllers/${state.selectedControllerId}/pins/${encodeURIComponent(pin)}/history`, {
        method: 'DELETE',
      });
      await refreshReadings();
    } catch (error) {
      setError(`Не удалось очистить историю ${pin}: ${error.message}`);
    } finally {
      state.historyClearPendingPin = null;
    }
  };

  const renderCards = (items) => {
    elCards.innerHTML = '';

    const visibleItems = (Array.isArray(items) ? items : []).filter((item) => item.show_on_dashboard !== false);

    if (!visibleItems.length) {
      setMessage('Для выбранного контроллера пока нет данных.');
      return;
    }

    setMessage('');

    for (const item of visibleItems) {
      const card = document.createElement('article');
      card.className = 'card';

      const header = document.createElement('div');
      header.className = 'row';

      const title = document.createElement('h4');
      title.textContent = `${item.label} (${item.pin})`;

      const pinSettingsBtn = document.createElement('button');
      pinSettingsBtn.type = 'button';
      pinSettingsBtn.className = 'switch';
      pinSettingsBtn.textContent = '⚙';
      pinSettingsBtn.title = `Настроить пин ${item.pin}`;
      pinSettingsBtn.addEventListener('click', () => openPinSettings(item.pin));

      header.appendChild(title);
      header.appendChild(pinSettingsBtn);
      card.appendChild(header);

      const value = document.createElement('div');
      value.className = 'value ' + (isDigitalPin(item.pin) ? (Number(item.display_value) > 0 ? 'on' : 'off') : '');
      value.textContent = isDigitalPin(item.pin)
        ? item.display_text
        : `${item.display_value}${item.unit ? ' ' + item.unit : ''}`;
      card.appendChild(value);

      if (isDigitalPin(item.pin) && item.digital_style === 'power') {
        const row = document.createElement('div');
        row.className = 'row';
        const text = document.createElement('span');
        text.className = 'muted';
        text.textContent = 'Ручное управление';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'switch';
        const currentState = Number(item.desired_digital_value ?? item.display_value) > 0 ? 1 : 0;
        btn.textContent = currentState > 0 ? 'Выключить' : 'Включить';
        btn.addEventListener('click', () => togglePin(item.pin, currentState > 0 ? 0 : 1));

        row.appendChild(text);
        row.appendChild(btn);
        card.appendChild(row);
      }

      if (isAnalogPin(item.pin)) {
        const historyRow = document.createElement('div');
        historyRow.className = 'row mt-8';

        const text = document.createElement('span');
        text.className = 'muted';
        text.textContent = 'История';

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'switch';
        clearBtn.textContent = state.historyClearPendingPin === item.pin ? 'Очистка...' : 'Очистить';
        clearBtn.disabled = state.historyClearPendingPin === item.pin;
        clearBtn.addEventListener('click', () => clearHistory(item.pin));

        historyRow.appendChild(text);
        historyRow.appendChild(clearBtn);
        card.appendChild(historyRow);
      }

      elCards.appendChild(card);
    }
  };

  const buildAreaPath = (values, width, height, padding) => {
    const min = Math.min(...values);
    const max = Math.max(...values);
    const valueRange = max - min || 1;
    const innerW = width - padding * 2;
    const innerH = height - padding * 2;

    const points = values.map((value, idx) => {
      const x = padding + (idx / Math.max(1, values.length - 1)) * innerW;
      const y = padding + (1 - (value - min) / valueRange) * innerH;
      return { x, y };
    });

    const line = points.map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' ');
    const area = `${line} L ${points[points.length - 1].x.toFixed(2)} ${(height - padding).toFixed(2)} L ${points[0].x.toFixed(2)} ${(height - padding).toFixed(2)} Z`;

    return { min, max, points, line, area };
  };

  const renderHistoryCharts = (historyRows) => {
    if (!elHistoryCharts || !elHistoryMessage) {
      return;
    }

    elHistoryCharts.innerHTML = '';

    const groups = new Map();
    const nowMs = Date.now();
    for (const row of Array.isArray(historyRows) ? historyRows : []) {
      if (!row || !row.pin) continue;
      const ts = new Date(row.created_at).getTime();
      if (!Number.isFinite(ts)) continue;
      const pinRangeHours = Math.max(1, Math.trunc(toNumberOr(row.chart_range_hours, 24)));
      const effectiveHours = Math.min(state.historyRangeHours, pinRangeHours);
      if (ts < nowMs - effectiveHours * 3600 * 1000) continue;
      if (!groups.has(row.pin)) groups.set(row.pin, []);
      groups.get(row.pin).push(row);
    }

    if (groups.size === 0) {
      elHistoryMessage.classList.remove('hidden');
      elHistoryMessage.textContent = 'Нет данных для построения графиков.';
      return;
    }

    elHistoryMessage.classList.add('hidden');

    for (const [pin, rows] of groups.entries()) {
      const sorted = [...rows].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
      const values = sorted.map((item) => Number(item.display_value));
      if (!values.length) continue;

      const width = 420;
      const height = 160;
      const padding = 20;
      const { min, max, line, area } = buildAreaPath(values, width, height, padding);
      const palette = chartPalette[String(pin)] || { line: '#177b52', fill: 'rgba(23,123,82,0.20)' };

      const firstTs = new Date(sorted[0].created_at);
      const lastTs = new Date(sorted[sorted.length - 1].created_at);

      const card = document.createElement('article');
      card.className = 'chart-card';

      const head = document.createElement('div');
      head.className = 'row';
      const title = document.createElement('strong');
      title.textContent = sorted[0].label || pin;
      const unit = document.createElement('span');
      unit.className = 'muted';
      unit.textContent = sorted[0].unit || '';
      head.appendChild(title);
      head.appendChild(unit);

      const svgWrap = document.createElement('div');
      svgWrap.innerHTML = `
        <svg viewBox="0 0 ${width} ${height}" class="chart-svg" preserveAspectRatio="none" aria-label="${String(pin)} chart">
          <path d="${area}" fill="${palette.fill}"></path>
          <path d="${line}" fill="none" stroke="${palette.line}" stroke-width="2"></path>
          <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" stroke="#d9e1ef" stroke-width="1"></line>
        </svg>
      `;

      const foot = document.createElement('div');
      foot.className = 'row';
      const left = document.createElement('span');
      left.className = 'muted';
      left.textContent = `${Number(min).toFixed(1)}${sorted[0].unit ? ` ${sorted[0].unit}` : ''} - ${Number(max).toFixed(1)}${sorted[0].unit ? ` ${sorted[0].unit}` : ''}`;
      const right = document.createElement('span');
      right.className = 'muted';
      right.textContent = `${firstTs.toLocaleTimeString('ru-RU')} - ${lastTs.toLocaleTimeString('ru-RU')}`;
      foot.appendChild(left);
      foot.appendChild(right);

      card.appendChild(head);
      card.appendChild(svgWrap);
      card.appendChild(foot);
      elHistoryCharts.appendChild(card);
    }
  };

  const refreshReadings = async () => {
    if (!state.selectedControllerId) {
      setMessage('Выберите контроллер слева.');
      elCards.innerHTML = '';
      if (elHistoryCharts) elHistoryCharts.innerHTML = '';
      if (elHistoryMessage) {
        elHistoryMessage.classList.remove('hidden');
        elHistoryMessage.textContent = 'Выберите контроллер слева, чтобы увидеть графики.';
      }
      return;
    }

    try {
      const data = await fetchJson(`/api/controllers/${state.selectedControllerId}/readings`);
      setError('');
      elCurrentController.textContent = data?.controller?.name || `Контроллер ${state.selectedControllerId}`;
      renderCards(Array.isArray(data?.latest) ? data.latest : []);
      renderHistoryCharts(Array.isArray(data?.history) ? data.history : []);
      elRefreshInfo.textContent = `Обновлено: ${new Date().toLocaleTimeString('ru-RU')}`;
    } catch (error) {
      setError(`Ошибка загрузки данных: ${error.message}`);
    }
  };

  const refreshControllers = async () => {
    try {
      const data = await fetchJson('/api/controllers');
      state.controllers = Array.isArray(data?.controllers) ? data.controllers : [];

      const hasSelected = state.controllers.some((c) => c.id === state.selectedControllerId);
      if (!hasSelected) {
        state.selectedControllerId = state.controllers.length ? state.controllers[0].id : null;
      }

      renderControllers();

      if (state.selectedControllerId) {
        await loadControllerSettings(state.selectedControllerId);
      }

      await refreshReadings();
    } catch (error) {
      setError(`Ошибка загрузки контроллеров: ${error.message}`);
    }
  };

  const closeSettingsDialog = () => {
    if (!elSettingsDialog?.open) return;
    elSettingsDialog.close();
    state.editingControllerId = null;
    state.settingsSavePending = false;
    if (elSettingsSaveBtn) {
      elSettingsSaveBtn.disabled = false;
      elSettingsSaveBtn.textContent = 'Сохранить';
    }
    setSettingsError('');
  };

  const openControllerSettings = async (controllerId) => {
    try {
      const data = await loadControllerSettings(controllerId);
      const controller = data?.controller;
      if (!controller) {
        throw new Error('Controller not found');
      }

      state.editingControllerId = controllerId;
      elSettingsTitle.textContent = `Настройки: ${controller.name} (ID ${controller.id})`;
      elSettingsForm.name.value = controller.name || '';
      elSettingsForm.discription.value = controller.discription || '';
      elSettingsForm.send_interval_seconds.value = String(controller.send_interval_seconds || 5);
      setSettingsError('');
      elSettingsDialog.showModal();
    } catch (error) {
      setError(`Не удалось открыть настройки контроллера: ${error.message}`);
    }
  };

  const saveControllerSettings = async () => {
    if (!state.editingControllerId) {
      return;
    }

    state.settingsSavePending = true;
    if (elSettingsSaveBtn) {
      elSettingsSaveBtn.disabled = true;
      elSettingsSaveBtn.textContent = 'Сохранение...';
    }

    try {
      await saveFullControllerSettings(state.editingControllerId, {
        name: String(elSettingsForm.name.value || '').trim(),
        discription: String(elSettingsForm.discription.value || '').trim() || null,
        send_interval_seconds: Math.max(1, Math.trunc(toNumberOr(elSettingsForm.send_interval_seconds.value, 5))),
      });

      closeSettingsDialog();
      await refreshControllers();
    } catch (error) {
      setSettingsError(`Ошибка сохранения: ${error.message}`);
    } finally {
      state.settingsSavePending = false;
      if (elSettingsSaveBtn) {
        elSettingsSaveBtn.disabled = false;
        elSettingsSaveBtn.textContent = 'Сохранить';
      }
    }
  };

  const closePinSettingsDialog = () => {
    if (!elPinSettingsDialog?.open) return;
    elPinSettingsDialog.close();
    state.editingPin = null;
    state.pinSettingsSavePending = false;
    if (elPinSettingsSaveBtn) {
      elPinSettingsSaveBtn.disabled = false;
      elPinSettingsSaveBtn.textContent = 'Сохранить';
    }
    setPinSettingsError('');
  };

  const togglePinFormFields = (pin) => {
    const digital = isDigitalPin(pin);
    const analog = isAnalogPin(pin);

    const namesForDigitalOnly = ['digital_style', 'invert_digital_logic', 'power_on_duration_seconds', 'digital_off_text', 'digital_on_text'];
    const namesForAnalogOnly = ['unit', 'multiplier', 'offset', 'precision', 'average_interval_minutes', 'chart_range_hours', 'show_on_chart'];

    for (const name of namesForDigitalOnly) {
      const field = elPinSettingsForm.elements[name];
      if (field && field.closest('label')) {
        field.closest('label').classList.toggle('hidden', !digital);
      }
    }

    for (const name of namesForAnalogOnly) {
      const field = elPinSettingsForm.elements[name];
      if (field && field.closest('label')) {
        field.closest('label').classList.toggle('hidden', !analog);
      }
    }
  };

  const openPinSettings = async (pin) => {
    if (!state.selectedControllerId) {
      return;
    }

    try {
      if (!state.pinConfigs.length) {
        await loadControllerSettings(state.selectedControllerId);
      }

      const config = state.pinConfigs.find((item) => String(item.pin).toUpperCase() === String(pin).toUpperCase());
      if (!config) {
        throw new Error(`Конфигурация пина ${pin} не найдена`);
      }

      state.editingPin = String(config.pin).toUpperCase();

      elPinSettingsTitle.textContent = `Настройки пина ${config.pin}`;
      elPinSettingsForm.label.value = config.label || config.pin;
      elPinSettingsForm.sort_order.value = String(config.sort_order ?? 0);
      elPinSettingsForm.digital_style.value = config.digital_style || 'power';
      elPinSettingsForm.unit.value = config.unit || '';
      elPinSettingsForm.multiplier.value = String(config.multiplier ?? 1);
      elPinSettingsForm.offset.value = String(config.offset ?? 0);
      elPinSettingsForm.precision.value = String(config.precision ?? 0);
      elPinSettingsForm.average_interval_minutes.value = String(config.average_interval_minutes ?? 5);
      elPinSettingsForm.chart_range_hours.value = String(config.chart_range_hours ?? 24);
      elPinSettingsForm.power_on_duration_seconds.value =
        config.power_on_duration_seconds === null || config.power_on_duration_seconds === undefined
          ? ''
          : String(config.power_on_duration_seconds);
      elPinSettingsForm.digital_off_text.value = (config.value_labels && config.value_labels['0']) || 'Выключен';
      elPinSettingsForm.digital_on_text.value = (config.value_labels && config.value_labels['1']) || 'Включен';
      elPinSettingsForm.invert_digital_logic.checked = Boolean(config.invert_digital_logic);
      elPinSettingsForm.show_on_dashboard.checked = Boolean(config.show_on_dashboard);
      elPinSettingsForm.show_on_chart.checked = Boolean(config.show_on_chart);

      togglePinFormFields(config.pin);
      setPinSettingsError('');
      elPinSettingsDialog.showModal();
    } catch (error) {
      setError(`Не удалось открыть настройки пина: ${error.message}`);
    }
  };

  const savePinSettings = async () => {
    if (!state.selectedControllerId || !state.editingPin) {
      return;
    }

    state.pinSettingsSavePending = true;
    if (elPinSettingsSaveBtn) {
      elPinSettingsSaveBtn.disabled = true;
      elPinSettingsSaveBtn.textContent = 'Сохранение...';
    }

    try {
      const pinIndex = state.pinConfigs.findIndex((item) => String(item.pin).toUpperCase() === state.editingPin);
      if (pinIndex < 0) {
        throw new Error(`Пин ${state.editingPin} не найден`);
      }

      const current = state.pinConfigs[pinIndex];
      const next = {
        ...current,
        label: String(elPinSettingsForm.label.value || '').trim() || current.pin,
        sort_order: Math.trunc(toNumberOr(elPinSettingsForm.sort_order.value, current.sort_order || 0)),
        digital_style: String(elPinSettingsForm.digital_style.value || current.digital_style || 'power'),
        unit: String(elPinSettingsForm.unit.value || '').trim() || null,
        multiplier: toNumberOr(elPinSettingsForm.multiplier.value, current.multiplier ?? 1),
        offset: toNumberOr(elPinSettingsForm.offset.value, current.offset ?? 0),
        precision: Math.max(0, Math.trunc(toNumberOr(elPinSettingsForm.precision.value, current.precision ?? 0))),
        average_interval_minutes: Math.max(
          1,
          Math.trunc(toNumberOr(elPinSettingsForm.average_interval_minutes.value, current.average_interval_minutes ?? 5))
        ),
        chart_range_hours: Math.max(1, Math.trunc(toNumberOr(elPinSettingsForm.chart_range_hours.value, current.chart_range_hours ?? 1))),
        power_on_duration_seconds:
          String(elPinSettingsForm.power_on_duration_seconds.value || '').trim() === ''
            ? null
            : Math.max(0, Math.trunc(toNumberOr(elPinSettingsForm.power_on_duration_seconds.value, current.power_on_duration_seconds ?? 0))),
        invert_digital_logic: Boolean(elPinSettingsForm.invert_digital_logic.checked),
        show_on_dashboard: Boolean(elPinSettingsForm.show_on_dashboard.checked),
        show_on_chart: Boolean(elPinSettingsForm.show_on_chart.checked),
        value_labels: {
          '0': String(elPinSettingsForm.digital_off_text.value || 'Выключен').trim() || 'Выключен',
          '1': String(elPinSettingsForm.digital_on_text.value || 'Включен').trim() || 'Включен',
        },
      };

      const nextPinConfigs = [...state.pinConfigs];
      nextPinConfigs[pinIndex] = next;

      await saveFullControllerSettings(state.selectedControllerId, {}, nextPinConfigs);
      state.pinConfigs = nextPinConfigs;

      closePinSettingsDialog();
      await refreshControllers();
    } catch (error) {
      setPinSettingsError(`Ошибка сохранения: ${error.message}`);
    } finally {
      state.pinSettingsSavePending = false;
      if (elPinSettingsSaveBtn) {
        elPinSettingsSaveBtn.disabled = false;
        elPinSettingsSaveBtn.textContent = 'Сохранить';
      }
    }
  };

  if (elSettingsCancelBtn) {
    elSettingsCancelBtn.addEventListener('click', closeSettingsDialog);
  }
  if (elSettingsDialog) {
    elSettingsDialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closeSettingsDialog();
    });
  }
  if (elSettingsForm) {
    elSettingsForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await saveControllerSettings();
    });
  }

  if (elPinSettingsCancelBtn) {
    elPinSettingsCancelBtn.addEventListener('click', closePinSettingsDialog);
  }
  if (elPinSettingsDialog) {
    elPinSettingsDialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closePinSettingsDialog();
    });
  }
  if (elPinSettingsForm) {
    elPinSettingsForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await savePinSettings();
    });
  }

  if (elHistoryRangeControls) {
    elHistoryRangeControls.addEventListener('click', async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const button = target.closest('[data-range-hours]');
      if (!(button instanceof HTMLElement)) return;
      const nextRange = Number(button.getAttribute('data-range-hours') || 0);
      if (!Number.isFinite(nextRange) || nextRange <= 0) return;
      state.historyRangeHours = Math.trunc(nextRange);
      updateHistoryRangeButtons();
      await refreshReadings();
    });
  }

  const start = async () => {
    updateHistoryRangeButtons();
    await refreshControllers();
    if (state.refreshTimer) {
      clearInterval(state.refreshTimer);
    }
    state.refreshTimer = setInterval(() => {
      refreshReadings();
    }, 5000);
  };

  start();
})();
