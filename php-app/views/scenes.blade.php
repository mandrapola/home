<section class="head">
  <h1>Сценарии</h1>
  <p>Управление правилами установки состояний цифровых пинов для всех контроллеров</p>
</section>

<section class="panel">
  <div class="row">
    <h3>Список сценариев</h3>
    <div class="toolbar-actions">
      <button id="newScenarioBtn" class="switch">+ Новый сценарий</button>
      <span id="scenesRefreshInfo" class="muted">...</span>
    </div>
  </div>
  <p id="scenesError" class="error"></p>
  <div id="scenesList" class="controllers"></div>
</section>

<dialog id="scenarioDialog" class="modal-dialog modal-dialog--md">
  <form method="dialog" id="scenarioForm" class="modal-form modal-form--double">
    <h3 class="modal-title modal-title--full" id="scenarioDialogTitle">Новый сценарий</h3>

    <label>Название<br><input name="name" required class="form-full"></label>
    <label>Приоритет<br><input name="priority" type="number" value="100" class="form-full"></label>

    <label>Источник (пин)<br><select name="source_pin" id="sourcePinSelect" class="form-full"></select></label>
    <label>Оператор<br>
      <select name="operator" class="form-full">
        <option value="gt">&gt;</option>
        <option value="gte">&gt;=</option>
        <option value="lt">&lt;</option>
        <option value="lte">&lt;=</option>
      </select>
    </label>

    <label>Порог<br><input name="threshold" type="number" step="0.01" value="0" class="form-full"></label>
    <label>Гистерезис<br><input name="hysteresis" type="number" step="0.01" value="0" class="form-full"></label>

    <label>Целевой пин<br><select name="target_pin" id="targetPinSelect" class="form-full"></select></label>
    <label>Включен<br><input type="checkbox" name="enabled" checked></label>

    <label>Значение при TRUE<br><select name="value_when_true"><option value="1">1</option><option value="0">0</option></select></label>
    <label>Значение при FALSE<br><select name="value_when_false"><option value="0">0</option><option value="1">1</option></select></label>

    <div class="modal-actions modal-actions--full">
      <button type="button" id="deleteScenarioBtn" class="switch hidden">Удалить</button>
      <button type="button" id="cancelScenarioBtn" class="switch">Закрыть</button>
      <button type="submit" class="switch">Сохранить</button>
    </div>
  </form>
</dialog>
