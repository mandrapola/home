<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Edit Pin') }}: {{ $pin->label }} ({{ $pin->pin }})</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="small text-muted mb-3">{{ __('Style') }}: {{ $pin->digital_style }}</div>

            <form method="post" action="{{ route('admin.pin.update', ['pinId' => $pin->id]) }}" class="d-grid gap-3">
                @csrf
                @method('patch')

                <div>
                    <label class="form-label">{{ __('Name') }}</label>
                    <input type="text" name="label" class="form-control" value="{{ old('label', $pin->label) }}" required>
                </div>

                @if (($pin->digital_style ?? '') === 'power')
                    <input type="hidden" name="show_on_report" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_report" id="show_on_report" value="1" @checked((int) old('show_on_report', $pin->show_on_report ?? 0) === 1)>
                        <label class="form-check-label" for="show_on_report">{{ __('Show on Report') }}</label>
                    </div>
                @else
                    <div>
                        <label class="form-label">{{ __('Unit') }}</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit', $pin->unit) }}">
                    </div>

                    <div>
                        <label class="form-label">{{ __('Chart Range, h') }}</label>
                        <select name="chart_range_hours" class="form-select">
                            @foreach ([1, 4, 8, 16, 24] as $hours)
                                <option value="{{ $hours }}" @selected((int) old('chart_range_hours', $pin->chart_range_hours ?? 24) === $hours)>{{ $hours }}</option>
                            @endforeach
                        </select>
                    </div>

                    <input type="hidden" name="show_on_chart" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_chart" id="show_on_chart" value="1" @checked((int) old('show_on_chart', $pin->show_on_chart ?? 0) === 1)>
                        <label class="form-check-label" for="show_on_chart">{{ __('Show on Chart') }}</label>
                    </div>

                    <input type="hidden" name="is_monitored" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_monitored" id="is_monitored" value="1" @checked((int) old('is_monitored', $pin->is_monitored ?? 0) === 1)>
                        <label class="form-check-label" for="is_monitored">{{ __('Monitoring') }}</label>
                    </div>

                    @if (($pin->digital_style ?? '') === 'sensor_humidity')
                        <div>
                            <label class="form-label">{{ __('Moisture raw dry') }}</label>
                            <input type="number" step="0.01" name="moisture_raw_dry" class="form-control" value="{{ old('moisture_raw_dry', $pin->moisture_raw_dry ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Moisture raw wet') }}</label>
                            <input type="number" step="0.01" name="moisture_raw_wet" class="form-control" value="{{ old('moisture_raw_wet', $pin->moisture_raw_wet ?? '') }}">
                        </div>
                        <input type="hidden" name="moisture_show_percent" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="moisture_show_percent" id="moisture_show_percent" value="1" @checked((int) old('moisture_show_percent', $pin->moisture_show_percent ?? 0) === 1)>
                            <label class="form-check-label" for="moisture_show_percent">{{ __('Show humidity as percent') }}</label>
                        </div>
                    @endif
                @endif

                @if ($hasExternalEnabled)
                    <input type="hidden" name="external_enabled" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="external_enabled" id="external_enabled" value="1" @checked((int) old('external_enabled', $pin->external_enabled ?? 0) === 1)>
                        <label class="form-check-label" for="external_enabled">{{ __('Available for Alice') }}</label>
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    <a href="{{ route('admin.controller.show', ['controllerId' => $pin->controller_id]) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
