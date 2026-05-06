<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Profile') }}</h2>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 mb-0">{{ __('User Data') }}</h3>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#profileEditForm">
                            {{ __('Edit') }}
                        </button>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Name') }}</div>
                        <div class="fw-semibold">{{ $user->name }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">E-mail</div>
                        <div class="fw-semibold">{{ $user->email }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Current Time Zone') }}</div>
                        <div class="fw-semibold">{{ $user->time_zone ?? 'Europe/Moscow' }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Current Language') }}</div>
                        <div class="fw-semibold">{{ strtoupper((string) ($user->locale ?? 'ru')) }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Alice Access') }}</div>
                        <div class="fw-semibold">
                            @if ($user->alice_enabled)
                                {{ __('Enabled') }}
                            @else
                                {{ __('Disabled') }}
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="mb-2">
                        <div class="text-muted small">Yandex Alice</div>
                        <div class="fw-semibold">
                            @if ($aliceLinkedAccount)
                                {{ __('Connected') }} (ID: {{ $aliceLinkedAccount->yandex_user_id }})
                            @else
                                {{ __('Not connected') }}
                            @endif
                        </div>
                    </div>

                    @if (session('alice-status'))
                        <div class="alert alert-success py-2 mb-3">{{ session('alice-status') }}</div>
                    @endif
                    @if (session('alice-error'))
                        <div class="alert alert-danger py-2 mb-3">{{ session('alice-error') }}</div>
                    @endif

                    <div class="d-flex gap-2 mb-3">
                        <a href="{{ route('profile.alice.connect') }}"
                           class="btn btn-outline-primary btn-sm @if (!config('services.alice.enabled') || !($user->alice_enabled ?? false)) disabled @endif">
                            {{ __('Connect Alice') }}
                        </a>

                        @if ($aliceLinkedAccount)
                            <form method="post" action="{{ route('profile.alice.disconnect') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    {{ __('Disconnect Alice') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    @if (session('status') === 'profile-updated')
                        <div class="alert alert-success py-2 mb-3">{{ __('Profile updated.') }}</div>
                    @endif

                    <div id="profileEditForm" class="collapse">
                        <form method="post" action="{{ route('profile.update') }}" class="border rounded p-3 theme-form-panel">
                            @csrf
                            @method('patch')

                            <div class="mb-3">
                                <label class="form-label">{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required maxlength="255">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('Time Zone') }}</label>
                                <select name="time_zone" class="form-select @error('time_zone') is-invalid @enderror" required>
                                    @php($selectedTimeZone = old('time_zone', $user->time_zone ?? 'Europe/Moscow'))
                                    @foreach ($timeZones as $timeZone)
                                        <option value="{{ $timeZone }}" @selected($selectedTimeZone === $timeZone)>{{ $timeZone }}</option>
                                    @endforeach
                                </select>
                                @error('time_zone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('Interface Language') }}</label>
                                <select name="locale" class="form-select @error('locale') is-invalid @enderror" required>
                                    @php($selectedLocale = old('locale', $user->locale ?? 'ru'))
                                    @foreach ($locales as $locale)
                                        <option value="{{ $locale }}" @selected($selectedLocale === $locale)>{{ strtoupper($locale) }}</option>
                                    @endforeach
                                </select>
                                @error('locale')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-success btn-sm">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
