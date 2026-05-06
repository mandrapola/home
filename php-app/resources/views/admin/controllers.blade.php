<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Controllers') }}</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Last Seen') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($controllers as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>{{ $c->name }}</td>
                            <td>{{ $c->status }}</td>
                            <td>{{ $c->users_count }}</td>
                            <td>{{ optional($c->last_seen_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.controller.show', $c->id) }}">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
