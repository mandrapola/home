<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Admin') }}</h2>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small">{{ __('Users') }}</div>
                <div class="h4 mb-0">{{ $usersCount }}</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small">{{ __('Controllers') }}</div>
                <div class="h4 mb-0">{{ $controllersCount }}</div>
            </div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm"><div class="card-body">
                <div class="text-muted small">{{ __('Plans') }}</div>
                <div class="h4 mb-0">{{ $plansCount }}</div>
            </div></div>
        </div>
        <div class="col-12">
            <div class="d-flex gap-2">
                <a class="btn btn-primary btn-sm" href="{{ route('admin.users') }}">{{ __('Users') }}</a>
                <a class="btn btn-primary btn-sm" href="{{ route('admin.controllers') }}">{{ __('Controllers') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>

