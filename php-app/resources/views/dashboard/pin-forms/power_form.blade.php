<label>
    {{ __('Name') }}<br>
    <input name="label" required class="form-full form-control">
</label>

<label class="checkbox-row">
    <input name="show_on_report" type="checkbox" class="form-check-input">
    {{ __('Show on Report') }}
</label>

<label class="checkbox-row">
    <input name="external_enabled" type="checkbox" class="form-check-input">
    {{ __('Available for Alice') }}
</label>
