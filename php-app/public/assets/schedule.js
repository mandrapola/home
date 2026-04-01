(() => {
  const STORAGE_KEY = 'smart-home-schedule-v1';

  const elList = document.getElementById('scheduleList');
  const elAddBtn = document.getElementById('addScheduleBtn');
  const elDialog = document.getElementById('scheduleDialog');
  const elForm = document.getElementById('scheduleForm');
  const elDialogTitle = document.getElementById('scheduleDialogTitle');
  const elDeleteBtn = document.getElementById('scheduleDeleteBtn');
  const elCancelBtn = document.getElementById('scheduleCancelBtn');

  const state = {
    items: [],
    editingId: null,
  };

  const loadItems = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        state.items = [];
        return;
      }
      const parsed = JSON.parse(raw);
      state.items = Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      state.items = [];
    }
  };

  const saveItems = () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state.items));
  };

  const render = () => {
    elList.innerHTML = '';

    if (!state.items.length) {
      const p = document.createElement('p');
      p.className = 'muted';
      p.textContent = 'Задачи не добавлены';
      elList.appendChild(p);
      return;
    }

    const sorted = [...state.items].sort((a, b) => String(a.time).localeCompare(String(b.time)));
    for (const item of sorted) {
      const row = document.createElement('div');
      row.className = 'controller';
      row.innerHTML = `
        <strong>${item.time} · ${item.action}</strong><br>
        <span class="muted">${item.days}</span><br>
        <span class="muted">${item.enabled ? 'Включено' : 'Выключено'}</span>
      `;
      row.addEventListener('click', () => openDialog(item));
      elList.appendChild(row);
    }
  };

  const openDialog = (item = null) => {
    state.editingId = item ? String(item.id) : null;
    elDialogTitle.textContent = item ? 'Редактирование задачи' : 'Новая задача';
    elDeleteBtn.classList.toggle('hidden', !item);

    elForm.time.value = item?.time || '08:00';
    elForm.days.value = item?.days || 'Пн,Вт,Ср,Чт,Пт';
    elForm.action.value = item?.action || '';
    elForm.enabled.checked = Boolean(item?.enabled ?? true);

    elDialog.showModal();
  };

  const closeDialog = () => {
    if (elDialog.open) {
      elDialog.close();
    }
  };

  elAddBtn.addEventListener('click', () => openDialog(null));
  elCancelBtn.addEventListener('click', closeDialog);

  elDeleteBtn.addEventListener('click', () => {
    if (!state.editingId) return;
    state.items = state.items.filter((item) => String(item.id) !== state.editingId);
    saveItems();
    render();
    closeDialog();
  });

  elForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const payload = {
      id: state.editingId || String(Date.now()),
      time: String(elForm.time.value || '00:00'),
      days: String(elForm.days.value || '').trim(),
      action: String(elForm.action.value || '').trim(),
      enabled: Boolean(elForm.enabled.checked),
    };

    if (!payload.days || !payload.action) {
      return;
    }

    const idx = state.items.findIndex((item) => String(item.id) === String(payload.id));
    if (idx >= 0) {
      state.items[idx] = payload;
    } else {
      state.items.push(payload);
    }

    saveItems();
    render();
    closeDialog();
  });

  loadItems();
  render();
})();
