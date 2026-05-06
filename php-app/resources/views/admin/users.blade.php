<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Users') }}</h2>
    </x-slot>

    <div class="card shadow-sm">
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('E-mail') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Alice Access') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>{{ $u->id }}</td>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->hasRole('administrator') ? __('Admin') : __('User') }}</td>
                            <td>{{ $u->selectedPlan?->name ?? '—' }}</td>
                            <td>{{ $u->alice_enabled ? __('Enabled') : __('Disabled') }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.users.edit', $u) }}">{{ __('Edit user') }}</a>
                                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.users.plan.edit', $u) }}">{{ __('Assign plan') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
