<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Edit user') }}: {{ $targetUser->email }}</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('admin.users.update', $targetUser) }}" class="d-grid gap-3">
                @csrf
                @method('patch')

                <div>
                    <label class="form-label">{{ __('Name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $targetUser->name) }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('E-mail') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $targetUser->email) }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Time Zone') }}</label>
                    <input type="text" name="time_zone" class="form-control" value="{{ old('time_zone', $targetUser->time_zone) }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Language') }}</label>
                    <select name="locale" class="form-select" required>
                        <option value="en" @selected(old('locale', $targetUser->locale) === 'en')>en</option>
                        <option value="ru" @selected(old('locale', $targetUser->locale) === 'ru')>ru</option>
                    </select>
                </div>

                <input type="hidden" name="alice_enabled" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="alice_enabled" name="alice_enabled" value="1" @checked((int) old('alice_enabled', $targetUser->alice_enabled ? 1 : 0) === 1)>
                    <label class="form-check-label" for="alice_enabled">{{ __('Alice Access') }}</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.users') }}">{{ __('Back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
