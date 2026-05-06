<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Controller') }}: {{ $controller->name }}</h2>
    </x-slot>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div><strong>{{ __('ID') }}:</strong> {{ $controller->id }}</div>
            <div><strong>{{ __('Status') }}:</strong> {{ $controller->status }}</div>
            <div><strong>{{ __('Last Seen') }}:</strong> {{ $controller->last_seen_at ?? '—' }}</div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="h6 mb-3">{{ __('Pins') }}</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Pin') }}</th>
                        <th>{{ __('Label') }}</th>
                        <th>{{ __('Style') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($pins as $pin)
                        <tr>
                            <td>{{ $pin->id }}</td>
                            <td>{{ $pin->pin }}</td>
                            <td>{{ $pin->label }}</td>
                            <td>{{ $pin->digital_style }}</td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.pin.edit', ['pinId' => $pin->id]) }}">
                                    {{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
