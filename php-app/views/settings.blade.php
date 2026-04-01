<section class="head">
  <h1>Настройки системы</h1>
  <p>Глобальная таймзона для расчета параметров и сценариев</p>
</section>

<section class="panel panel--narrow">
  <div class="form-grid-two">
    <label>
      Таймзона
      <select id="timezoneSelect" class="input-control"></select>
    </label>
    <label>
      Или ввести вручную (IANA)
      <input id="timezoneCustom" type="text" placeholder="Asia/Tokyo" class="input-control" />
    </label>
  </div>

  <div class="modal-actions section-spacer">
    <button id="timezoneSaveBtn" class="switch">Сохранить таймзону</button>
  </div>

  <p id="timezoneStatus" class="muted"></p>
  <p id="timezoneError" class="error"></p>
</section>
