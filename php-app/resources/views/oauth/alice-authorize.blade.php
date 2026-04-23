<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Authorize access') }}</h2>
    </x-slot>

    <div class="card theme-card border-0">
        <div class="card-body">
            <p class="mb-2">
                {{ __('Application') }}: <strong>{{ $appName }}</strong>
            </p>
            <p class="mb-3 text-muted">
                {{ __('Grant this app access to your devices and sensor states for Yandex Alice.') }}
            </p>

            <form method="post" action="{{ route('oauth.alice.approve') }}" class="d-flex gap-2">
                @csrf
                <input type="hidden" name="response_type" value="code">
                <input type="hidden" name="client_id" value="{{ $oauth['client_id'] }}">
                <input type="hidden" name="redirect_uri" value="{{ $oauth['redirect_uri'] }}">
                @if(!empty($oauth['state']))
                    <input type="hidden" name="state" value="{{ $oauth['state'] }}">
                @endif
                @if(!empty($oauth['scope']))
                    <input type="hidden" name="scope" value="{{ $oauth['scope'] }}">
                @endif

                <button type="submit" class="btn btn-primary" name="decision" value="allow">{{ __('Allow') }}</button>
                <button type="submit" class="btn btn-outline-danger" name="decision" value="deny">{{ __('Deny') }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
