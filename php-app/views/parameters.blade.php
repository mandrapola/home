<section class="head">
  <h1>Параметры сценариев</h1>
  <p>Расчетные параметры для условий автоматизации</p>
</section>

<section class="panel">
  <div class="toolbar-actions-end">
    <label>
      Контроллер
      <select id="parametersControllerSelect" class="input-control select-min-260"></select>
    </label>
    <span id="parametersUpdatedAt" class="muted">Обновлено: нет данных</span>
  </div>

  <p id="parametersError" class="error"></p>
  <p id="parametersEmpty" class="muted">Параметры пока недоступны</p>

  <div id="parametersTableWrap" class="table-wrap hidden">
    <table class="data-table">
      <thead>
        <tr>
          <th>Параметр</th>
          <th>Ключ</th>
          <th>Значение</th>
        </tr>
      </thead>
      <tbody id="parametersTableBody"></tbody>
    </table>
  </div>
</section>
