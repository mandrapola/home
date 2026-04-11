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

            function isDigitalPin(pinName) {
                const pin = String(pinName || '').toUpperCase();
                return /^D\d+$/.test(pin) || /^RELAY_\d+$/.test(pin);
            }

            function isAnalogPin(pinName) {
                return !isDigitalPin(pinName);
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

            function togglePinSettingsFields(pinName) {
                const digital = isDigitalPin(pinName);
                const analog = isAnalogPin(pinName);
                pinSettingsForm.querySelectorAll('[data-field-group="digital"]').forEach((el) => {
                    el.classList.toggle('d-none', !digital);
                });
                pinSettingsForm.querySelectorAll('[data-field-group="analog"]').forEach((el) => {
                    el.classList.toggle('d-none', !analog);
                });
            }

            function openPinSettings(pin) {
                if (!pin) return;
                editingPin = pin;
                pinSettingsTitle.textContent = `Настройки: ${pin.label || pin.pin}`;
                pinSettingsForm.label.value = pin.label || pin.pin || '';
                pinSettingsForm.unit.value = pin.unit || '';
                pinSettingsForm.average_interval_minutes.value = String(pin.average_interval_minutes ?? 5);
                pinSettingsForm.chart_range_hours.value = String(pin.chart_range_hours ?? 24);
                pinSettingsForm.power_on_duration_seconds.value = secondsToTimeString(pin.power_on_duration_seconds);
                pinSettingsForm.invert_digital_logic.checked = Number(pin.invert_digital_logic || 0) > 0;
                pinSettingsForm.show_on_chart.checked = Number(pin.show_on_chart || 0) > 0;
                togglePinSettingsFields(pin.pin);
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
                                <div class="fw-semibold text-body">${controller.name || 'Контроллер'}</div>
                                <div class="text-muted small">${controller.discription}</div>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm controller-settings" title="Настройки">⚙</button>
                        </div>
                    `;
                    card.querySelector('.controller-select').addEventListener('click', () => {
                        selectedControllerId = controller.id;
                        renderControllers();
                        loadPins();
                    });
                    card.querySelector('.controller-settings').addEventListener('click', () => {
                        openControllerSettings(controller);
                    });
                    controllersListEl.appendChild(card);
                });
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
                col.innerHTML = `
                    <article class="border rounded p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">${pin.label || pin.pin}</div>
                            <button type="button" class="btn btn-outline-secondary btn-sm pin-settings" title="Настройки пина">⚙</button>
                        </div>
                        <div class="text-muted small mb-1">${pin.pin}</div>
                        <div><strong class="pin-status">${displayValue}${statusUnit}</strong></div>
                        ${isPowerPin ? `
                            <div class="form-check form-switch mt-2 mb-0">
                                <input class="form-check-input pin-power-switch" type="checkbox" ${desiredChecked ? 'checked' : ''}>
                                <label class="form-check-label small ms-2">Вкл.</label>
                            </div>
                            <div class="form-check form-switch mt-2 mb-0">
                                <input class="form-check-input pin-scenario-switch" type="checkbox" ${scenarioEnabled ? 'checked' : ''}>
                                <label class="form-check-label small ms-2">Сценарии</label>
                            </div>
                        ` : ''}
                        ${showChart ? `
                            <div class="pin-chart-meta mt-2 small text-muted">
                                Усреднение: ${Number(pin.average_interval_minutes || 5)} мин · Диапазон: ${Number(pin.chart_range_hours || 24)} ч
                            </div>
                            <div class="pin-chart" data-pin-id="${pin.id}">Загрузка графика...</div>
                        ` : ''}
                    </article>
                `;

                col.querySelector('.pin-settings').addEventListener('click', () => {
                    openPinSettings(pin);
                });

                if (isPowerPin) {
                    const switchEl = col.querySelector('.pin-power-switch');
                    const scenarioSwitchEl = col.querySelector('.pin-scenario-switch');
                    const statusEl = col.querySelector('.pin-status');
                    const setPowerStatus = (value) => {
                        const logicalOn = Number(value) === 1;
                        if (statusEl) {
                            statusEl.textContent = logicalOn ? 'Включен' : 'Выключен';
                        }
                    };
                    if (switchEl) {
                        switchEl.addEventListener('change', async () => {
                            const nextValue = switchEl.checked ? 1 : 0;
                            const prevValue = Number(pin.desired_digital_value || 0) > 0 ? 1 : 0;
                            setPowerStatus(nextValue);
                            switchEl.disabled = true;
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
                                    switchEl.checked = prevValue === 1;
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
                                switchEl.checked = prevValue === 1;
                                setPowerStatus(prevValue);
                                pinsMessageEl.textContent = 'Не удалось изменить состояние пина.';
                            } finally {
                                switchEl.disabled = false;
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

                const powerDurationSeconds = timeStringToSeconds(pinSettingsForm.power_on_duration_seconds.value);
                const payload = {
                    label: String(pinSettingsForm.label.value || '').trim(),
                    unit: String(pinSettingsForm.unit.value || '').trim() || null,
                    average_interval_minutes: Number(pinSettingsForm.average_interval_minutes.value || 5),
                    chart_range_hours: Number(pinSettingsForm.chart_range_hours.value || 24),
                    power_on_duration_seconds: powerDurationSeconds,
                    invert_digital_logic: Boolean(pinSettingsForm.invert_digital_logic.checked),
                    show_on_chart: Boolean(pinSettingsForm.show_on_chart.checked),
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
            setInterval(() => {
                if (selectedControllerId) {
                    loadPins({ preserveScroll: true, silent: true }).catch(() => {});
                }
            }, 10000);
        })();
