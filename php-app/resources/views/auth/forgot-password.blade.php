<x-guest-layout>
    <h1 class="h4 mb-3">{{ __('Forgot password') }}</h1>

    <p class="text-secondary small mb-3">
        {{ __('Forgot your password? No problem. Enter your email and we will send a reset link.') }}
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="d-grid">
            <x-primary-button>{{ __('Email Password Reset Link') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
