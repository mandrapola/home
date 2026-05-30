        (function () {
            const i18n = window.dashboardI18n || {};
            const t = (key, fallback) => i18n[key] || fallback || key;
            const unitLabels = i18n.unit_labels && typeof i18n.unit_labels === 'object' ? i18n.unit_labels : {};
            const localizeUnitLabel = (unit) => {
                const raw = String(unit || '').trim();
                if (raw === '') return '';
                const lower = raw.toLowerCase();
                if (unitLabels[raw] !== undefined) return String(unitLabels[raw]);
                if (unitLabels[lower] !== undefined) return String(unitLabels[lower]);
                return raw;
            };
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
            const controllerSettingsDeleteBtn = document.getElementById('controllerSettingsDeleteBtn');
            const controllerDeleteDialog = document.getElementById('controllerDeleteDialog');
            const controllerDeleteError = document.getElementById('controllerDeleteError');
            const controllerDeleteCancelBtn = document.getElementById('controllerDeleteCancelBtn');
            const controllerDeleteConfirmBtn = document.getElementById('controllerDeleteConfirmBtn');
            const pinSettingsDialog = document.getElementById('pinSettingsDialog');
            const pinSettingsForm = document.getElementById('pinSettingsForm');
            const pinSettingsFieldsEl = document.getElementById('pinSettingsFields');
            const pinSettingsTitle = document.getElementById('pinSettingsTitle');
            const pinSettingsError = document.getElementById('pinSettingsError');
            const pinSettingsCancelBtn = document.getElementById('pinSettingsCancelBtn');
            const pinSettingsSaveBtn = document.getElementById('pinSettingsSaveBtn');
            const pinChartDialog = document.getElementById('pinChartDialog');
            const pinChartTitle = document.getElementById('pinChartTitle');
            const pinChartBody = document.getElementById('pinChartBody');
            const pinChartCloseBtn = document.getElementById('pinChartCloseBtn');
            const pinChartRangeButtons = document.getElementById('pinChartRangeButtons');

            let controllers = [];
            let currentPins = [];
            let selectedControllerId = null;
            let editingControllerId = null;
            let editingPin = null;
            let chartPin = null;
            let chartsRequestToken = 0;
            let isPinsLoading = false;
            let pinsAutoRefreshTimer = null;
            const powerPinsPendingSyncUntil = new Map();
            const powerPinsPendingExpectedValue = new Map();

            function markPowerPinPendingSync(pinId, expectedValue, ttlMs = 10000) {
                const key = String(pinId);
                powerPinsPendingSyncUntil.set(key, Date.now() + Math.max(1000, Number(ttlMs) || 10000));
                powerPinsPendingExpectedValue.set(key, Number(expectedValue) > 0 ? 1 : 0);
            }

            function clearPowerPinPendingSync(pinId) {
                const key = String(pinId);
                powerPinsPendingSyncUntil.delete(key);
                powerPinsPendingExpectedValue.delete(key);
            }

            function isPowerPinPendingSync(pinId) {
                const key = String(pinId);
                const until = Number(powerPinsPendingSyncUntil.get(key) || 0);
                if (!Number.isFinite(until) || until <= Date.now()) {
                    clearPowerPinPendingSync(key);
                    return false;
                }
                return true;
            }

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

            function setControllerDeleteError(message) {
                if (controllerDeleteError) {
                    controllerDeleteError.textContent = message || '';
                }
            }

            function closeControllerDeleteDialog() {
                setControllerDeleteError('');
                controllerDeleteDialog?.close();
            }

            async function deleteEditingController() {
                if (!editingControllerId) return;

                controllerSettingsDeleteBtn.disabled = true;
                if (controllerDeleteConfirmBtn) {
                    controllerDeleteConfirmBtn.disabled = true;
                    controllerDeleteConfirmBtn.textContent = t('deleting', 'Deleting...');
                }
                setControllerSettingsError('');
                setControllerDeleteError('');

                try {
                    const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(editingControllerId), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        const message = data.message || t('failed_delete_controller', 'Failed to delete controller.');
                        setControllerSettingsError(message);
                        setControllerDeleteError(message);
                        return;
                    }

                    selectedControllerId = null;
                    closeControllerDeleteDialog();
                    closeControllerSettings();
                    currentPins = [];
                    pinsListEl.innerHTML = '';
                    await loadControllers();
                    await loadPins();
                    schedulePinsAutoRefresh();
                } catch (_) {
                    const message = t('failed_delete_controller', 'Failed to delete controller.');
                    setControllerSettingsError(message);
                    setControllerDeleteError(message);
                } finally {
                    controllerSettingsDeleteBtn.disabled = false;
                    if (controllerDeleteConfirmBtn) {
                        controllerDeleteConfirmBtn.disabled = false;
                        controllerDeleteConfirmBtn.textContent = t('delete', 'Delete');
                    }
                }
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
                const rangeEl = pinSettingsForm.querySelector('[name="chart_range_hours"]');
                const showOnChartEl = pinSettingsForm.querySelector('input[name="show_on_chart"]');
                const showOnReportEl = pinSettingsForm.querySelector('input[name="show_on_report"]');
                const isMonitoredEl = pinSettingsForm.querySelector('input[name="is_monitored"]');
                const externalEnabledEl = pinSettingsForm.querySelector('input[name="external_enabled"]');
                const moistureRawDryEl = pinSettingsForm.querySelector('input[name="moisture_raw_dry"]');
                const moistureRawWetEl = pinSettingsForm.querySelector('input[name="moisture_raw_wet"]');
                const moistureShowPercentEl = pinSettingsForm.querySelector('input[name="moisture_show_percent"]');

                if (labelEl) labelEl.value = pin.label || pin.pin || '';
                if (unitEl) unitEl.value = pin.unit || '';
                if (rangeEl) rangeEl.value = String(pin.chart_range_hours ?? 24);
                if (showOnChartEl) showOnChartEl.checked = Number(pin.show_on_chart || 0) > 0;
                if (showOnReportEl) showOnReportEl.checked = Number(pin.show_on_report ?? 1) > 0;
                if (isMonitoredEl) isMonitoredEl.checked = Number(pin.is_monitored || 0) > 0;
                if (externalEnabledEl) externalEnabledEl.checked = Number(pin.external_enabled ?? 1) > 0;
                if (moistureRawDryEl) moistureRawDryEl.value = pin.moisture_raw_dry ?? '';
                if (moistureRawWetEl) moistureRawWetEl.value = pin.moisture_raw_wet ?? '';
                if (moistureShowPercentEl) moistureShowPercentEl.checked = Number(pin.moisture_show_percent ?? 0) > 0;

                setPinSettingsError('');
                pinSettingsDialog.showModal();
            }

            function closePinSettings() {
                editingPin = null;
                setPinSettingsError('');
                pinSettingsDialog.close();
            }

            function getPinById(pinId) {
                const key = String(pinId || '');
                return currentPins.find((pin) => String(pin.id) === key) || null;
            }

            function setChartRangeButtonState(rangeHours) {
                const value = Number(rangeHours || 24);
                if (!pinChartRangeButtons) return;
                pinChartRangeButtons.querySelectorAll('button[data-range-hours]').forEach((btn) => {
                    const btnRange = Number(btn.getAttribute('data-range-hours') || 0);
                    btn.classList.toggle('active', btnRange === value);
                });
            }

            async function fetchChartDataMap() {
                if (!selectedControllerId) {
                    return {};
                }
                const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins/chart-data', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || t('chart_failed', 'Failed to load chart.'));
                }
                return data && typeof data === 'object' && data.charts && typeof data.charts === 'object'
                    ? data.charts
                    : {};
            }

            async function renderPinChartDialog(pin) {
                if (!pin || !pinChartBody) return;
                const current = getPinById(pin.id) || pin;
                const styleKey = resolveStyleTemplateKey('pin-chart-template', current.digital_style);
                const chartTemplate = cloneTemplateContent(`pin-chart-template-${styleKey}`);
                if (!chartTemplate) {
                    pinChartBody.textContent = t('chart_failed', 'Failed to load chart.');
                    return;
                }

                pinChartBody.innerHTML = '';
                pinChartBody.appendChild(chartTemplate);

                const panel = pinChartBody.querySelector('.pin-chart-panel');
                const pinMeta = pinChartBody.querySelector('.pin-chart-pin');
                const rangeMeta = pinChartBody.querySelector('.pin-chart-range');
                const canvas = pinChartBody.querySelector('.pin-chart-canvas');

                if (pinMeta) {
                    pinMeta.textContent = current.label || current.pin || '';
                }
                if (rangeMeta) {
                    rangeMeta.textContent = `${Number(current.chart_range_hours || 24)} ч`;
                }
                setChartRangeButtonState(current.chart_range_hours || 24);
                if (panel) panel.dataset.pinId = String(current.id);
                if (canvas) {
                    canvas.textContent = t('chart_loading', 'Loading chart...');
                }

                try {
                    const charts = await fetchChartDataMap();
                    const chart = charts[current.id] || { points: [] };
                    if (!canvas) return;
                    renderDetailedChart(canvas, Array.isArray(chart.points) ? chart.points : [], localizeUnitLabel(current.unit));
                } catch (_) {
                    if (canvas) {
                        canvas.textContent = t('chart_failed', 'Failed to load chart.');
                    }
                }
            }

            async function openPinChart(pin) {
                if (!pin || !pinChartDialog) return;
                chartPin = getPinById(pin.id) || pin;
                if (pinChartTitle) {
                    pinChartTitle.textContent = `${t('chart', 'Chart')}: ${chartPin.label || chartPin.pin || ''}`;
                }
                pinChartDialog.showModal();
                await renderPinChartDialog(chartPin);
            }

            function closePinChart() {
                chartPin = null;
                if (pinChartBody) {
                    pinChartBody.innerHTML = '';
                }
                if (pinChartDialog) {
                    pinChartDialog.close();
                }
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
                                <div class="fw-semibold text-body label">${controller.name || t('controller', 'Controller')}</div>
                                <div class="text-muted small">${controller.discription}</div>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm controller-settings" title="${t('settings', 'Settings')}">⚙</button>
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

            function sensorIconByStyle(style) {
                const key = String(style || '').toLowerCase();
                if (key === 'sensor_humidity') return '💧';
                if (key === 'sensor_temperature') return '🌡️';
                if (key === 'sensor_light') return '☀️';
                if (key === 'sensor_pressure') return '🔵';
                if (key === 'sensor_level') return '🛢️';
                return '📟';
            }

            function renderPinCard(pin, existingCol = null) {
                const isPowerPin = String(pin.digital_style || '') === 'power';
                const showChart = !isPowerPin && Number(pin.show_on_chart || 0) > 0;
                const isMonitored = Number(pin.is_monitored || 0) > 0;
                const rawValue = pin.value;
                let displayValue = rawValue === null || rawValue === undefined ? '—' : String(rawValue);
                const localizedUnit = localizeUnitLabel(pin.unit);
                let unit = localizedUnit ? ` ${localizedUnit}` : '';

                if (isPowerPin && rawValue !== null && rawValue !== undefined) {
                    const value = Number(rawValue || 0) > 0 ? 1 : 0;
                    displayValue = value === 1 ? 'Включен' : 'Выключен';
                }

                const actualWireValue = Number(pin.value || 0) > 0 ? 1 : 0;
                const desiredChecked = isPowerPin ? (actualWireValue === 1) : false;
                const scenarioEnabled = Number(pin.enable_scenario ?? 1) > 0;
                const statusUnit = isPowerPin ? '' : unit;
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-xl-3';
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

                if (labelEl) {
                    labelEl.textContent = pin.label || pin.pin;
                    labelEl.title = pin.label || pin.pin || '';
                }
                if (codeEl) {
                    if (isPowerPin) {
                        codeEl.remove();
                    } else {
                        codeEl.classList.add('pin-code--icon');
                        codeEl.innerHTML = `<span aria-hidden="true">${sensorIconByStyle(pin.digital_style)}</span>`;
                    }
                }
                if (statusEl) statusEl.textContent = `${displayValue}${statusUnit}`;
                if (statusEl) statusEl.classList.toggle('pin-status--monitored', isMonitored);

                if (powerSwitchEl) {
                    powerSwitchEl.checked = desiredChecked;
                }
                if (scenarioSwitchEl) {
                    scenarioSwitchEl.checked = scenarioEnabled;
                }
                if (chartMetaEl || chartEl) {
                    if (showChart) {
                        if (chartMetaEl) {
                            chartMetaEl.textContent = `Диапазон: ${Number(pin.chart_range_hours || 24)} ч`;
                        }
                        if (chartEl) {
                            chartEl.dataset.pinId = String(pin.id);
                            chartEl.textContent = t('chart_loading', 'Loading chart...');
                        }
                    } else {
                        if (chartMetaEl) chartMetaEl.remove();
                        if (chartEl) chartEl.remove();
                    }
                }

                col.querySelector('.pin-settings').addEventListener('click', () => {
                    openPinSettings(pin);
                });

                const chartOpenBtn = col.querySelector('.pin-chart-open');
                if (chartOpenBtn) {
                    if (showChart) {
                        chartOpenBtn.disabled = false;
                        chartOpenBtn.addEventListener('click', () => {
                            openPinChart(pin).catch(() => {});
                        });
                    } else {
                        chartOpenBtn.remove();
                    }
                }

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
                            const prevDesiredUpdatedAt = pin.desired_digital_updated_at ?? null;
                            const prevEnableScenario = Number(pin.enable_scenario ?? 0);
                            markPowerPinPendingSync(pin.id, nextValue, getPinsAutoRefreshIntervalMs());
                            setPowerStatus(nextValue);
                            pin.desired_digital_value = nextValue;
                            pin.desired_digital_updated_at = new Date().toISOString();
                            pin.enable_scenario = 0;
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
                                    pin.desired_digital_value = prevValue;
                                    pin.desired_digital_updated_at = prevDesiredUpdatedAt;
                                    pin.enable_scenario = prevEnableScenario;
                                    clearPowerPinPendingSync(pin.id);
                                    pinsMessageEl.textContent = data.message || t('failed_change_pin_state', 'Failed to change pin state.');
                                    return;
                                }

                                pin.desired_digital_value = Number(data?.pin?.desired_digital_value ?? nextValue);
                                pin.enable_scenario = Number(data?.pin?.enable_scenario ?? 0);
                                pin.desired_digital_updated_at = data?.pin?.desired_digital_updated_at ?? pin.desired_digital_updated_at ?? null;
                                if (scenarioSwitchEl) {
                                    scenarioSwitchEl.checked = false;
                                }
                                setPowerStatus(pin.desired_digital_value);
                                pinsMessageEl.textContent = '';
                            } catch (_) {
                                powerSwitchEl.checked = prevValue === 1;
                                setPowerStatus(prevValue);
                                pin.desired_digital_value = prevValue;
                                pin.desired_digital_updated_at = prevDesiredUpdatedAt;
                                pin.enable_scenario = prevEnableScenario;
                                clearPowerPinPendingSync(pin.id);
                                pinsMessageEl.textContent = t('failed_change_pin_state', 'Failed to change pin state.');
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
                                    clearPowerPinPendingSync(pin.id);
                                    pinsMessageEl.textContent = data.message || t('failed_change_pin_scenarios', 'Failed to change scenario state for pin.');
                                    return;
                                }
                                pin.enable_scenario = nextEnabled ? 1 : 0;
                                clearPowerPinPendingSync(pin.id);
                                if (data?.pin) {
                                    pin.desired_digital_value = Number(data.pin.desired_digital_value ?? pin.desired_digital_value ?? 0);
                                    pin.desired_digital_updated_at = data.pin.desired_digital_updated_at ?? pin.desired_digital_updated_at ?? null;
                                }
                                pinsMessageEl.textContent = '';
                            } catch (_) {
                                scenarioSwitchEl.checked = prevEnabled;
                                clearPowerPinPendingSync(pin.id);
                                pinsMessageEl.textContent = t('failed_change_pin_scenarios', 'Failed to change scenario state for pin.');
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

            function extractRelayOrder(pinName) {
                const text = String(pinName || '').toLowerCase();
                const match = text.match(/^relay_(\d+)$/);
                return match ? Number(match[1]) : Number.POSITIVE_INFINITY;
            }

            function sortPinsForDashboard(pins) {
                return [...pins].sort((a, b) => {
                    const aStyle = String(a?.digital_style || '').toLowerCase();
                    const bStyle = String(b?.digital_style || '').toLowerCase();
                    const aIsPower = aStyle === 'power';
                    const bIsPower = bStyle === 'power';

                    if (aIsPower !== bIsPower) {
                        return aIsPower ? -1 : 1;
                    }

                    if (aIsPower && bIsPower) {
                        const aRelay = extractRelayOrder(a?.pin);
                        const bRelay = extractRelayOrder(b?.pin);
                        if (aRelay !== bRelay) {
                            return aRelay - bRelay;
                        }
                    }

                    const aPin = String(a?.pin || '').toLowerCase();
                    const bPin = String(b?.pin || '').toLowerCase();
                    return aPin.localeCompare(bPin, 'ru');
                });
            }

            function renderPins(pins) {
                currentPins = sortPinsForDashboard(Array.isArray(pins) ? pins : []);
                pinsListEl.innerHTML = '';
                if (!Array.isArray(currentPins) || currentPins.length === 0) {
                    pinsMessageEl.textContent = t('pins_not_found', 'No pins found for selected controller.');
                    return;
                }

                pinsMessageEl.textContent = '';
                currentPins.forEach((pin) => {
                    renderPinCard(pin);
                });
                void loadPinCharts();
            }

            function updatePinsInPlace(pins) {
                currentPins = sortPinsForDashboard(Array.isArray(pins) ? pins : []);
                if (!Array.isArray(currentPins) || currentPins.length === 0) {
                    pinsListEl.innerHTML = '';
                    pinsMessageEl.textContent = t('pins_not_found', 'No pins found for selected controller.');
                    return;
                }

                const nextById = new Map(currentPins.map((pin) => [String(pin.id), pin]));
                pinsListEl.querySelectorAll('[data-pin-id]').forEach((node) => {
                    const pinId = String(node.getAttribute('data-pin-id') || '');
                    if (!nextById.has(pinId)) {
                        node.remove();
                    }
                });

                currentPins.forEach((pin) => {
                    const pinId = String(pin.id);
                    const existingCol = pinsListEl.querySelector(`[data-pin-id="${pinId}"]`);
                    if (
                        existingCol &&
                        String(pin.digital_style || '').toLowerCase() === 'power' &&
                        isPowerPinPendingSync(pinId)
                    ) {
                        const expected = powerPinsPendingExpectedValue.get(pinId);
                        const actual = Number(pin.value || 0) > 0 ? 1 : 0;
                        if (expected !== undefined && actual !== expected) {
                            return;
                        }
                        clearPowerPinPendingSync(pinId);
                    }
                    renderPinCard(pin, existingCol);
                });

                pinsMessageEl.textContent = '';
                void loadPinCharts();
            }

            function renderSparkline(container, points, unit) {
                if (!container) return;
                if (!Array.isArray(points) || points.length === 0) {
                    container.textContent = t('chart_no_data_range', 'No chart data for selected range.');
                    return;
                }

                const values = points.map((p) => Number(p.value)).filter((v) => Number.isFinite(v));
                if (values.length === 0) {
                    container.textContent = t('chart_no_valid_data', 'No valid chart data.');
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
                    <svg viewBox="0 0 ${width} ${height}" class="pin-chart-svg" preserveAspectRatio="none" aria-label="${t('chart', 'Chart')}">
                        <polyline points="${path}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
                    </svg>
                    <div class="pin-chart-legend">
                        <span>${t('min', 'min')}: ${minText}${unitText}</span>
                        <span>${t('max', 'max')}: ${maxText}${unitText}</span>
                        <span>${firstAt} → ${lastAt}</span>
                    </div>
                `;
            }

            function formatChartTimeLabel(value) {
                const text = String(value || '');
                const match = text.match(/\b(\d{2}:\d{2})(?::\d{2})?\b/);
                return match ? match[1] : text;
            }

            function renderDetailedChart(container, points, unit) {
                if (!container) return;
                if (!Array.isArray(points) || points.length === 0) {
                    container.textContent = t('chart_no_data_range', 'No chart data for selected range.');
                    return;
                }

                const numericPoints = points
                    .map((point) => ({ value: Number(point.value), at: String(point.at || '') }))
                    .filter((point) => Number.isFinite(point.value));
                if (numericPoints.length === 0) {
                    container.textContent = t('chart_no_valid_data', 'No valid chart data.');
                    return;
                }

                const width = 820;
                const height = 320;
                const margin = { top: 22, right: 14, bottom: 44, left: 56 };
                const plotWidth = width - margin.left - margin.right;
                const plotHeight = height - margin.top - margin.bottom;
                const values = numericPoints.map((p) => p.value);
                const minV = Math.min(...values);
                const maxV = Math.max(...values);
                const span = Math.max(1e-9, maxV - minV);
                const paddedMin = minV - span * 0.08;
                const paddedMax = maxV + span * 0.08;
                const ySpan = Math.max(1e-9, paddedMax - paddedMin);
                const xSpan = Math.max(1, numericPoints.length - 1);
                const unitText = unit ? ` ${unit}` : '';

                const toX = (idx) => margin.left + (plotWidth * idx) / xSpan;
                const toY = (value) => margin.top + ((paddedMax - value) / ySpan) * plotHeight;
                const polyline = numericPoints
                    .map((point, idx) => `${toX(idx).toFixed(2)},${toY(point.value).toFixed(2)}`)
                    .join(' ');

                const yTicks = 5;
                const yTickLines = [];
                const yTickLabels = [];
                for (let i = 0; i <= yTicks; i += 1) {
                    const value = paddedMin + ((yTicks - i) * ySpan) / yTicks;
                    const y = margin.top + (plotHeight * i) / yTicks;
                    yTickLines.push(`<line x1="${margin.left}" y1="${y.toFixed(2)}" x2="${(width - margin.right).toFixed(2)}" y2="${y.toFixed(2)}" stroke="rgba(255,255,255,0.10)" stroke-width="1"/>`);
                    yTickLabels.push(`<text x="${(margin.left - 8).toFixed(2)}" y="${(y + 4).toFixed(2)}" text-anchor="end" fill="var(--muted)" font-size="11">${value.toFixed(1)}${unitText}</text>`);
                }

                const xTickIndexes = Array.from(new Set([0, Math.floor(xSpan * 0.25), Math.floor(xSpan * 0.5), Math.floor(xSpan * 0.75), xSpan]))
                    .filter((idx) => idx >= 0 && idx < numericPoints.length);
                const xTickLines = [];
                const xTickLabels = [];
                xTickIndexes.forEach((idx) => {
                    const x = toX(idx);
                    xTickLines.push(`<line x1="${x.toFixed(2)}" y1="${margin.top}" x2="${x.toFixed(2)}" y2="${(height - margin.bottom).toFixed(2)}" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>`);
                    xTickLabels.push(`<text x="${x.toFixed(2)}" y="${(height - margin.bottom + 18).toFixed(2)}" text-anchor="middle" fill="var(--muted)" font-size="11">${formatChartTimeLabel(numericPoints[idx].at)}</text>`);
                });

                const firstAt = formatChartTimeLabel(numericPoints[0].at);
                const lastAt = formatChartTimeLabel(numericPoints[numericPoints.length - 1].at);

                container.innerHTML = `
                    <svg viewBox="0 0 ${width} ${height}" class="pin-chart-svg" preserveAspectRatio="none" aria-label="${t('chart', 'Chart')}">
                        ${yTickLines.join('')}
                        ${xTickLines.join('')}
                        <line x1="${margin.left}" y1="${(height - margin.bottom).toFixed(2)}" x2="${(width - margin.right).toFixed(2)}" y2="${(height - margin.bottom).toFixed(2)}" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"/>
                        <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${(height - margin.bottom).toFixed(2)}" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"/>
                        <polyline points="${polyline}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        ${yTickLabels.join('')}
                        ${xTickLabels.join('')}
                    </svg>
                    <div class="pin-chart-legend">
                        <span>${t('min', 'min')}: ${minV.toFixed(2)}${unitText}</span>
                        <span>${t('max', 'max')}: ${maxV.toFixed(2)}${unitText}</span>
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
                        renderSparkline(chartContainer, Array.isArray(chart.points) ? chart.points : [], localizeUnitLabel(pin.unit));
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
                        pinsTitleEl.textContent = t('pins_controller', 'Controller Pins');
                    pinsMessageEl.textContent = t('select_controller_left', 'Select a controller on the left.');
                    refreshPinsBtn.disabled = true;
                    return;
                }

                    const selected = controllers.find((c) => c.id === selectedControllerId);
                    pinsTitleEl.textContent = `Пины: ${selected?.name || selectedControllerId}`;
                    if (!silent) {
                        pinsListEl.innerHTML = '';
                        pinsMessageEl.textContent = t('loading', 'Loading...');
                    }
                    refreshPinsBtn.disabled = false;

                    const response = await fetch('/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins', {
                        headers: {'Accept': 'application/json'}
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        pinsMessageEl.textContent = data.message || t('failed_load_pins', 'Failed to load pins.');
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
            if (pinChartCloseBtn) {
                pinChartCloseBtn.addEventListener('click', () => {
                    closePinChart();
                });
            }
            if (pinChartRangeButtons) {
                pinChartRangeButtons.addEventListener('click', async (event) => {
                    const target = event.target instanceof Element ? event.target.closest('button[data-range-hours]') : null;
                    if (!target || !chartPin || !selectedControllerId) {
                        return;
                    }
                    const nextRange = Number(target.getAttribute('data-range-hours') || 0);
                    if (!Number.isFinite(nextRange) || nextRange <= 0) {
                        return;
                    }

                    const payload = {
                        label: String(chartPin.label || chartPin.pin || '').trim(),
                        unit: String(chartPin.unit || '').trim() || null,
                        chart_range_hours: nextRange,
                        show_on_chart: Number(chartPin.show_on_chart || 0) > 0,
                        show_on_report: Number(chartPin.show_on_report ?? 1) > 0,
                        is_monitored: Number(chartPin.is_monitored || 0) > 0,
                        external_enabled: Number(chartPin.external_enabled ?? 1) > 0,
                    };

                    try {
                        const response = await fetch(
                            '/api/pairing/my-controllers/' + encodeURIComponent(selectedControllerId) + '/pins/' + encodeURIComponent(chartPin.id) + '/settings',
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
                            return;
                        }
                        chartPin = { ...chartPin, ...(data.pin || {}), chart_range_hours: nextRange };
                        const index = currentPins.findIndex((p) => String(p.id) === String(chartPin.id));
                        if (index >= 0) {
                            currentPins[index] = chartPin;
                        }
                        await renderPinChartDialog(chartPin);
                    } catch (_) {
                        // keep current chart as is
                    }
                });
            }
            controllerSettingsForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!editingControllerId) return;
                setControllerSettingsError('');
                controllerSettingsSaveBtn.disabled = true;
                controllerSettingsSaveBtn.textContent = t('saving', 'Saving...');

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
                        setControllerSettingsError(data.message || t('failed_save_settings', 'Failed to save settings.'));
                        return;
                    }

                    closeControllerSettings();
                    await loadControllers();
                    schedulePinsAutoRefresh();
                } catch (_) {
                    setControllerSettingsError(t('failed_save_settings', 'Failed to save settings.'));
                } finally {
                    controllerSettingsSaveBtn.disabled = false;
                    controllerSettingsSaveBtn.textContent = t('save', 'Save');
                }
            });

            controllerSettingsDeleteBtn?.addEventListener('click', () => {
                if (!editingControllerId) return;
                setControllerDeleteError('');
                controllerDeleteDialog?.showModal();
            });
            controllerDeleteCancelBtn?.addEventListener('click', closeControllerDeleteDialog);
            controllerDeleteConfirmBtn?.addEventListener('click', deleteEditingController);
            pinSettingsForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!editingPin || !selectedControllerId) return;

                setPinSettingsError('');
                pinSettingsSaveBtn.disabled = true;
                pinSettingsSaveBtn.textContent = t('saving', 'Saving...');

                const labelEl = pinSettingsForm.querySelector('input[name="label"]');
                const unitEl = pinSettingsForm.querySelector('input[name="unit"]');
                const rangeEl = pinSettingsForm.querySelector('[name="chart_range_hours"]');
                const showOnChartEl = pinSettingsForm.querySelector('input[name="show_on_chart"]');
                const showOnReportEl = pinSettingsForm.querySelector('input[name="show_on_report"]');
                const isMonitoredEl = pinSettingsForm.querySelector('input[name="is_monitored"]');
                const externalEnabledEl = pinSettingsForm.querySelector('input[name="external_enabled"]');
                const moistureRawDryEl = pinSettingsForm.querySelector('input[name="moisture_raw_dry"]');
                const moistureRawWetEl = pinSettingsForm.querySelector('input[name="moisture_raw_wet"]');
                const moistureShowPercentEl = pinSettingsForm.querySelector('input[name="moisture_show_percent"]');
                const payload = {
                    label: String(labelEl?.value || '').trim(),
                    unit: String(unitEl?.value || '').trim() || null,
                    chart_range_hours: Number(rangeEl?.value || 24),
                    show_on_chart: Boolean(showOnChartEl?.checked),
                    show_on_report: showOnReportEl ? Boolean(showOnReportEl.checked) : (Number(editingPin?.show_on_report ?? 1) > 0),
                    is_monitored: Boolean(isMonitoredEl?.checked),
                    external_enabled: externalEnabledEl ? Boolean(externalEnabledEl.checked) : (Number(editingPin?.external_enabled ?? 1) > 0),
                    moisture_raw_dry: moistureRawDryEl ? (moistureRawDryEl.value === '' ? null : Number(moistureRawDryEl.value)) : (editingPin?.moisture_raw_dry ?? null),
                    moisture_raw_wet: moistureRawWetEl ? (moistureRawWetEl.value === '' ? null : Number(moistureRawWetEl.value)) : (editingPin?.moisture_raw_wet ?? null),
                    moisture_show_percent: moistureShowPercentEl ? Boolean(moistureShowPercentEl.checked) : (Number(editingPin?.moisture_show_percent ?? 0) > 0),
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
