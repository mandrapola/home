<label>
    {{ __('Name') }}<br>
    <input name="label" required class="form-full form-control">
</label>

<label>
    {{ __('Unit') }}<br>
    <input name="unit" class="form-full form-control">
</label>

<label>
    {{ __('Chart Range, h') }}<br>
    <input name="chart_range_hours" type="number" min="1" step="1" class="form-full form-control">
</label>

<label class="checkbox-row">
    <input name="show_on_chart" type="checkbox" class="form-check-input">
    {{ __('Show on Chart') }}
</label>

<label class="checkbox-row">
    <input name="is_monitored" type="checkbox" class="form-check-input">
    {{ __('Monitoring') }}
</label>
