<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Сценарии') }}</h2>
    </x-slot>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h3 class="h6 mb-1">Список сценариев</h3>
                <div class="text-muted small">Условия внутри сценария: И. Сценарии одного пина: ИЛИ.</div>
            </div>
            <div id="scenesMeta" class="text-muted small">...</div>
        </div>
    </div>

    <div id="scenesError" class="alert alert-danger d-none mb-3"></div>
    <div id="scenesList" class="d-grid gap-3"></div>

    <dialog id="scenarioDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="scenarioForm" class="modal-form">
            <h3 id="scenarioDialogTitle" class="h6 mb-0">Сценарий</h3>
            <input type="hidden" name="pin_id">
            <input type="hidden" name="definition_id">
            <label>
                Название сценария<br>
                <input name="name" required class="form-control">
            </label>
            <div class="modal-actions">
                <button type="button" id="scenarioCancelBtn" class="btn btn-outline-secondary btn-sm">Закрыть</button>
                <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
            </div>
        </form>
    </dialog>

    <dialog id="conditionDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="conditionForm" class="modal-form">
            <h3 id="conditionDialogTitle" class="h6 mb-0">Условие</h3>
            <input type="hidden" name="scenario_id">
            <input type="hidden" name="condition_id">
            <label>
                Источник (пин)<br>
                <select name="pin_id" class="form-select" required></select>
            </label>
            <label id="conditionOperatorField">
                Условие<br>
                <select name="operator" class="form-select">
                    <option value="gt">&gt;</option>
                    <option value="gte">&gt;=</option>
                    <option value="lt">&lt;</option>
                    <option value="lte">&lt;=</option>
                    <option value="eq">=</option>
                    <option value="ne">!=</option>
                </select>
            </label>
            <label id="conditionThresholdLabel">
                Порог<br>
                <input name="threshold" type="number" step="0.01" class="form-control" required>
            </label>
            <div class="modal-actions">
                <button type="button" id="conditionCancelBtn" class="btn btn-outline-secondary btn-sm">Закрыть</button>
                <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const AUTO_REFRESH_MS = 5000;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const elMeta = document.getElementById('scenesMeta');
            const elError = document.getElementById('scenesError');
            const elList = document.getElementById('scenesList');
            const expandedTargets = new Set();
            const expandedScenarios = new Set();

            const scenarioDialog = document.getElementById('scenarioDialog');
            const scenarioForm = document.getElementById('scenarioForm');
            const scenarioDialogTitle = document.getElementById('scenarioDialogTitle');
            const scenarioCancelBtn = document.getElementById('scenarioCancelBtn');

            const conditionDialog = document.getElementById('conditionDialog');
            const conditionForm = document.getElementById('conditionForm');
            const conditionDialogTitle = document.getElementById('conditionDialogTitle');
            const conditionCancelBtn = document.getElementById('conditionCancelBtn');
            const conditionOperatorField = document.getElementById('conditionOperatorField');
            const conditionThresholdLabel = document.getElementById('conditionThresholdLabel');

            let latestData = { targets: [], pins: [], scenario_definitions: [], conditions: [] };

            const setError = (message) => {
                elError.textContent = message || '';
                elError.classList.toggle('d-none', !message);
            };

            const fetchJson = async (url, options = {}) => {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        ...(options.method && options.method !== 'GET' ? {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf} : {}),
                        ...(options.headers || {}),
                    },
                    ...options
                });
                const text = await response.text();
                const data = text ? JSON.parse(text) : {};
                if (!response.ok) throw new Error(data?.message || data?.error || `HTTP ${response.status}`);
                return data;
            };

            const opLabel = (op) => ({gt: '>', gte: '>=', lt: '<', lte: '<=', eq: '=', ne: '!='}[String(op || '').toLowerCase()] || '?');
            const pinKey = (controllerId, pin) => `${controllerId}|${String(pin || '').toUpperCase()}`;
            const isDigitalPinName = (pinName) => /^D\d+$/i.test(String(pinName || '').trim()) || /^RELAY_\d+$/i.test(String(pinName || '').trim());
            const isCurrentTimePin = (pinName) => String(pinName || '').toUpperCase() === 'CURRENT_TIME';

            const findPin = (pinId) => latestData.pins.find((p) => String(p.id) === String(pinId));
            const findScenarioDefinition = (definitionId) => latestData.scenario_definitions.find((d) => String(d.id) === String(definitionId));

            const fillConditionPins = (_controllerId, selectedPinId = null) => {
                const select = conditionForm.pin_id;
                const pins = latestData.pins
                    .sort((a, b) => {
                        const aName = `${String(a.controller_name || '')} ${String(a.pin || '')}`.trim();
                        const bName = `${String(b.controller_name || '')} ${String(b.pin || '')}`.trim();
                        return aName.localeCompare(bName, 'ru');
                    });
                select.innerHTML = '';
                pins.forEach((pin) => {
                    const option = document.createElement('option');
                    option.value = pin.id;
                    option.textContent = `${pin.controller_name} · ${pin.label} (${pin.pin})`;
                    select.appendChild(option);
                });
                if (selectedPinId && pins.some((p) => String(p.id) === String(selectedPinId))) {
                    select.value = selectedPinId;
                } else if (pins[0]) {
                    select.value = String(pins[0].id);
                }
            };

            const updateConditionInputMode = () => {
                const sourcePin = findPin(conditionForm.pin_id.value);
                if (!sourcePin) return;
                const thresholdInput = conditionForm.threshold;
                const prevOperator = String(conditionForm.operator.value || '').toLowerCase();

                if (isDigitalPinName(sourcePin.pin)) {
                    conditionForm.operator.value = 'eq';
                    conditionOperatorField.classList.add('d-none');
                    const checked = thresholdInput.type === 'checkbox'
                        ? Boolean(thresholdInput.checked)
                        : Number(thresholdInput.value || 0) > 0;
                    thresholdInput.type = 'checkbox';
                    thresholdInput.checked = checked;
                    thresholdInput.value = checked ? '1' : '0';
                    thresholdInput.classList.remove('form-control');
                    thresholdInput.classList.add('form-check-input');
                    conditionThresholdLabel.classList.add('form-check', 'form-switch', 'd-flex', 'align-items-center', 'gap-2');
                    conditionThresholdLabel.innerHTML = '';
                    conditionThresholdLabel.appendChild(thresholdInput);
                    const text = document.createElement('span');
                    text.textContent = 'Включен (TRUE)';
                    conditionThresholdLabel.appendChild(text);
                    return;
                }

                conditionOperatorField.classList.remove('d-none');
                if (thresholdInput.type === 'checkbox') {
                    const checked = thresholdInput.checked;
                    conditionThresholdLabel.classList.remove('form-check', 'form-switch', 'd-flex', 'align-items-center', 'gap-2');
                    conditionThresholdLabel.innerHTML = 'Порог<br>';
                    thresholdInput.type = 'number';
                    thresholdInput.step = '0.01';
                    thresholdInput.value = checked ? '1' : '0';
                    thresholdInput.classList.add('form-control');
                    thresholdInput.classList.remove('form-check-input');
                    conditionThresholdLabel.appendChild(thresholdInput);
                }

                if (isCurrentTimePin(sourcePin.pin)) {
                    conditionForm.operator.innerHTML = '<option value="gt">&gt;</option><option value="lt">&lt;</option>';
                    conditionForm.operator.value = ['gt', 'lt'].includes(prevOperator) ? prevOperator : 'gt';
                    thresholdInput.type = 'time';
                    thresholdInput.step = '1';
                } else {
                    conditionForm.operator.innerHTML = '<option value="gt">&gt;</option><option value="gte">&gt;=</option><option value="lt">&lt;</option><option value="lte">&lt;=</option><option value="eq">=</option><option value="ne">!=</option>';
                    conditionForm.operator.value = ['gt', 'gte', 'lt', 'lte', 'eq', 'ne'].includes(prevOperator) ? prevOperator : 'gt';
                    thresholdInput.type = 'number';
                    thresholdInput.step = '0.01';
                }
            };

            const parseTimeToSeconds = (value) => {
                const parts = String(value || '').split(':').map((n) => Number(n));
                if (parts.length < 2 || parts.length > 3 || parts.some((x) => !Number.isFinite(x) || x < 0)) return null;
                const [hh, mm, ss = 0] = parts;
                if (hh > 23 || mm > 59 || ss > 59) return null;
                return hh * 3600 + mm * 60 + ss;
            };

            const formatSecondsAsTime = (seconds) => {
                const s = Math.max(0, Math.min(86399, Math.trunc(Number(seconds) || 0)));
                const h = String(Math.trunc(s / 3600)).padStart(2, '0');
                const m = String(Math.trunc((s % 3600) / 60)).padStart(2, '0');
                const sec = String(s % 60).padStart(2, '0');
                return `${h}:${m}:${sec}`;
            };

            const render = () => {
                elList.innerHTML = '';
                const targets = latestData.targets || [];
                const definitions = latestData.scenario_definitions || [];
                const conditions = latestData.conditions || [];

                if (!targets.length) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted';
                    empty.textContent = 'Целевые пины типа "power" не найдены.';
                    elList.appendChild(empty);
                    return;
                }

                const defsByPin = new Map();
                const condByScenario = new Map();
                definitions.forEach((d) => {
                    const key = pinKey(d.controller_id, d.target_pin);
                    if (!defsByPin.has(key)) defsByPin.set(key, []);
                    defsByPin.get(key).push(d);
                });
                conditions.forEach((c) => {
                    const key = String(c.scenario_id || '');
                    if (!condByScenario.has(key)) condByScenario.set(key, []);
                    condByScenario.get(key).push(c);
                });

                targets.forEach((target) => {
                    const targetKey = pinKey(target.controller_id, target.pin);
                    const defs = defsByPin.get(targetKey) || [];
                    const scenarioEnabled = Number(target.enable_scenario || 0) > 0;
                    const scenarioResults = defs.map((def) => {
                        const cond = condByScenario.get(String(def.id)) || [];
                        const scenarioTrue = cond.length > 0 && cond.every((x) => Number(x.current_state || 0) > 0);
                        return { def, cond, scenarioTrue };
                    });
                    const pinResult = scenarioEnabled && scenarioResults.some((x) => x.scenarioTrue);

                    const wrap = document.createElement('section');
                    wrap.className = 'card shadow-sm';
                    const body = document.createElement('div');
                    body.className = 'card-body';

                    const header = document.createElement('div');
                    header.className = 'd-flex justify-content-between align-items-center gap-3 flex-wrap';
                    header.style.cursor = 'pointer';
                    header.innerHTML = `
                        <div>
                            <div class="fw-semibold">${target.controller_name} · ${target.label} (${target.pin})</div>
                            <div class="small text-muted">Сценариев: ${defs.length}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-primary btn-sm js-add-scenario">Добавить сценарий</button>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input js-toggle-enabled" type="checkbox" ${scenarioEnabled ? 'checked' : ''}>
                            </div>
                            <span class="badge ${scenarioEnabled ? 'text-bg-success' : 'text-bg-secondary'}">${scenarioEnabled ? 'Сценарий ВКЛ' : 'Сценарий ВЫКЛ'}</span>
                            <span class="badge ${pinResult ? 'text-bg-warning' : 'text-bg-dark'}">${pinResult ? 'Результат TRUE' : 'Результат FALSE'}</span>
                        </div>
                    `;
                    body.appendChild(header);

                    const addScenarioBtn = header.querySelector('.js-add-scenario');
                    addScenarioBtn.addEventListener('click', (event) => {
                        event.stopPropagation();
                        scenarioDialogTitle.textContent = 'Создать сценарий';
                        scenarioForm.pin_id.value = String(target.id);
                        scenarioForm.definition_id.value = '';
                        scenarioForm.name.value = `Сценарий ${defs.length + 1}`;
                        scenarioDialog.showModal();
                    });

                    const enabledToggle = header.querySelector('.js-toggle-enabled');
                    enabledToggle.addEventListener('click', (event) => event.stopPropagation());
                    enabledToggle.addEventListener('change', async (event) => {
                        event.stopPropagation();
                        try {
                            enabledToggle.disabled = true;
                            await fetchJson(`/api/scenes/targets/${encodeURIComponent(target.id)}/enabled`, {
                                method: 'PUT',
                                body: JSON.stringify({enabled: Boolean(enabledToggle.checked)}),
                            });
                            await load();
                        } catch (error) {
                            setError(`Ошибка переключения сценария: ${error.message}`);
                            enabledToggle.checked = !enabledToggle.checked;
                        } finally {
                            enabledToggle.disabled = false;
                        }
                    });

                    const scenariosBox = document.createElement('div');
                    scenariosBox.className = 'mt-3 d-grid gap-2';
                    scenariosBox.classList.toggle('d-none', !expandedTargets.has(targetKey));

                    if (!defs.length) {
                        const p = document.createElement('div');
                        p.className = 'text-muted small';
                        p.textContent = 'Сценарии для этого пина не заданы.';
                        scenariosBox.appendChild(p);
                    }

                    scenarioResults.forEach((item) => {
                        const scenKey = `${targetKey}|${item.def.id}`;
                        const scenarioCard = document.createElement('div');
                        scenarioCard.className = 'border rounded p-2';

                        const scenarioHead = document.createElement('div');
                        scenarioHead.className = 'd-flex justify-content-between align-items-center gap-2 flex-wrap';
                        scenarioHead.style.cursor = 'pointer';
                        scenarioHead.innerHTML = `
                            <div class="fw-semibold">${item.def.name}</div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-edit-scenario">Ред.</button>
                                <button type="button" class="btn btn-outline-danger btn-sm js-del-scenario">Удалить</button>
                                <button type="button" class="btn btn-outline-primary btn-sm js-add-condition">+ Условие</button>
                                <span class="badge ${item.scenarioTrue ? 'text-bg-success' : 'text-bg-secondary'}">${item.scenarioTrue ? 'TRUE' : 'FALSE'}</span>
                            </div>
                        `;
                        scenarioCard.appendChild(scenarioHead);

                        scenarioHead.querySelector('.js-edit-scenario').addEventListener('click', (event) => {
                            event.stopPropagation();
                            scenarioDialogTitle.textContent = 'Редактировать сценарий';
                            scenarioForm.pin_id.value = String(target.id);
                            scenarioForm.definition_id.value = String(item.def.id);
                            scenarioForm.name.value = String(item.def.name || '');
                            scenarioDialog.showModal();
                        });
                        scenarioHead.querySelector('.js-del-scenario').addEventListener('click', async (event) => {
                            event.stopPropagation();
                            if (!window.confirm(`Удалить сценарий "${item.def.name}" и все его условия?`)) return;
                            try {
                                await fetchJson(`/api/scenes/scenario-definitions/${encodeURIComponent(item.def.id)}`, {method: 'DELETE'});
                                await load();
                            } catch (error) {
                                setError(`Ошибка удаления сценария: ${error.message}`);
                            }
                        });
                        scenarioHead.querySelector('.js-add-condition').addEventListener('click', (event) => {
                            event.stopPropagation();
                            conditionDialogTitle.textContent = 'Добавить условие';
                            conditionForm.scenario_id.value = String(item.def.id);
                            conditionForm.condition_id.value = '';
                            fillConditionPins(target.controller_id);
                            conditionForm.operator.value = 'gt';
                            conditionForm.threshold.value = '0';
                            updateConditionInputMode();
                            conditionDialog.showModal();
                        });

                        const condBox = document.createElement('div');
                        condBox.className = 'mt-2 d-grid gap-2';
                        condBox.classList.toggle('d-none', !expandedScenarios.has(scenKey));

                        if (!item.cond.length) {
                            const p = document.createElement('div');
                            p.className = 'text-muted small';
                            p.textContent = 'Условия не заданы (сценарий возвращает FALSE).';
                            condBox.appendChild(p);
                        } else {
                            item.cond.forEach((c) => {
                                const row = document.createElement('div');
                                row.className = 'border rounded p-2 scenario-condition-row';
                                row.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                        <div class="small"><strong>${c.source_pin_label || c.source_pin}</strong> ${opLabel(c.operator)} ${c.source_pin === 'CURRENT_TIME' ? formatSecondsAsTime(c.threshold) : c.threshold}</div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm js-edit-condition">Ред.</button>
                                            <button type="button" class="btn btn-outline-danger btn-sm js-del-condition">Удалить</button>
                                            <span class="small scenario-condition-state">${Number(c.current_state || 0) > 0 ? 'TRUE' : 'FALSE'}</span>
                                        </div>
                                    </div>
                                `;
                                row.querySelector('.js-edit-condition').addEventListener('click', () => {
                                    conditionDialogTitle.textContent = 'Редактировать условие';
                                    conditionForm.scenario_id.value = String(c.scenario_id);
                                    conditionForm.condition_id.value = String(c.id);
                                    fillConditionPins(target.controller_id, c.source_pin_id);
                                    conditionForm.operator.value = String(c.operator || 'gt');
                                    if (isCurrentTimePin(c.source_pin)) conditionForm.threshold.value = formatSecondsAsTime(c.threshold);
                                    else conditionForm.threshold.value = String(c.threshold ?? 0);
                                    updateConditionInputMode();
                                    if (isDigitalPinName(c.source_pin)) conditionForm.threshold.checked = Number(c.threshold || 0) > 0;
                                    conditionDialog.showModal();
                                });
                                row.querySelector('.js-del-condition').addEventListener('click', async () => {
                                    if (!window.confirm('Удалить условие?')) return;
                                    try {
                                        await fetchJson(`/api/scenes/conditions/${encodeURIComponent(c.id)}`, {method: 'DELETE'});
                                        await load();
                                    } catch (error) {
                                        setError(`Ошибка удаления условия: ${error.message}`);
                                    }
                                });
                                condBox.appendChild(row);
                            });
                        }

                        scenarioHead.addEventListener('click', () => {
                            if (expandedScenarios.has(scenKey)) expandedScenarios.delete(scenKey);
                            else expandedScenarios.add(scenKey);
                            condBox.classList.toggle('d-none', !expandedScenarios.has(scenKey));
                        });

                        scenarioCard.appendChild(condBox);
                        scenariosBox.appendChild(scenarioCard);
                    });

                    header.addEventListener('click', () => {
                        if (expandedTargets.has(targetKey)) expandedTargets.delete(targetKey);
                        else expandedTargets.add(targetKey);
                        scenariosBox.classList.toggle('d-none', !expandedTargets.has(targetKey));
                    });

                    body.appendChild(scenariosBox);
                    wrap.appendChild(body);
                    elList.appendChild(wrap);
                });
            };

            const load = async () => {
                try {
                    setError('');
                    latestData = await fetchJson('/api/scenes/data');
                    const ts = new Date(latestData.server_time || Date.now()).toLocaleTimeString('ru-RU');
                    elMeta.textContent = `Часовой пояс: ${latestData.time_zone || '-'} · Обновлено: ${ts}`;
                    render();
                } catch (error) {
                    setError(`Ошибка загрузки сценариев: ${error.message}`);
                }
            };

            scenarioCancelBtn.addEventListener('click', () => scenarioDialog.close());
            conditionCancelBtn.addEventListener('click', () => conditionDialog.close());
            conditionForm.pin_id.addEventListener('change', updateConditionInputMode);

            scenarioForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const payload = {
                        pin_id: String(scenarioForm.pin_id.value || ''),
                        name: String(scenarioForm.name.value || '').trim(),
                    };
                    if (!payload.pin_id || !payload.name) {
                        setError('Заполните название сценария');
                        return;
                    }
                    const definitionId = String(scenarioForm.definition_id.value || '');
                    if (definitionId) {
                        await fetchJson(`/api/scenes/scenario-definitions/${encodeURIComponent(definitionId)}`, {
                            method: 'PUT',
                            body: JSON.stringify({name: payload.name}),
                        });
                    } else {
                        await fetchJson('/api/scenes/scenario-definitions', {
                            method: 'POST',
                            body: JSON.stringify(payload),
                        });
                    }
                    scenarioDialog.close();
                    await load();
                } catch (error) {
                    setError(`Ошибка сохранения сценария: ${error.message}`);
                }
            });

            conditionForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const pinId = String(conditionForm.pin_id.value || '');
                    const sourcePin = findPin(pinId);
                    if (!pinId || !sourcePin) {
                        setError('Выберите источник');
                        return;
                    }
                    let threshold = 0;
                    let operator = String(conditionForm.operator.value || 'gt');

                    if (isDigitalPinName(sourcePin.pin)) {
                        operator = 'eq';
                        threshold = conditionForm.threshold.checked ? 1 : 0;
                    } else if (isCurrentTimePin(sourcePin.pin)) {
                        const parsed = parseTimeToSeconds(conditionForm.threshold.value);
                        if (parsed === null) {
                            setError('Укажите время в формате чч:мм:сс');
                            return;
                        }
                        threshold = parsed;
                    } else {
                        threshold = Number(conditionForm.threshold.value || 0);
                    }

                    const payload = {
                        scenario_id: String(conditionForm.scenario_id.value || ''),
                        pin_id: pinId,
                        operator,
                        threshold,
                    };
                    if (!payload.scenario_id) {
                        setError('Не выбран сценарий');
                        return;
                    }

                    const conditionId = String(conditionForm.condition_id.value || '');
                    if (conditionId) {
                        await fetchJson(`/api/scenes/conditions/${encodeURIComponent(conditionId)}`, {
                            method: 'PUT',
                            body: JSON.stringify({
                                pin_id: payload.pin_id,
                                operator: payload.operator,
                                threshold: payload.threshold,
                            }),
                        });
                    } else {
                        await fetchJson('/api/scenes/conditions', {
                            method: 'POST',
                            body: JSON.stringify(payload),
                        });
                    }
                    conditionDialog.close();
                    await load();
                } catch (error) {
                    setError(`Ошибка сохранения условия: ${error.message}`);
                }
            });

            load();
            setInterval(load, AUTO_REFRESH_MS);
        })();
    </script>

    <style>
        .app-dialog { width: 95%; max-width: 560px; border: 1px solid var(--line); border-radius: 12px; padding: 16px; background: rgba(21, 33, 49, 0.96); color: var(--text); }
        .modal-form { display: grid; gap: 10px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
        .scenario-condition-row {
            background: rgba(10, 20, 33, 0.82);
            border-color: var(--line) !important;
            color: var(--text);
        }
        .scenario-condition-row .small,
        .scenario-condition-row strong {
            color: var(--text);
        }
        .scenario-condition-state {
            color: #cfe2ff;
            font-weight: 600;
            letter-spacing: .2px;
        }
    </style>
</x-app-layout>
