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
    <select name="chart_range_hours" class="form-full form-control">
        <option value="1">1ч</option>
        <option value="4">4ч</option>
        <option value="8">8ч</option>
        <option value="16">16ч</option>
        <option value="24">24ч</option>
    </select>
</label>

<label class="checkbox-row">
    <input name="show_on_chart" type="checkbox" class="form-check-input">
    {{ __('Show on Chart') }}
</label>

<label class="checkbox-row">
    <input name="is_monitored" type="checkbox" class="form-check-input">
    {{ __('Monitoring') }}
</label>

<label class="checkbox-row">
    <input name="external_enabled" type="checkbox" class="form-check-input">
    {{ __('Available for Alice') }}
</label>
