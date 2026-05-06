@include('dashboard.pin-forms.sensor_form')

<label>
    {{ __('Moisture raw dry') }}<br>
    <input name="moisture_raw_dry" type="number" step="0.01" class="form-full form-control">
</label>

<label>
    {{ __('Moisture raw wet') }}<br>
    <input name="moisture_raw_wet" type="number" step="0.01" class="form-full form-control">
</label>

<label class="checkbox-row">
    <input name="moisture_show_percent" type="checkbox" class="form-check-input">
    {{ __('Show humidity as percent') }}
</label>
