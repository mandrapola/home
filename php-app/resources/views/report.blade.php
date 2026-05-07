<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Report') }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('assets/report.css') }}">
    <style>
        .report-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(17,34,68,.06);
        }
        .report-title {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        #reportBody.power-events-body {
            background: #f8fbff !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
        }
        .power-events-legend {
            color: var(--muted) !important;
        }
        .power-events-legend .badge.text-bg-primary {
            background: #dbeafe !important;
            color: #1e40af !important;
            border: 1px solid #bfd8ff;
        }
        .power-events-legend .badge.text-bg-success {
            background: #eafaf1 !important;
            color: #1a7f4b !important;
            border: 1px solid #ccead9;
        }
        .power-events-legend .badge.text-bg-warning {
            background: #fff2cd !important;
            color: #8a6d1f !important;
            border: 1px solid #f2dca2;
        }
        .power-events-legend .badge.text-bg-danger {
            background: #ffe8eb !important;
            color: #9d2d3f !important;
            border: 1px solid #f7c2ca;
        }
        .power-gantt-scroll,
        .power-gantt-header .label-col,
        .power-gantt-label {
            background: #f8fbff !important;
            border-color: var(--line) !important;
            color: var(--text) !important;
        }
        .power-gantt-hour,
        .power-gantt-hover-label {
            color: var(--muted) !important;
        }
        .power-gantt-hour,
        .power-gantt-lane .hour-line {
            border-left-color: rgba(10,10,10,.08) !important;
        }
        .power-gantt-lane--fact {
            border-bottom-color: rgba(10,10,10,.08) !important;
        }
        .power-gantt-hover-label {
            background: #fff !important;
            border-color: var(--line) !important;
        }
    </style>

    <div class="report-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="report-title mb-0">{{ __('Report') }}</h3>
                <button type="button" id="reportRefreshBtn" class="btn btn-outline-secondary btn-sm">{{ __('Refresh') }}</button>
            </div>

            <div id="reportBody" class="power-events-body">{{ __('Loading...') }}</div>

            <div class="small text-muted power-events-legend mt-2">
                <div class="power-events-legend-title">{{ __('Legend') }}:</div>
                <div><span class="badge text-bg-primary me-1">&nbsp;</span>{{ __('Fact') }}</div>
                <div><span class="badge text-bg-success me-1">&nbsp;</span>{{ __('Plan: time conditions true and all conditions true') }}</div>
                <div><span class="badge text-bg-warning me-1">&nbsp;</span>{{ __('Plan: time conditions true but some other conditions false') }}</div>
                <div><span class="badge text-bg-danger me-1">&nbsp;</span>{{ __('Plan: no time condition and all conditions true') }}</div>
            </div>
        </div>
    </div>

    @php
        $reportI18n = [
            'loading' => __('Loading...'),
            'chart_failed' => __('Failed to load chart.'),
            'power_events_empty' => __('No power events for current day.'),
            'pin' => __('Pin'),
        ];
    @endphp
    <script>
        window.reportI18n = @json($reportI18n);
    </script>
    <script src="{{ asset('assets/report.js') }}"></script>
</x-app-layout>
