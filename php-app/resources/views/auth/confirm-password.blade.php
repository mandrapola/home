<x-guest-layout>
    <h1 class="h4 mb-3">{{ __('Confirm password') }}</h1>

    <p class="text-secondary small mb-3">
        {{ __('This is a secure area of the application. Confirm your password to continue.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="d-grid">
            <x-primary-button>{{ __('Confirm') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
