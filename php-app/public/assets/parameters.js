(() => {
  const AUTO_REFRESH_MS = 5000;

  const state = {
    controllers: [],
    selectedControllerId: null,
    timer: null,
  };

  const elSelect = document.getElementById('parametersControllerSelect');
  const elUpdatedAt = document.getElementById('parametersUpdatedAt');
  const elError = document.getElementById('parametersError');
  const elEmpty = document.getElementById('parametersEmpty');
  const elWrap = document.getElementById('parametersTableWrap');
  const elBody = document.getElementById('parametersTableBody');

  const setError = (message) => { elError.textContent = message || ''; };

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

  const formatValue = (parameter) => {
    const key = String(parameter?.key || '');
    const value = Number(parameter?.value || 0);

    if (key.includes(':current_time') || key.endsWith(':current_time') || key === 'current_time') {
      const seconds = Math.max(0, Math.trunc(value)) % 86400;
      const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
      const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
      const s = String(seconds % 60).padStart(2, '0');
      return `${h}:${m}:${s}`;
    }

    if (key.includes(':pin_state:') || key.startsWith('pin_state:')) {
      return value > 0 ? 'Вкл' : 'Выкл';
    }

    if (parameter?.unit) {
      return Number.isInteger(value) ? String(value) : value.toFixed(1);
    }

    return String(Math.round(value));
  };

  const renderControllers = () => {
    elSelect.innerHTML = '';
    for (const controller of state.controllers) {
      const option = document.createElement('option');
      option.value = String(controller.id);
      option.textContent = `${controller.name} (ID ${controller.id})`;
      if (controller.id === state.selectedControllerId) {
        option.selected = true;
      }
      elSelect.appendChild(option);
    }
  };

  const renderParameters = (parameters) => {
    elBody.innerHTML = '';

    if (!Array.isArray(parameters) || parameters.length === 0) {
      elWrap.classList.add('hidden');
      elEmpty.classList.remove('hidden');
      return;
    }

    elWrap.classList.remove('hidden');
    elEmpty.classList.add('hidden');

    for (const parameter of parameters) {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${parameter.label}</td>
        <td><code>${parameter.key}</code></td>
        <td><strong>${formatValue(parameter)}</strong>${parameter.unit ? ` <span class="muted">${parameter.unit}</span>` : ''}</td>
      `;
      elBody.appendChild(tr);
    }
  };

  const loadControllers = async () => {
    const data = await fetchJson('/api/controllers');
    state.controllers = Array.isArray(data?.controllers) ? data.controllers : [];

    const hasSelected = state.controllers.some((c) => c.id === state.selectedControllerId);
    if (!hasSelected) {
      state.selectedControllerId = state.controllers[0]?.id || null;
    }

    renderControllers();
  };

  const loadParameters = async () => {
    if (!state.selectedControllerId) {
      renderParameters([]);
      return;
    }

    const data = await fetchJson(`/api/controllers/${state.selectedControllerId}/parameters`);
    renderParameters(Array.isArray(data?.parameters) ? data.parameters : []);

    if (data?.updated_at) {
      const date = new Date(String(data.updated_at));
      elUpdatedAt.textContent = `Обновлено: ${Number.isNaN(date.getTime()) ? data.updated_at : date.toLocaleString('ru-RU')}`;
    }
  };

  const reloadAll = async () => {
    try {
      setError('');
      await loadControllers();
      await loadParameters();
    } catch (error) {
      setError(`Ошибка загрузки: ${error.message}`);
    }
  };

  elSelect.addEventListener('change', async () => {
    state.selectedControllerId = Number(elSelect.value) || null;
    await loadParameters();
  });

  const start = async () => {
    await reloadAll();
    if (state.timer) {
      clearInterval(state.timer);
    }
    state.timer = setInterval(() => {
      loadParameters();
    }, AUTO_REFRESH_MS);
  };

  start();
})();
