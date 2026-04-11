<x-guest-layout>
    <h1 class="h4 mb-3">{{ __('Verify email') }}</h1>

    <p class="text-secondary small mb-3">
        {{ __('Thanks for signing up! Verify your email by clicking the link we sent.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success py-2" role="alert">
            {{ __('A new verification link has been sent to your email address.') }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link link-secondary p-0">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
