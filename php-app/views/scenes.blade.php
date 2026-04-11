<section class="head">
  <h1>Сценарии</h1>
  <p>Сценарий — это группа условий, управляющих целевым цифровым пином</p>
</section>

<section class="panel card shadow-sm">
  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <h3>Список сценариев</h3>
    <div class="toolbar-actions">
      <button id="newScenarioBtn" class="switch btn btn-primary btn-sm">+ Новое условие</button>
      <span id="scenesRefreshInfo" class="muted">...</span>
    </div>
  </div>
  <p id="scenesError" class="error"></p>
  <div id="scenesList" class="controllers"></div>
</section>

<dialog id="scenarioDialog" class="app-dialog app-dialog--md">
  <form method="dialog" id="scenarioForm" class="modal-form modal-form--double">
    <h3 class="modal-title modal-title--full" id="scenarioDialogTitle">Новое условие</h3>

    <label class="field-full">Название<br><input name="name" required class="form-full form-control"></label>

    <label>Источник (пин)<br><select name="source_pin" id="sourcePinSelect" class="form-full form-select"></select></label>
    <label id="operatorFieldLabel">Условие<br>
      <select name="operator" id="operatorSelect" class="form-full form-select">
        <option value="gt">&gt;</option>
        <option value="gte">&gt;=</option>
        <option value="lt">&lt;</option>
        <option value="lte">&lt;=</option>
      </select>
    </label>

    <label id="thresholdLabel">Порог<br><input name="threshold" type="number" step="0.01" value="0" class="form-full form-control"></label>

    <label class="field-full">Целевой пин<br><select name="target_pin" id="targetPinSelect" class="form-full form-select"></select></label>

    <div class="modal-actions modal-actions--full">
      <button type="button" id="deleteScenarioBtn" class="switch btn btn-outline-danger hidden">Удалить</button>
      <button type="button" id="cancelScenarioBtn" class="switch btn btn-outline-secondary">Закрыть</button>
      <button type="submit" class="switch btn btn-primary">Сохранить</button>
    </div>
  </form>
</dialog>
