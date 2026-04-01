(() => {
  const AUTO_REFRESH_MS = 5000;

  const state = {
    pins: [],
    scenarios: [],
    editingScenario: null,
    timer: null,
    expandedTargets: new Set(),
  };

  const elScenesList = document.getElementById('scenesList');
  const elScenesError = document.getElementById('scenesError');
  const elScenesRefreshInfo = document.getElementById('scenesRefreshInfo');
  const elDialog = document.getElementById('scenarioDialog');
  const elForm = document.getElementById('scenarioForm');
  const elDialogTitle = document.getElementById('scenarioDialogTitle');
  const elDeleteBtn = document.getElementById('deleteScenarioBtn');
  const elCancelBtn = document.getElementById('cancelScenarioBtn');
  const elNewBtn = document.getElementById('newScenarioBtn');
  const elSourcePinSelect = document.getElementById('sourcePinSelect');
  const elTargetPinSelect = document.getElementById('targetPinSelect');

  const setError = (message) => {
    elScenesError.textContent = message || '';
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

  const isDigitalPin = (pin) => /^D\d+$/i.test(String(pin || '').trim());

  const pinKey = (controllerId, pin) => `${controllerId}|${String(pin || '').toUpperCase()}`;

  const parsePinKey = (encoded) => {
    const [controllerIdRaw, pinRaw] = String(encoded || '').split('|');
    const controllerId = Number(controllerIdRaw);
    const pin = String(pinRaw || '').toUpperCase();
    if (!controllerId || !pin) return null;
    return { controllerId, pin };
  };

  const getPinOption = (encoded) => {
    const parsed = parsePinKey(encoded);
    if (!parsed) return null;
    return state.pins.find((item) => item.controller_id === parsed.controllerId && String(item.pin).toUpperCase() === parsed.pin) || null;
  };

  const getPinMeta = (controllerId, pin) => {
    const key = pinKey(controllerId, pin);
    return state.pins.find((item) => pinKey(item.controller_id, item.pin) === key) || null;
  };

  const getOperatorLabel = (operator) => {
    const op = String(operator || '').toLowerCase();
    if (op === 'gt') return '>';
    if (op === 'gte') return '>=';
    if (op === 'lt') return '<';
    if (op === 'lte') return '<=';
    return op || '?';
  };

  const getScenarioDecision = (scenario) => {
    const active = Number(scenario?.current_state || 0) > 0;
    if (active) return Number(scenario?.value_when_true || 0) > 0 ? 1 : 0;
    return Number(scenario?.value_when_false || 0) > 0 ? 1 : 0;
  };

  const getTargetGroups = () => {
    const byTarget = new Map();

    for (const pin of state.pins) {
      if (!isDigitalPin(pin.pin) || String(pin.digital_style || '') !== 'power') {
        continue;
      }
      const key = pinKey(pin.controller_id, pin.pin);
      byTarget.set(key, {
        key,
        controller_id: pin.controller_id,
        controller_name: pin.controller_name,
        pin: String(pin.pin).toUpperCase(),
        display_name: pin.display_name || `${pin.controller_name} · ${pin.label} (${pin.pin})`,
        scenarios: [],
      });
    }

    for (const scenario of state.scenarios) {
      const key = pinKey(scenario.controller_id, scenario.target_pin);
      if (!byTarget.has(key)) {
        byTarget.set(key, {
          key,
          controller_id: scenario.controller_id,
          controller_name: scenario.controller_name || `controller-${scenario.controller_id}`,
          pin: String(scenario.target_pin || '').toUpperCase(),
          display_name: `${scenario.controller_name || `controller-${scenario.controller_id}`} · ${String(scenario.target_pin || '').toUpperCase()}`,
          scenarios: [],
        });
      }
      byTarget.get(key).scenarios.push(scenario);
    }

    return [...byTarget.values()]
      .map((group) => {
        group.scenarios.sort((a, b) => (a.priority - b.priority) || (a.id - b.id));
        const firstEnabled = group.scenarios.find((x) => x.enabled);
        group.current_value = firstEnabled ? getScenarioDecision(firstEnabled) : 0;
        return group;
      })
      .sort((a, b) => {
        if (a.controller_id !== b.controller_id) return a.controller_id - b.controller_id;
        return a.pin.localeCompare(b.pin);
      });
  };

  const updateSourcePinOptions = (controllerId, selectedKey = null) => {
    const sourcePins = state.pins
      .filter((pin) => pin.controller_id === controllerId)
      .sort((a, b) => (a.sort_order - b.sort_order) || String(a.pin).localeCompare(String(b.pin)));

    const prev = selectedKey || elSourcePinSelect.value;
    elSourcePinSelect.innerHTML = '';

    for (const pin of sourcePins) {
      const option = document.createElement('option');
      option.value = pinKey(pin.controller_id, pin.pin);
      option.textContent = pin.display_name || `${pin.controller_name} · ${pin.label} (${pin.pin})`;
      elSourcePinSelect.appendChild(option);
    }

    if (prev && [...elSourcePinSelect.options].some((x) => x.value === prev)) {
      elSourcePinSelect.value = prev;
    } else if (elSourcePinSelect.options[0]) {
      elSourcePinSelect.value = elSourcePinSelect.options[0].value;
    }
  };

  const populateTargetPinSelect = (preferredControllerId = null) => {
    const targetPins = state.pins
      .filter((pin) => isDigitalPin(pin.pin) && String(pin.digital_style || '') === 'power')
      .filter((pin) => !preferredControllerId || pin.controller_id === preferredControllerId)
      .sort((a, b) => {
        if (a.controller_id !== b.controller_id) return a.controller_id - b.controller_id;
        return (a.sort_order - b.sort_order) || String(a.pin).localeCompare(String(b.pin));
      });

    elTargetPinSelect.innerHTML = '';
    for (const pin of targetPins) {
      const option = document.createElement('option');
      option.value = pinKey(pin.controller_id, pin.pin);
      option.textContent = pin.display_name || `${pin.controller_name} · ${pin.label} (${pin.pin})`;
      elTargetPinSelect.appendChild(option);
    }

    if (!elTargetPinSelect.value && elTargetPinSelect.options[0]) {
      elTargetPinSelect.value = elTargetPinSelect.options[0].value;
    }
  };

  const renderScenarioList = () => {
    elScenesList.innerHTML = '';

    const groups = getTargetGroups();
    if (!groups.length) {
      const p = document.createElement('p');
      p.className = 'muted';
      p.textContent = 'Целевые пины не найдены';
      elScenesList.appendChild(p);
      return;
    }

    for (const group of groups) {
      const row = document.createElement('div');
      row.className = 'controller scenes-group-row';

      const header = document.createElement('div');
      header.className = 'row scenes-group-header';

      const stateIcon = group.current_value > 0 ? '●' : '○';
      const stateLabel = group.current_value > 0 ? 'Включен' : 'Выключен';
      const countLabel = `${group.scenarios.length} сценар.`;

      const left = document.createElement('div');
      left.innerHTML = `<strong>${group.display_name}</strong><br><span class="muted">Состояние: ${stateIcon} ${stateLabel}</span>`;

      const right = document.createElement('span');
      right.className = 'muted';
      right.textContent = countLabel;

      header.appendChild(left);
      header.appendChild(right);

      const body = document.createElement('div');
      body.className = 'scenes-group-body';
      body.classList.toggle('hidden', !state.expandedTargets.has(group.key));

      if (!group.scenarios.length) {
        const empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = 'Сценарии для этого пина отсутствуют';
        body.appendChild(empty);
      } else {
        for (const scenario of group.scenarios) {
          const scenarioRow = document.createElement('div');
          scenarioRow.className = 'scenario-row';

          const sourceMeta = getPinMeta(scenario.controller_id, scenario.source_pin);
          const sourceName = sourceMeta?.display_name || `${scenario.controller_name} · ${scenario.source_pin}`;
          const scenarioState = Number(scenario.current_state || 0) > 0 ? 'TRUE' : 'FALSE';

          scenarioRow.innerHTML = `
            <div class="row">
              <strong>${scenario.name}</strong>
              <button type="button" class="switch" data-edit-id="${scenario.id}">Редактировать</button>
            </div>
            <div class="muted mt-4">
              Источник: ${sourceName} | Условие: ${getOperatorLabel(scenario.operator)} ${scenario.threshold}
            </div>
            <div class="muted mt-2">
              Состояние: ${scenarioState} | TRUE=${scenario.value_when_true}, FALSE=${scenario.value_when_false} | Приоритет: ${scenario.priority} | ${scenario.enabled ? 'Включен' : 'Отключен'}
            </div>
          `;

          const editBtn = scenarioRow.querySelector('[data-edit-id]');
          if (editBtn) {
            editBtn.addEventListener('click', (event) => {
              event.stopPropagation();
              openScenarioDialog(scenario);
            });
          }

          body.appendChild(scenarioRow);
        }
      }

      header.addEventListener('click', () => {
        if (state.expandedTargets.has(group.key)) {
          state.expandedTargets.delete(group.key);
        } else {
          state.expandedTargets.add(group.key);
        }
        renderScenarioList();
      });

      row.appendChild(header);
      row.appendChild(body);
      elScenesList.appendChild(row);
    }
  };

  const openScenarioDialog = (scenario = null) => {
    state.editingScenario = scenario;
    elDialogTitle.textContent = scenario ? `Сценарий #${scenario.id}` : 'Новый сценарий';
    elDeleteBtn.classList.toggle('hidden', !scenario);

    const preferredControllerId = scenario ? Number(scenario.controller_id) : null;
    populateTargetPinSelect(preferredControllerId);

    elForm.name.value = scenario?.name || '';
    elForm.priority.value = String(scenario?.priority ?? 100);
    elForm.operator.value = scenario?.operator || 'gt';
    elForm.threshold.value = String(scenario?.threshold ?? 0);
    elForm.hysteresis.value = String(scenario?.hysteresis ?? 0);
    elForm.enabled.checked = Boolean(scenario?.enabled ?? true);
    elForm.value_when_true.value = String(scenario?.value_when_true ?? 1);
    elForm.value_when_false.value = String(scenario?.value_when_false ?? 0);

    if (scenario) {
      const targetValue = pinKey(scenario.controller_id, scenario.target_pin);
      if ([...elTargetPinSelect.options].some((o) => o.value === targetValue)) {
        elTargetPinSelect.value = targetValue;
      }
    }

    const selectedTarget = getPinOption(elForm.target_pin.value || elTargetPinSelect.value);
    updateSourcePinOptions(selectedTarget?.controller_id || preferredControllerId || null, scenario ? pinKey(scenario.controller_id, scenario.source_pin) : null);

    if (!elForm.target_pin.value && elTargetPinSelect.options[0]) {
      elForm.target_pin.value = elTargetPinSelect.options[0].value;
    }

    elDialog.showModal();
  };

  const closeDialog = () => {
    if (elDialog.open) {
      elDialog.close();
    }
  };

  const loadPins = async () => {
    const data = await fetchJson('/api/pins');
    state.pins = Array.isArray(data?.pins) ? data.pins : [];
  };

  const loadScenarios = async () => {
    const data = await fetchJson('/api/scenarios');
    state.scenarios = Array.isArray(data?.scenarios) ? data.scenarios : [];
    renderScenarioList();
    elScenesRefreshInfo.textContent = `Обновлено: ${new Date().toLocaleTimeString('ru-RU')}`;
  };

  const reloadAll = async () => {
    try {
      setError('');
      await loadPins();
      await loadScenarios();
    } catch (error) {
      setError(`Ошибка загрузки: ${error.message}`);
    }
  };

  elNewBtn.addEventListener('click', () => openScenarioDialog(null));
  elCancelBtn.addEventListener('click', closeDialog);

  elTargetPinSelect.addEventListener('change', () => {
    const target = getPinOption(elTargetPinSelect.value);
    updateSourcePinOptions(target?.controller_id || null, null);
  });

  elDeleteBtn.addEventListener('click', async () => {
    if (!state.editingScenario) {
      return;
    }

    try {
      await fetchJson(`/api/controllers/${state.editingScenario.controller_id}/scenarios/${state.editingScenario.id}`, {
        method: 'DELETE',
      });
      closeDialog();
      await loadScenarios();
    } catch (error) {
      setError(`Ошибка удаления: ${error.message}`);
    }
  });

  elForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const source = getPinOption(elForm.source_pin.value);
    const target = getPinOption(elForm.target_pin.value);
    if (!source || !target) {
      setError('Не удалось определить выбранные пины');
      return;
    }

    if (source.controller_id !== target.controller_id) {
      setError('Источник должен принадлежать тому же контроллеру, что и целевой пин');
      return;
    }

    const controllerId = Number(target.controller_id);
    const payload = {
      name: String(elForm.name.value || '').trim(),
      source_pin: String(source.pin || '').trim(),
      operator: String(elForm.operator.value || 'gt'),
      threshold: Number(elForm.threshold.value || 0),
      hysteresis: Number(elForm.hysteresis.value || 0),
      target_pin: String(target.pin || '').trim(),
      value_when_true: Number(elForm.value_when_true.value || 0),
      value_when_false: Number(elForm.value_when_false.value || 0),
      priority: Number(elForm.priority.value || 100),
      enabled: Boolean(elForm.enabled.checked),
    };

    try {
      if (state.editingScenario?.id) {
        await fetchJson(`/api/controllers/${state.editingScenario.controller_id}/scenarios/${state.editingScenario.id}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        });
      } else {
        await fetchJson(`/api/controllers/${controllerId}/scenarios`, {
          method: 'POST',
          body: JSON.stringify(payload),
        });
      }

      closeDialog();
      await loadScenarios();
    } catch (error) {
      setError(`Ошибка сохранения: ${error.message}`);
    }
  });

  const start = async () => {
    await reloadAll();
    if (state.timer) {
      clearInterval(state.timer);
    }
    state.timer = setInterval(() => {
      loadScenarios();
    }, AUTO_REFRESH_MS);
  };

  start();
})();
