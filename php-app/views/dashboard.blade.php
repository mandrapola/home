<section class="head">
  <h1>Smart Home Dashboard</h1>
  <p>PHP + MySQL + Blade + JS polling</p>
</section>

<section class="layout">
  <aside class="panel">
    <h3>Контроллеры</h3>
    <div id="controllers" class="controllers"></div>
  </aside>

  <section class="panel">
    <div class="row">
      <h3 id="currentController">Контроллер не выбран</h3>
      <span id="refreshInfo" class="muted">Обновление...</span>
    </div>
    <p id="message" class="muted">Выберите контроллер слева.</p>
    <p id="error" class="error"></p>
    <div id="cards" class="cards"></div>

    <div class="section-spacer">
      <div class="row">
        <h3 class="section-title">История измерений</h3>
        <div id="historyRangeControls" class="history-range-controls">
          <button type="button" class="switch active" data-range-hours="1">1ч</button>
          <button type="button" class="switch" data-range-hours="6">6ч</button>
          <button type="button" class="switch" data-range-hours="24">24ч</button>
        </div>
      </div>
      <p id="historyMessage" class="muted">Нет данных для построения графиков.</p>
      <div id="historyCharts" class="charts-grid"></div>
    </div>
  </section>
</section>

<dialog id="controllerSettingsDialog" class="modal-dialog modal-dialog--sm">
  <form method="dialog" id="controllerSettingsForm" class="modal-form modal-form--single">
    <h3 class="modal-title" id="controllerSettingsTitle">Настройки контроллера</h3>

    <label>
      Имя контроллера<br>
      <input name="name" required class="form-full">
    </label>

    <label>
      Описание<br>
      <input name="discription" class="form-full">
    </label>

    <label>
      Интервал отправки, сек<br>
      <input name="send_interval_seconds" type="number" min="1" step="1" class="form-full">
    </label>

    <p id="controllerSettingsError" class="error modal-error"></p>

    <div class="modal-actions">
      <button type="button" id="controllerSettingsCancelBtn" class="switch">Закрыть</button>
      <button type="submit" id="controllerSettingsSaveBtn" class="switch">Сохранить</button>
    </div>
  </form>
</dialog>

<dialog id="pinSettingsDialog" class="modal-dialog modal-dialog--md">
  <form method="dialog" id="pinSettingsForm" class="modal-form modal-form--double">
    <h3 class="modal-title modal-title--full" id="pinSettingsTitle">Настройки пина</h3>

    <label class="field-full">
      Отображаемое имя<br>
      <input name="label" required class="form-full">
    </label>

    <label>
      Порядок<br>
      <input name="sort_order" type="number" step="1" class="form-full">
    </label>

    <label>
      Стиль цифрового пина<br>
      <select name="digital_style" class="form-full">
        <option value="power">Питание</option>
        <option value="sensor">Датчик</option>
        <option value="access">Доступ</option>
        <option value="security">Охрана</option>
        <option value="signal">Сигнал</option>
      </select>
    </label>

    <label>
      Единица<br>
      <input name="unit" class="form-full">
    </label>

    <label>
      Множитель<br>
      <input name="multiplier" type="number" step="0.01" class="form-full">
    </label>

    <label>
      Смещение<br>
      <input name="offset" type="number" step="0.01" class="form-full">
    </label>

    <label>
      Точность<br>
      <input name="precision" type="number" min="0" step="1" class="form-full">
    </label>

    <label>
      Среднее за, мин<br>
      <input name="average_interval_minutes" type="number" min="1" step="1" class="form-full">
    </label>

    <label>
      Диапазон графика, ч<br>
      <input name="chart_range_hours" type="number" min="1" step="1" class="form-full">
    </label>

    <label>
      Таймер auto-off, сек<br>
      <input name="power_on_duration_seconds" type="number" min="0" step="1" class="form-full">
    </label>

    <label>
      Текст при 0<br>
      <input name="digital_off_text" class="form-full">
    </label>

    <label>
      Текст при 1<br>
      <input name="digital_on_text" class="form-full">
    </label>

    <label class="checkbox-row">
      <input name="invert_digital_logic" type="checkbox">
      Инверсия логики
    </label>

    <label class="checkbox-row">
      <input name="show_on_dashboard" type="checkbox">
      Показывать на дашборде
    </label>

    <label class="checkbox-row">
      <input name="show_on_chart" type="checkbox">
      Показывать на графике
    </label>

    <p id="pinSettingsError" class="error modal-error modal-error--full"></p>

    <div class="modal-actions modal-actions--full">
      <button type="button" id="pinSettingsCancelBtn" class="switch">Закрыть</button>
      <button type="submit" id="pinSettingsSaveBtn" class="switch">Сохранить</button>
    </div>
  </form>
</dialog>
