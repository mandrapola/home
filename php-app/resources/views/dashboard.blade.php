<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Дашбоард') }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('assets/dashboard.css') }}">

    <div class="row g-3 mb-2">
        <div class="col-12 d-flex justify-content-end">
            <a href="{{ route('adding-a-new-controller') }}" class="btn btn-primary">Добавить новый контроллер</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">Контроллеры</h3>
                        <button id="refresh-controllers-btn" class="btn btn-outline-secondary btn-sm">Обновить</button>
                    </div>
                    <div id="controllers-empty" class="text-muted small d-none">Нет привязанных контроллеров.</div>
                    <div id="controllers-list" class="d-grid gap-2"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 id="pins-title" class="h6 mb-0">Пины контроллера</h3>
                        <button id="refresh-pins-btn" class="btn btn-outline-secondary btn-sm" disabled>Обновить</button>
                    </div>
                    <div id="pins-message" class="text-muted small mb-2">Выберите контроллер слева.</div>
                    <div id="pins-list" class="row g-2"></div>
                </div>
            </div>
        </div>
    </div>

    <dialog id="controllerSettingsDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="controllerSettingsForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="controllerSettingsTitle">Настройки контроллера</h3>

            <label>
                Имя контроллера<br>
                <input name="name" required class="form-full form-control">
            </label>

            <label>
                Описание<br>
                <input name="discription" class="form-full form-control">
            </label>

            <label>
                Интервал отправки, сек<br>
                <input name="send_interval_seconds" type="number" min="1" step="1" class="form-full form-control">
            </label>

            <p id="controllerSettingsError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="controllerSettingsCancelBtn" class="switch btn btn-outline-secondary">Закрыть</button>
                <button type="submit" id="controllerSettingsSaveBtn" class="switch btn btn-primary">Сохранить</button>
            </div>
        </form>
    </dialog>

    <dialog id="pinSettingsDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="pinSettingsForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="pinSettingsTitle">Настройки пина</h3>

            <label>
                Название<br>
                <input name="label" required class="form-full form-control">
            </label>

            <label data-field-group="analog">
                Единица<br>
                <input name="unit" class="form-full form-control">
            </label>

            <label data-field-group="analog">
                Среднее за, мин<br>
                <input name="average_interval_minutes" type="number" min="1" step="1" class="form-full form-control">
            </label>

            <label data-field-group="analog">
                Диапазон графика, ч<br>
                <input name="chart_range_hours" type="number" min="1" step="1" class="form-full form-control">
            </label>

            <label data-field-group="digital">
                Таймер auto-off, сек<br>
                <input name="power_on_duration_seconds" type="time" step="1" class="form-full form-control">
            </label>

            <label class="checkbox-row" data-field-group="digital">
                <input name="invert_digital_logic" type="checkbox" class="form-check-input">
                Инверсия логики
            </label>

            <label class="checkbox-row" data-field-group="analog">
                <input name="show_on_chart" type="checkbox" class="form-check-input">
                Показывать на графике
            </label>

            <p id="pinSettingsError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="pinSettingsCancelBtn" class="switch btn btn-outline-secondary">Закрыть</button>
                <button type="submit" id="pinSettingsSaveBtn" class="switch btn btn-primary">Сохранить</button>
            </div>
        </form>
    </dialog>

    <script src="{{ asset('assets/dashboard.js') }}"></script>
</x-app-layout>
