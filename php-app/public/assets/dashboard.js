        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const controllersListEl = document.getElementById('controllers-list');
            const controllersEmptyEl = document.getElementById('controllers-empty');
            const pinsListEl = document.getElementById('pins-list');
            const pinsTitleEl = document.getElementById('pins-title');
            const pinsMessageEl = document.getElementById('pins-message');
            const refreshControllersBtn = document.getElementById('refresh-controllers-btn');
            const refreshPinsBtn = document.getElementById('refresh-pins-btn');
            const controllerSettingsDialog = document.getElementById('controllerSettingsDialog');
            const controllerSettingsForm = document.getElementById('controllerSettingsForm');
            const controllerSettingsTitle = document.getElementById('controllerSettingsTitle');
            const controllerSettingsError = document.getElementById('controllerSettingsError');
            const controllerSettingsCancelBtn = document.getElementById('controllerSettingsCancelBtn');
            const controllerSettingsSaveBtn = document.getElementById('controllerSettingsSaveBtn');
            const pinSettingsDialog = document.getElementById('pinSettingsDialog');
            const pinSettingsForm = document.getElementById('pinSettingsForm');
            const pinSettingsFieldsEl = document.getElementById('pinSettingsFields');
            const pinSettingsTitle = document.getElementById('pinSettingsTitle');
            const pinSettingsError = document.getElementById('pinSettingsError');
            const pinSettingsCancelBtn = document.getElementById('pinSettingsCancelBtn');
            const pinSettingsSaveBtn = document.getElementById('pinSettingsSaveBtn');

            let controllers = [];
            let currentPins = [];
            let selectedControllerId = null;
            let editingControllerId = null;
            let editingPin = null;
            let chartsRequestToken = 0;
            let isPinsLoading = false;
            let pinsAutoRefreshTimer = null;

            function setControllerSettingsError(message) {
                controllerSettingsError.textContent = message || '';
            }

            function openControllerSettings(controller) {
                if (!controller) return;
                editingControllerId = controller.id;
                controllerSettingsTitle.textContent = `Настройки: ${controller.name || controller.id}`;
                controllerSettingsForm.name.value = controller.name || '';
                controllerSettingsForm.discription.value = controller.discription || '';
                controllerSettingsForm.send_interval_seconds.value = String(controller.send_interval_seconds || 5);
                setControllerSettingsError('');
                controllerSettingsDialog.showModal();
            }

            function closeControllerSettings() {
                editingControllerId = null;
                setControllerSettingsError('');
                controllerSettingsDialog.close();
            }

            function normalizeStyleKey(style) {
                const value = String(style || '').trim().toLowerCase();
                if (!value) {
                    return 'sensor';
                }
                return value;
            }

            function resolveStyleTemplateKey(prefix, style) {
                const styleKey = normalizeStyleKey(style);
                const exactId = `${prefix}-${styleKey}`;
                if (document.getElementById(exactId)) {
                    return styleKey;
                }
                if (styleKey.startsWith('sensor_') && document.getElementById(`${prefix}-sensor`)) {
                    return 'sensor';
                }
                if (document.getElementById(`${prefix}-power`)) {
                    return 'power';
                }
                return 'sensor';
            }

            function cloneTemplateRoot(id) {
                const template = document.getElementById(id);
                if (!template) {
                    return null;
                }
                const node = template.content.firstElementChild;
                return node ? node.cloneNode(true) : null;
            }

            function cloneTemplateContent(id) {
                const template = document.getElementById(id);
                if (!template) {
                    return null;
                }
                return template.content.cloneNode(true);
            }

            function setPinSettingsError(message) {
                pinSettingsError.textContent = message || '';
            }

            function secondsToTimeString(totalSeconds) {
                const seconds = Number(totalSeconds);
                if (!Number.isFinite(seconds) || seconds <= 0) {
                    return '';
                }
                const safe = Math.max(0, Math.trunc(seconds));
                const h = Math.floor(safe / 3600).toString().padStart(2, '0');
                const m = Math.floor((safe % 3600) / 60).toString().padStart(2, '0');
                const s = Math.floor(safe % 60).toString().padStart(2, '0');
                return `${h}:${m}:${s}`;
            }

            function timeStringToSeconds(value) {
                const text = String(value || '').trim();
                if (!text) {
                    return null;
                }
                const parts = text.split(':');
                if (parts.length < 2 || parts.length > 3) {
                    return null;
                }
                const h = Number(parts[0] || 0);
                const m = Number(parts[1] || 0);
                const s = Number(parts[2] || 0);
                if (![h, m, s].every((n) => Number.isFinite(n) && n >= 0)) {
                    return null;
                }
                return (Math.trunc(h) * 3600) + (Math.trunc(m) * 60) + Math.trunc(s);
            }

            function openPinSettings(pin) {
                if (!pin) return;
                editingPin = pin;
                const styleKey = resolveStyleTemplateKey('pin-form-template', pin.digital_style);
                const formTemplate = cloneTemplateContent(`pin-form-template-${styleKey}`);
                if (!formTemplate) {
                    return;
                }

                pinSettingsFieldsEl.innerHTML = '';
                pinSettingsFieldsEl.appendChild(formTemplate);
                pinSettingsTitle.textContent = `Настройки: ${pin.label || pin.pin}`;

                const labelEl = pinSettingsForm.querySelector('input[name="label"]');
                const unitEl = pinSettingsForm.querySelector('input[name="unit"]');
                const avgEl = pinSettingsForm.querySelector('input[name="average_interval_minutes"]');
                const rangeEl = pinSettingsForm.querySelector('input[name="chart_range_hours"]');
                const powerTimerEl = pinSettingsForm.querySelector('input[name="power_on_duration_seconds"]');
                const invertEl = pinSettingsForm.querySelector('input[name="invert_digital_logic"]');
                const showOnChartEl = pinSettingsForm.querySelector('input[name="show_on_chart"]');

                if (labelEl) labelEl.value = pin.label || pin.pin || '';
                if (unitEl) unitEl.value = pin.unit || '';
                if (avgEl) avgEl.value = String(pin.average_interval_minutes ?? 5);
                if (rangeEl) rangeEl.value = String(pin.chart_range_hours ?? 24);
                if (powerTimerEl) powerTimerEl.value = secondsToTimeString(pin.power_on_duration_seconds);
                if (invertEl) invertEl.checked = Number(pin.invert_digital_logic || 0) > 0;
                if (showOnChartEl) showOnChartEl.checked = Number(pin.show_on_chart || 0) > 0;

                setPinSettingsError('');
                pinSettingsDialog.showModal();
            }

            function closePinSettings() {
                editingPin = null;
                setPinSettingsError('');
                pinSettingsDialog.close();
            }

            function renderControllers() {
                controllersListEl.innerHTML = '';
                controllersEmptyEl.classList.toggle('d-none', controllers.length > 0);

                controllers.forEach((controller) => {
                    const card = document.createElement('article');
                    card.className = 'border rounded p-2' + (controller.id === selectedControllerId ? ' border-primary' : '');
                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <button type="button" class="btn btn-link text-start p-0 text-decoration-none flex-grow-1 controller-select">
                                <div class="fw-semibold text-body label">${controller.name || 'Контроллер'}</div>
                                <div class="text-muted small">${controller.discription}</div>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm controller-settings" title="Настройки">⚙</button>
                        </div>
                    `;
                    card.querySelector('.controller-select').addEventListener('click', () => {
                        selectedControllerId = controller.id;
                        renderControllers();
                        loadPins();
                        schedulePinsAutoRefresh();
                    });
                    card.querySelector('.controller-settings').addEventListener('click', () => {
                        openControllerSettings(controller);
                    });
                    controllersListEl.appendChild(card);
                });
            }

            function getPinsAutoRefreshIntervalMs() {
                const selected = controllers.find((c) => c.id === selectedControllerId);
                const sendIntervalSeconds = Number(selected?.send_interval_seconds || 5);
                const baseMs = Number.isFinite(sendIntervalSeconds) && sendIntervalSeconds > 0
                    ? sendIntervalSeconds * 1000
                    : 5000;
                return Math.max(1000, baseMs * 2);
            }

            function clearPinsAutoRefresh() {
                if (pinsAutoRefreshTimer) {
                    clearTimeout(pinsAutoRefreshTimer);
                    pinsAutoRefreshTimer = null;
                }
            }

            function schedulePinsAutoRefresh() {
                clearPinsAutoRefresh();
                const tick = async () => {
                    if (selectedControllerId) {
                        await loadPins({ preserveScroll: true, silent: true }).catch(() => {});
                    }
                    pinsAutoRefreshTimer = setTimeout(tick, getPinsAutoRefreshIntervalMs());
                };
                pinsAutoRefreshTimer = setTimeout(tick, getPinsAutoRefreshIntervalMs());
            }

            function renderPinCard(pin, existingCol = null) {
                const isPowerPin = String(pin.digital_style || '') === 'power';
                const showChart = !isPowerPin && Number(pin.show_on_chart || 0) > 0;
                const rawValue = pin.value;
                let displayValue = rawValue === null || rawValue === undefined ? '—' : String(rawValue);
                let unit = pin.unit ? ` ${pin.unit}` : '';

                if (isPowerPin && rawValue !== null && rawValue !== undefined) {
                    const value = Number(rawValue || 0) > 0 ? 1 : 0;
                    displayValue = value === 1 ? 'Включен' : 'Выключен';
                }

                const actualWireValue = Number(pin.value || 0) > 0 ? 1 : 0;
                const desiredChecked = isPowerPin ? (actualWireValue === 1) : false;
                const scenarioEnabled = Number(pin.enable_scenario ?? 1) > 0;
                const statusUnit = isPowerPin ? '' : unit;
                const col = document.createElement('div');
                col.className = 'col-12';
                col.dataset.pinId = String(pin.id);
                const styleKey = resolveStyleTemplateKey('pin-card-template', pin.digital_style);
                const article = cloneTemplateRoot(`pin-card-template-${styleKey}`);
                if (!article) {
                    return col;
                }
                col.appendChild(article);

                const labelEl = col.querySelector('.pin-label');
                const codeEl = col.querySelector('.pin-code');
                const statusEl = col.querySelector('.pin-status');
                const chartMetaEl = col.querySelector('.pin-chart-meta');
                const chartEl = col.querySelector('.pin-chart');
                const powerSwitchEl = col.querySelector('.pin-power-switch');
                const scenarioSwitchEl = col.querySelector('.pin-scenario-switch');

                if (labelEl) labelEl.textContent = pin.label || pin.pin;
                if (codeEl) codeEl.textContent = pin.pin;
                if (statusEl) statusEl.textContent = `${displayValue}${statusUnit}`;

                if (powerSwitchEl) {
                    powerSwitchEl.checked = desiredChecked;
                }
                if (scenarioSwitchEl) {
                    scenarioSwitchEl.checked = scenarioEnabled;
                }

                if (chartMetaEl || chartEl) {
                    if (showChart) {
                        if (chartMetaEl) {
                            chartMetaEl.textContent = `Усреднение: ${Number(pin.average_interval_minutes || 5)} мин · Диапазон: ${Number(pin.chart_range_hours || 24)} ч`;
                        }
                        if (chartEl) {
                            chartEl.dataset.pinId = String(pin.id);
                            chartEl.textContent = 'Загрузка графика...';
                        }
                    } else {
                        if (chartMetaEl) chartMetaEl.remove();
                        if (chartEl) chartEl.remove();
                    }
                }

                col.querySelector('.pin-settings').addEventListener('click', () => {
                    openPinSettings(pin);
                });

                if (isPowerPin) {
                    const setPowerStatus = (value) => {
                        const logicalOn = Number(value) === 1;
                        if (statusEl) {
                            statusEl.textContent = logicalOn ? 'Включен' : 'Выключен';
                        }
                    };
                    if (powerSwitchEl) {
                        powerSwitchEl.addEventListener('change', async () => {
                            const nextValue = powerSwitchEl.checked ? 1 : 0;
                            const prevValue = Number(pin.desired_digital_value || 0) > 0 ? 1 : 0;
                            setPowerStatus(nextValue);
                            powerSwitchEl.disabled = true;
                            try {
                                const response = await fetch(
                                    '/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins/' + encodeURIComponent(pin.id) + '/desired-digital-value',
                                    {
                                        method: 'PUT',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrf,
                                        },
                                        body: JSON.stringify({
                                            desired_digital_value: nextValue,
                                        }),
                                    }
                                );
                                const data = await response.json();
                                if (!response.ok) {
                                    powerSwitchEl.checked = prevValue === 1;
                                    setPowerStatus(prevValue);
                                    pinsMessageEl.textContent = data.message || 'Не удалось изменить состояние пина.';
                                    return;
                                }

                                pin.desired_digital_value = Number(data?.pin?.desired_digital_value ?? nextValue);
                                pin.enable_scenario = Number(data?.pin?.enable_scenario ?? 0);
                                if (scenarioSwitchEl) {
                                    scenarioSwitchEl.checked = false;
                                }
                                setPowerStatus(pin.desired_digital_value);
                                pinsMessageEl.textContent = '';
                            } catch (_) {
                                powerSwitchEl.checked = prevValue === 1;
                                setPowerStatus(prevValue);
                                pinsMessageEl.textContent = 'Не удалось изменить состояние пина.';
                            } finally {
                                powerSwitchEl.disabled = false;
                            }
                        });
                    }

                    if (scenarioSwitchEl) {
                        scenarioSwitchEl.addEventListener('change', async () => {
                            const nextEnabled = Boolean(scenarioSwitchEl.checked);
                            const prevEnabled = Number(pin.enable_scenario ?? 1) > 0;
                            scenarioSwitchEl.disabled = true;
                            try {
                                const response = await fetch(
                                    '/api/scenes/targets/' + encodeURIComponent(pin.id) + '/enabled',
                                    {
                                        method: 'PUT',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrf,
                                        },
                                        body: JSON.stringify({ enabled: nextEnabled }),
                                    }
                                );
                                const data = await response.json();
                                if (!response.ok) {
                                    scenarioSwitchEl.checked = prevEnabled;
                                    pinsMessageEl.textContent = data.message || 'Не удалось изменить состояние сценариев для пина.';
                                    return;
                                }
                                pin.enable_scenario = nextEnabled ? 1 : 0;
                                pinsMessageEl.textContent = '';
                            } catch (_) {
                                scenarioSwitchEl.checked = prevEnabled;
                                pinsMessageEl.textContent = 'Не удалось изменить состояние сценариев для пина.';
                            } finally {
                                scenarioSwitchEl.disabled = false;
                            }
                        });
                    }
                }

                if (existingCol) {
                    existingCol.replaceWith(col);
                } else {
                    pinsListEl.appendChild(col);
                }

                return col;
            }

            function renderPins(pins) {
                currentPins = Array.isArray(pins) ? pins : [];
                pinsListEl.innerHTML = '';
                if (!Array.isArray(pins) || pins.length === 0) {
                    pinsMessageEl.textContent = 'Для выбранного контроллера нет пинов.';
                    return;
                }

                pinsMessageEl.textContent = '';
                pins.forEach((pin) => {
                    renderPinCard(pin);
                });
                void loadPinCharts();
            }

            function updatePinsInPlace(pins) {
                currentPins = Array.isArray(pins) ? pins : [];
                if (!Array.isArray(pins) || pins.length === 0) {
                    pinsListEl.innerHTML = '';
                    pinsMessageEl.textContent = 'Для выбранного контроллера нет пинов.';
                    return;
                }

                const nextById = new Map(pins.map((pin) => [String(pin.id), pin]));
                pinsListEl.querySelectorAll('[data-pin-id]').forEach((node) => {
                    const pinId = String(node.getAttribute('data-pin-id') || '');
                    if (!nextById.has(pinId)) {
                        node.remove();
                    }
                });

                pins.forEach((pin) => {
                    const pinId = String(pin.id);
                    const existingCol = pinsListEl.querySelector(`[data-pin-id="${pinId}"]`);
                    const updatedCol = renderPinCard(pin, existingCol);
                    pinsListEl.appendChild(updatedCol);
                });

                pinsMessageEl.textContent = '';
                void loadPinCharts();
            }

            function renderSparkline(container, points, unit) {
                if (!container) return;
                if (!Array.isArray(points) || points.length === 0) {
                    container.textContent = 'Нет данных для графика в выбранном диапазоне.';
                    return;
                }

                const values = points.map((p) => Number(p.value)).filter((v) => Number.isFinite(v));
                if (values.length === 0) {
                    container.textContent = 'Нет корректных данных для графика.';
                    return;
                }

                const width = 640;
                const height = 160;
                const padX = 12;
                const padY = 12;
                const minV = Math.min(...values);
                const maxV = Math.max(...values);
                const spanV = Math.max(1e-9, maxV - minV);
                const spanX = Math.max(1, points.length - 1);

                const path = points.map((point, index) => {
                    const v = Number(point.value);
                    const x = padX + ((width - padX * 2) * index) / spanX;
                    const y = padY + ((height - padY * 2) * (maxV - v)) / spanV;
                    return `${x.toFixed(1)},${y.toFixed(1)}`;
                }).join(' ');

                const firstAt = String(points[0].at || '');
                const lastAt = String(points[points.length - 1].at || '');
                const minText = Number.isFinite(minV) ? minV.toFixed(2) : '—';
                const maxText = Number.isFinite(maxV) ? maxV.toFixed(2) : '—';
                const unitText = unit ? ` ${unit}` : '';

                container.innerHTML = `
                    <svg viewBox="0 0 ${width} ${height}" class="pin-chart-svg" preserveAspectRatio="none" aria-label="График изменения значения">
                        <polyline points="${path}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
                    </svg>
                    <div class="pin-chart-legend">
                        <span>min: ${minText}${unitText}</span>
                        <span>max: ${maxText}${unitText}</span>
                        <span>${firstAt} → ${lastAt}</span>
                    </div>
                `;
            }

            async function loadPinCharts() {
                const sensorPins = currentPins.filter((pin) =>
                    String(pin.digital_style || '') !== 'power' && Number(pin.show_on_chart || 0) > 0
                );
                if (!selectedControllerId || sensorPins.length === 0) {
                    return;
                }

                const token = ++chartsRequestToken;
                try {
                    const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins/chart-data', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        return;
                    }
                    if (token !== chartsRequestToken) {
                        return;
                    }

                    const charts = data && typeof data === 'object' && data.charts && typeof data.charts === 'object'
                        ? data.charts
                        : {};

                    sensorPins.forEach((pin) => {
                        const chartContainer = pinsListEl.querySelector(`.pin-chart[data-pin-id="${pin.id}"]`);
                        if (!chartContainer) return;
                        const chart = charts[pin.id] || { points: [] };
                        renderSparkline(chartContainer, Array.isArray(chart.points) ? chart.points : [], pin.unit || '');
                    });
                } catch (_) {
                    // keep cards usable even if chart loading fails
                }
            }

            async function loadControllers() {
                const response = await fetch('/api/pairing/my-controllers', {
                    headers: {'Accept': 'application/json'}
                });
                const data = await response.json();
                controllers = Array.isArray(data.controllers) ? data.controllers : [];
                if (!selectedControllerId && controllers.length > 0) {
                    selectedControllerId = controllers[0].id;
                }
                if (selectedControllerId && !controllers.some((c) => c.id === selectedControllerId)) {
                    selectedControllerId = controllers.length > 0 ? controllers[0].id : null;
                }
                renderControllers();
                await loadPins();
                schedulePinsAutoRefresh();
            }

            async function loadPins(options = {}) {
                const preserveScroll = Boolean(options.preserveScroll);
                const silent = Boolean(options.silent);
                if (isPinsLoading) {
                    return;
                }
                isPinsLoading = true;
                const scrollYBefore = preserveScroll ? window.scrollY : null;
                try {
                    if (!selectedControllerId) {
                        pinsListEl.innerHTML = '';
                        pinsTitleEl.textContent = 'Пины контроллера';
                        pinsMessageEl.textContent = 'Выберите контроллер слева.';
                        refreshPinsBtn.disabled = true;
                        return;
                    }

                    const selected = controllers.find((c) => c.id === selectedControllerId);
                    pinsTitleEl.textContent = `Пины: ${selected?.name || selectedControllerId}`;
                    if (!silent) {
                        pinsListEl.innerHTML = '';
                        pinsMessageEl.textContent = 'Загрузка...';
                    }
                    refreshPinsBtn.disabled = false;

                    const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins', {
                        headers: {'Accept': 'application/json'}
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        pinsMessageEl.textContent = data.message || 'Не удалось загрузить пины.';
                        return;
                    }
                    if (silent) {
                        updatePinsInPlace(Array.isArray(data.pins) ? data.pins : []);
                    } else {
                        renderPins(data.pins);
                    }
                    if (preserveScroll && typeof scrollYBefore === 'number') {
                        window.scrollTo(0, scrollYBefore);
                    }
                } finally {
                    isPinsLoading = false;
                }
            }

            refreshControllersBtn.addEventListener('click', () => {
                loadControllers().catch(() => {});
            });
            refreshPinsBtn.addEventListener('click', () => {
                loadPins().catch(() => {});
            });
            controllerSettingsCancelBtn.addEventListener('click', () => {
                closeControllerSettings();
            });
            pinSettingsCancelBtn.addEventListener('click', () => {
                closePinSettings();
            });
            controllerSettingsForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!editingControllerId) return;
                setControllerSettingsError('');
                controllerSettingsSaveBtn.disabled = true;
                controllerSettingsSaveBtn.textContent = 'Сохранение...';

                try {
                    const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(editingControllerId) + '/settings', {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            name: String(controllerSettingsForm.name.value || '').trim(),
                            discription: String(controllerSettingsForm.discription.value || '').trim(),
                            send_interval_seconds: Number(controllerSettingsForm.send_interval_seconds.value || 5),
                        }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        setControllerSettingsError(data.message || 'Не удалось сохранить настройки.');
                        return;
                    }

                    closeControllerSettings();
                    await loadControllers();
                    schedulePinsAutoRefresh();
                } catch (_) {
                    setControllerSettingsError('Не удалось сохранить настройки.');
                } finally {
                    controllerSettingsSaveBtn.disabled = false;
                    controllerSettingsSaveBtn.textContent = 'Сохранить';
                }
            });
            pinSettingsForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!editingPin || !selectedControllerId) return;

                setPinSettingsError('');
                pinSettingsSaveBtn.disabled = true;
                pinSettingsSaveBtn.textContent = 'Сохранение...';

                const labelEl = pinSettingsForm.querySelector('input[name="label"]');
                const unitEl = pinSettingsForm.querySelector('input[name="unit"]');
                const avgEl = pinSettingsForm.querySelector('input[name="average_interval_minutes"]');
                const rangeEl = pinSettingsForm.querySelector('input[name="chart_range_hours"]');
                const powerTimerEl = pinSettingsForm.querySelector('input[name="power_on_duration_seconds"]');
                const invertEl = pinSettingsForm.querySelector('input[name="invert_digital_logic"]');
                const showOnChartEl = pinSettingsForm.querySelector('input[name="show_on_chart"]');
                const powerDurationSeconds = powerTimerEl ? timeStringToSeconds(powerTimerEl.value) : null;
                const payload = {
                    label: String(labelEl?.value || '').trim(),
                    unit: String(unitEl?.value || '').trim() || null,
                    average_interval_minutes: Number(avgEl?.value || 5),
                    chart_range_hours: Number(rangeEl?.value || 24),
                    power_on_duration_seconds: powerDurationSeconds,
                    invert_digital_logic: Boolean(invertEl?.checked),
                    show_on_chart: Boolean(showOnChartEl?.checked),
                };

                try {
                    const response = await fetch(
                        '/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins/' + encodeURIComponent(editingPin.id) + '/settings',
                        {
                            method: 'PUT',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify(payload),
                        }
                    );
                    const data = await response.json();
                    if (!response.ok) {
                        setPinSettingsError(data.message || 'Не удалось сохранить настройки пина.');
                        return;
                    }

                    closePinSettings();
                    await loadPins();
                } catch (_) {
                    setPinSettingsError('Не удалось сохранить настройки пина.');
                } finally {
                    pinSettingsSaveBtn.disabled = false;
                    pinSettingsSaveBtn.textContent = 'Сохранить';
                }
            });

            loadControllers().catch(() => {});
            schedulePinsAutoRefresh();
        })();
