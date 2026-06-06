<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Dashboard') }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('assets/dashboard.css') }}">
    <style>
        .db-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .db-plan-card .card-body {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            font-size: 13px;
        }
        .db-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--chip-bg);
            color: var(--muted);
            white-space: nowrap;
        }
        .db-chip b {
            color: var(--text);
            font-weight: 600;
        }
        .db-section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.01em;
        }
        .db-subtle {
            color: var(--muted);
            font-size: 13px;
            margin: 0;
        }
        .db-card-head {
            border-bottom: 1px solid var(--line);
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        #controllers-list > .border {
            background: linear-gradient(180deg, var(--card) 0%, var(--chip-bg) 100%);
            border-color: #d9e4f2 !important;
            box-shadow: 0 4px 12px rgba(17, 34, 68, 0.06);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        #controllers-list > .border:hover {
            border-color: #b8cbe6 !important;
            box-shadow: 0 8px 20px rgba(17, 34, 68, 0.10);
            transform: translateY(-1px);
        }
        #controllers-list > .border.border-primary {
            background: linear-gradient(180deg, var(--chip-bg) 0%, rgba(79, 140, 255, 0.14) 100%);
            border-color: #8cb1df !important;
            box-shadow: 0 10px 22px rgba(31, 122, 255, 0.16);
        }
        #pins-list article.border {
            background: linear-gradient(180deg, var(--card) 0%, var(--chip-bg) 100%);
            border-color: #d9e4f2 !important;
            box-shadow: 0 4px 12px rgba(17, 34, 68, 0.06);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        #pins-list article.border:hover {
            border-color: #b8cbe6 !important;
            box-shadow: 0 8px 20px rgba(17, 34, 68, 0.10);
            transform: translateY(-1px);
        }

        /* Light overrides for old dark chart blocks */
        .app-dialog {
            background: var(--card) !important;
            color: var(--text) !important;
        }
        .pin-chart,
        .power-events-body,
        .power-gantt-scroll,
        .power-gantt-header .label-col,
        .power-gantt-label {
            background: var(--chip-bg) !important;
            color: var(--text) !important;
            border-color: var(--line) !important;
        }
        .power-gantt-hour,
        .pin-chart-legend {
            color: var(--muted) !important;
        }
        .power-gantt-lane .hour-line,
        .power-gantt-hour {
            border-left-color: rgba(10, 10, 10, 0.08) !important;
        }
        .power-gantt-lane--fact {
            border-bottom-color: rgba(10, 10, 10, 0.08) !important;
        }
    </style>

    @php
        $dashboardUser = auth()->user();
        $selectedPlan = $dashboardUser?->selectedPlan;
        $effectivePlan = $dashboardUser ? app(\App\Services\Billing\PlanLimitService::class)->resolveEffectivePlanForUser($dashboardUser) : null;
        $activeSubRow = $dashboardUser ? \Illuminate\Support\Facades\DB::table('user_subscriptions')
            ->where('user_id', $dashboardUser->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('id')
            ->first(['status', 'ends_at']) : null;
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card shadow-sm db-plan-card">
                <div class="card-body">
                    <span class="db-chip"><b>{{ __('Plan') }}:</b> {{ $selectedPlan?->name ?? __('not selected') }}</span>
                    @if ($activeSubRow)
                        <span class="db-chip"><b>{{ __('Status') }}:</b> {{ $activeSubRow->status }}</span>
                    @endif
                    @if ($effectivePlan)
                        <span class="db-chip"><b>{{ __('Report quota') }}:</b> {{ (int) ($effectivePlan->report_max_requests_per_epoch ?? 0) > 0 ? number_format((int) $effectivePlan->report_max_requests_per_epoch, 0, '.', ' ') : __('Auto') }} / {{ (int) ($effectivePlan->report_epoch_seconds ?? 300) }} {{ __('sec') }}</span>
                        <span class="db-chip"><b>{{ __('pin_data limit') }}:</b> {{ (int) ($effectivePlan->max_pin_data_rows ?? 0) > 0 ? number_format((int) $effectivePlan->max_pin_data_rows, 0, '.', ' ') : __('No limit') }}</span>
                        <span class="db-chip"><b>{{ __('Scenarios') }}:</b> {{ (int) ($effectivePlan->max_scenarios ?? 0) > 0 ? number_format((int) $effectivePlan->max_scenarios, 0, '.', ' ') : __('No limit') }}</span>
                        <span class="db-chip"><b>{{ __('Scenario Conditions') }}:</b> {{ (int) ($effectivePlan->max_scenario_conditions ?? 0) > 0 ? number_format((int) $effectivePlan->max_scenario_conditions, 0, '.', ' ') : __('No limit') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-2">
        <div class="col-12">
            <div class="db-toolbar">
                <p class="db-subtle">{{ __('Manage controllers, pin states, settings and charts from a single dashboard.') }}</p>
                <a href="{{ route('adding-a-new-controller') }}" class="btn btn-primary">{{ __('Add New Controller') }}</a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="db-card-head d-flex justify-content-between align-items-center">
                        <h3 class="db-section-title">{{ __('Controllers') }}</h3>
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
                    <div class="db-card-head d-flex justify-content-between align-items-center">
                        <h3 id="pins-title" class="db-section-title">{{ __('Controller Pins') }}</h3>
                        <div class="d-flex align-items-center gap-2">
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
                <input name="send_interval_seconds" type="number" min="{{ $minimumSendIntervalSeconds }}" max="{{ \App\Models\IoTController::MAX_INTERVAL_SECONDS }}" step="1" class="form-full form-control">
                <span class="text-muted small">{{ __('Minimum send interval: :seconds sec.', ['seconds' => $minimumSendIntervalSeconds]) }}</span>
            </label>

            <p id="controllerSettingsError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="controllerSettingsDeleteBtn" class="switch btn btn-outline-danger me-auto">{{ __('Delete Controller') }}</button>
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

    <dialog id="controllerDeleteDialog" class="app-dialog app-dialog--sm">
        <form method="dialog" class="modal-form modal-form--single">
            <h3 class="modal-title">{{ __('Delete Controller') }}</h3>
            <p class="text-muted mb-0">{{ __('Deleting the controller will delete all telemetry history and related scenarios. Continue?') }}</p>
            <p id="controllerDeleteError" class="error modal-error"></p>

            <div class="modal-actions">
                <button type="button" id="controllerDeleteCancelBtn" class="switch btn btn-outline-secondary">{{ __('Cancel') }}</button>
                <button type="button" id="controllerDeleteConfirmBtn" class="switch btn btn-danger">{{ __('Delete') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="pinChartDialog" class="app-dialog app-dialog--lg">
        <form method="dialog" id="pinChartForm" class="modal-form modal-form--single">
            <h3 class="modal-title" id="pinChartTitle">{{ __('Pin Chart') }}</h3>
            <div class="pin-chart-toolbar">
                <span class="small text-muted mb-0">{{ __('Range') }}:</span>
                <div id="pinChartRangeButtons" class="btn-group btn-group-sm pin-chart-range-group" role="group" aria-label="{{ __('Chart range') }}">
                    <button type="button" class="btn btn-outline-info" data-range-hours="1">1 {{ __('h') }}</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="4">4 {{ __('h') }}</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="8">8 {{ __('h') }}</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="16">16 {{ __('h') }}</button>
                    <button type="button" class="btn btn-outline-info" data-range-hours="24">24 {{ __('h') }}</button>
                </div>
            </div>
            <div id="pinChartBody"></div>
            <div class="modal-actions">
                <button type="submit" id="pinChartCloseBtn" class="switch btn btn-outline-secondary">{{ __('Close') }}</button>
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
            'deleting' => __('Deleting...'),
            'failed_change_pin_state' => __('Failed to change pin state.'),
            'failed_change_pin_scenarios' => __('Failed to change scenario state for pin.'),
            'failed_save_settings' => __('Failed to save settings.'),
            'failed_delete_controller' => __('Failed to delete controller.'),
            'delete_controller_confirm' => __('Deleting the controller will delete all telemetry history and related scenarios. Continue?'),
            'controller_deleted' => __('Controller deleted.'),
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
            'delete' => __('Delete'),
            'min' => __('min'),
            'max' => __('max'),
            'current' => __('current'),
            'unit_labels' => [
                'celsius' => __('celsius'),
                'fahrenheit' => __('fahrenheit'),
                'percent' => __('percent'),
                'adc' => __('adc'),
                'kpa' => __('kpa'),
                'bar' => __('bar'),
                'ppm' => __('ppm'),
                'lux' => __('lux'),
                '°c' => __('celsius'),
                '°f' => __('fahrenheit'),
                '%' => __('percent'),
            ],
        ];
    @endphp
    <script>
        window.dashboardI18n = @json($dashboardI18n);
    </script>
    <script src="{{ asset('assets/dashboard.js') }}"></script>
</x-app-layout>
