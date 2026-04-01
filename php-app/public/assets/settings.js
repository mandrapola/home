(() => {
  const COMMON_TIME_ZONES = [
    'Europe/Moscow',
    'UTC',
    'Europe/Berlin',
    'Europe/London',
    'Asia/Almaty',
    'Asia/Yekaterinburg',
    'Asia/Novosibirsk',
    'Asia/Vladivostok',
    'America/New_York',
    'America/Los_Angeles'
  ];

  const elSelect = document.getElementById('timezoneSelect');
  const elCustom = document.getElementById('timezoneCustom');
  const elSaveBtn = document.getElementById('timezoneSaveBtn');
  const elStatus = document.getElementById('timezoneStatus');
  const elError = document.getElementById('timezoneError');

  const setStatus = (text) => { elStatus.textContent = text || ''; };
  const setError = (text) => { elError.textContent = text || ''; };

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

  const activeTimeZone = () => {
    const custom = String(elCustom.value || '').trim();
    return custom || String(elSelect.value || 'Europe/Moscow');
  };

  const fillSelect = () => {
    elSelect.innerHTML = '';
    for (const zone of COMMON_TIME_ZONES) {
      const option = document.createElement('option');
      option.value = zone;
      option.textContent = zone;
      elSelect.appendChild(option);
    }
  };

  const loadTimeZone = async () => {
    setStatus('');
    setError('');

    try {
      const data = await fetchJson('/api/settings/timezone');
      const tz = String(data?.time_zone || 'Europe/Moscow');
      if (COMMON_TIME_ZONES.includes(tz)) {
        elSelect.value = tz;
        elCustom.value = '';
      } else {
        elSelect.value = 'Europe/Moscow';
        elCustom.value = tz;
      }
      setStatus(`Текущая таймзона: ${tz}`);
    } catch (error) {
      setError(`Не удалось загрузить таймзону: ${error.message}`);
    }
  };

  const saveTimeZone = async () => {
    const tz = activeTimeZone();
    setStatus('');
    setError('');

    try {
      const data = await fetchJson('/api/settings/timezone', {
        method: 'PUT',
        body: JSON.stringify({ time_zone: tz }),
      });

      const saved = String(data?.time_zone || tz);
      if (COMMON_TIME_ZONES.includes(saved)) {
        elSelect.value = saved;
        elCustom.value = '';
      } else {
        elCustom.value = saved;
      }

      setStatus(`Таймзона сохранена: ${saved}`);
    } catch (error) {
      setError(`Не удалось сохранить таймзону: ${error.message}`);
    }
  };

  elSaveBtn.addEventListener('click', saveTimeZone);

  fillSelect();
  loadTimeZone();
})();
