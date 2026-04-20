<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Dashboard') }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('assets/dashboard.css') }}">

    <div class="row g-3 mb-2">
        <div class="col-12 d-flex justify-content-end">
            <a href="{{ route('adding-a-new-controller') }}" class="btn btn-primary">{{ __('Add New Controller') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">{{ __('Controllers') }}</h3>
                        <button id="refresh-controllers-btn" class="btn btn-outline-secondary btn-sm">{{ __('Refresh') }}</button>
                    </div>
                    <div id="controllers-empty" class="text-muted small d-none">{{ __('No linked controllers.') }}</div>
                    <div id="controllers-list" class="d-grid gap-2"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 id="pins-title" class="h6 mb-0">{{ __('Controller Pins') }}</h3>
                        <div class="d-flex align-items-center gap-2">
                            <button id="open-power-events-btn" class="btn btn-outline-info btn-sm" disabled>{{ __('Report') }}</button>
                            <button id="refresh-pins-btn" class="btn btn-outline-secondary btn-sm" disabled>{{ __('Refresh') }}</button>
                        </div>
                    </div>
                    <div id="pins-message" class="text-muted small mb-2">{{ __('Select a controller on the left.') }}</div>
                    <div id="pins-list" class="row g-2"></div>
                </div>
            </div>
        </div>
    </div>

    <dialog id="controllerSettingsDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="controllerSettingsForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="controllerSettingsTitle">{{ __('Controller Settings') }}</h3>

            <label>
                {{ __('Controller Name') }}<br>
                <input name="name" required class="form-full form-control">
            </label>

            <label>
                {{ __('Description') }}<br>
                <input name="discription" class="form-full form-control">
            </label>

            <label>
                {{ __('Send Interval, sec') }}<br>
                <input name="send_interval_seconds" type="number" min="5" step="1" class="form-full form-control">
            </label>

            <p id="controllerSettingsError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="controllerSettingsCancelBtn" class="switch btn btn-outline-secondary">{{ __('Close') }}</button>
                <button type="submit" id="controllerSettingsSaveBtn" class="switch btn btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="pinSettingsDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" id="pinSettingsForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="pinSettingsTitle">{{ __('Pin Settings') }}</h3>
            <div id="pinSettingsFields"></div>

            <p id="pinSettingsError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="pinSettingsCancelBtn" class="switch btn btn-outline-secondary">{{ __('Close') }}</button>
                <button type="submit" id="pinSettingsSaveBtn" class="switch btn btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="pinChartDialog" class="app-dialog app-dialog--lg">
        <form method="dialog" id="pinChartForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="pinChartTitle">{{ __('Pin Chart') }}</h3>
            <div class="pin-chart-toolbar">
                <span class="small text-muted mb-0">{{ __('Range') }}:</span>
                <div id="pinChartRangeButtons" class="btn-group btn-group-sm pin-chart-range-group" role="group" aria-label="Диапазон графика">
                    <button type="button" class="btn btn-outline-info" data-range-hours="1">1 ч</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="4">4 ч</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="8">8 ч</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="16">16 ч</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="24">24 ч</button>
                </div>
            </div>
            <div id="pinChartBody"></div>
            <div class="modal-actions">
                <button type="submit" id="pinChartCloseBtn" class="switch btn btn-outline-secondary">{{ __('Close') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="powerEventsDialog" class="app-dialog app-dialog--lg">
        <form method="dialog" id="powerEventsForm" class="modal-form modal-form--single">
            <h3 class="modal-title">{{ __('Report') }}</h3>
            <div class="d-flex align-items-center justify-content-end gap-2">
                <button type="button" id="powerEventsRefreshBtn" class="btn btn-outline-secondary btn-sm">{{ __('Refresh') }}</button>
            </div>
            <div id="powerEventsBody" class="power-events-body"></div>
            <div class="small text-muted power-events-legend">
                <div class="power-events-legend-title">{{ __('Legend') }}:</div>
                <div><span class="badge text-bg-primary me-1">&nbsp;</span>{{ __('Fact') }}</div>
                <div><span class="badge text-bg-success me-1">&nbsp;</span>{{ __('Plan: time conditions true and all conditions true') }}</div>
                <div><span class="badge text-bg-warning me-1">&nbsp;</span>{{ __('Plan: time conditions true but some other conditions false') }}</div>
                <div><span class="badge text-bg-danger me-1">&nbsp;</span>{{ __('Plan: no time condition and all conditions true now') }}</div>
            </div>
            <div class="modal-actions">
                <button type="submit" id="powerEventsCloseBtn" class="switch btn btn-outline-secondary">{{ __('Close') }}</button>
            </div>
        </form>
    </dialog>

    <template id="pin-card-template-power">@include('dashboard.pin-cards.power')</template>
    <template id="pin-card-template-sensor">@include('dashboard.pin-cards.sensor')</template>
    <template id="pin-card-template-sensor_humidity">@include('dashboard.pin-cards.sensor_humidity')</template>
    <template id="pin-card-template-sensor_light">@include('dashboard.pin-cards.sensor_light')</template>
    <template id="pin-card-template-sensor_level">@include('dashboard.pin-cards.sensor_level')</template>
    <template id="pin-card-template-sensor_pressure">@include('dashboard.pin-cards.sensor_pressure')</template>
    <template id="pin-card-template-sensor_temperature">@include('dashboard.pin-cards.sensor_temperature')</template>

    <template id="pin-form-template-power">@include('dashboard.pin-forms.power_form')</template>
    <template id="pin-form-template-sensor">@include('dashboard.pin-forms.sensor_form')</template>
    <template id="pin-form-template-sensor_humidity">@include('dashboard.pin-forms.sensor_humidity_form')</template>
    <template id="pin-form-template-sensor_light">@include('dashboard.pin-forms.sensor_light_form')</template>
    <template id="pin-form-template-sensor_level">@include('dashboard.pin-forms.sensor_level_form')</template>
    <template id="pin-form-template-sensor_pressure">@include('dashboard.pin-forms.sensor_pressure_form')</template>
    <template id="pin-form-template-sensor_temperature">@include('dashboard.pin-forms.sensor_temperature_form')</template>

    <template id="pin-chart-template-sensor">@include('dashboard.pin-charts.sensor_chart')</template>
    <template id="pin-chart-template-sensor_humidity">@include('dashboard.pin-charts.sensor_humidity_chart')</template>
    <template id="pin-chart-template-sensor_light">@include('dashboard.pin-charts.sensor_light_chart')</template>
    <template id="pin-chart-template-sensor_level">@include('dashboard.pin-charts.sensor_level_chart')</template>
    <template id="pin-chart-template-sensor_pressure">@include('dashboard.pin-charts.sensor_pressure_chart')</template>
    <template id="pin-chart-template-sensor_temperature">@include('dashboard.pin-charts.sensor_temperature_chart')</template>

    @php
        $dashboardI18n = [
            'controller' => __('Controller'),
            'settings' => __('Settings'),
            'on' => __('On'),
            'off' => __('Off'),
            'scenarios' => __('Scenarios'),
            'pins_controller' => __('Controller Pins'),
            'select_controller_left' => __('Select a controller on the left.'),
            'loading' => __('Loading...'),
            'pins_not_found' => __('No pins found for selected controller.'),
            'save' => __('Save'),
            'saving' => __('Saving...'),
            'failed_change_pin_state' => __('Failed to change pin state.'),
            'failed_change_pin_scenarios' => __('Failed to change scenario state for pin.'),
            'failed_save_settings' => __('Failed to save settings.'),
            'failed_save_pin_settings' => __('Failed to save pin settings.'),
            'failed_load_pins' => __('Failed to load pins.'),
            'chart_open' => __('Open Chart'),
            'pin_settings' => __('Pin Settings'),
            'chart' => __('Chart'),
            'without_unit' => __('without unit'),
            'chart_loading' => __('Loading chart...'),
            'chart_failed' => __('Failed to load chart.'),
            'chart_no_data_range' => __('No chart data for selected range.'),
            'chart_no_valid_data' => __('No valid chart data.'),
            'min' => __('min'),
            'max' => __('max'),
            'current' => __('current'),
            'power_events' => __('Report'),
            'power_events_loading' => __('Loading...'),
            'power_events_empty' => __('No power events for current day.'),
            'started' => __('Started'),
            'ended' => __('Ended'),
            'duration' => __('Duration'),
            'source' => __('Source'),
            'pin' => __('Pin'),
            'fact' => __('Fact'),
            'plan' => __('Plan'),
        ];
    @endphp
    <script>
        window.dashboardI18n = @json($dashboardI18n);
    </script>
    <script src="{{ asset('assets/dashboard.js') }}"></script>
</x-app-layout>
