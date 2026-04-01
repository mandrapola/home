<section class="head">
  <h1>Расписание</h1>
  <p>Локальный планировщик задач (клиентский режим)</p>
</section>

<section class="panel panel--wide">
  <div class="row">
    <h3 class="section-title">Список задач</h3>
    <button id="addScheduleBtn" class="switch">+ Добавить</button>
  </div>

  <p class="muted">Эти задачи хранятся в браузере (localStorage) и не влияют на серверные сценарии.</p>
  <div id="scheduleList" class="controllers"></div>
</section>

<dialog id="scheduleDialog" class="modal-dialog modal-dialog--sm">
  <form method="dialog" id="scheduleForm" class="modal-form modal-form--single">
    <h3 class="modal-title" id="scheduleDialogTitle">Новая задача</h3>

    <label>Время<br><input name="time" type="time" required class="input-control"></label>
    <label>Дни<br><input name="days" type="text" placeholder="Пн,Вт,Ср" required class="input-control"></label>
    <label>Действие<br><input name="action" type="text" placeholder="Проветривание" required class="input-control"></label>
    <label><input name="enabled" type="checkbox" checked> Включено</label>

    <div class="modal-actions">
      <button type="button" id="scheduleDeleteBtn" class="switch hidden">Удалить</button>
      <button type="button" id="scheduleCancelBtn" class="switch">Закрыть</button>
      <button type="submit" class="switch">Сохранить</button>
    </div>
  </form>
</dialog>
