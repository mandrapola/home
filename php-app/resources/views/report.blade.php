<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Report') }}</h2>
    </x-slot>

    <link rel="stylesheet" href="{{ asset('assets/report.css') }}">

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="h6 mb-0">{{ __('Report') }}</h3>
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

