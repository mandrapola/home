<x-guest-layout>
    <h1 class="h4 mb-3">{{ __('Log in') }}</h1>
    <p class="text-secondary small mb-3">{{ __('Access your dashboard, devices and automations.') }}</p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="form-check mb-3">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
            <label class="form-check-label" for="remember_me">{{ __('Remember me') }}</label>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            @if (Route::has('password.request'))
                <a class="link-secondary small" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            @endif
            <x-primary-button>{{ __('Log in') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
