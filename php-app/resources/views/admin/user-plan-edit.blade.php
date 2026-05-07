<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 mb-0">{{ __('Assign plan') }}: {{ $targetUser->email }}</h2>
    </x-slot>
    @php
        $statusLabel = static fn (?string $status): string => $status ? __('status.' . strtolower($status)) : '—';
    @endphp

    <div class="card shadow-sm">
        <div class="card-body">
            @if($subscription)
                <div class="alert alert-secondary">
                    <strong>{{ __('Current subscription') }}:</strong>
                    {{ $statusLabel($subscription->status) }},
                    {{ optional($subscription->starts_at)->format('Y-m-d H:i') }} -
                    {{ optional($subscription->ends_at)->format('Y-m-d H:i') ?? '∞' }},
                    {{ $subscription->source }}
                </div>
            @endif

            <form method="post" action="{{ route('admin.users.plan', $targetUser) }}" class="d-grid gap-3">
                @csrf
                @method('patch')

                <div>
                    <label class="form-label">{{ __('Plan') }}</label>
                    <select name="plan_id" class="form-select" required>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) old('plan_id', $targetUser->selected_plan_id) === (int) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select" required>
                        @foreach (['pending', 'active', 'expired', 'canceled'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $subscription->status ?? 'pending') === $status)>{{ $statusLabel($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">{{ __('Starts at') }}</label>
                    <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($subscription?->starts_at ?? now())->format('Y-m-d\\TH:i')) }}" required>
                </div>

                <div>
                    <label class="form-label">{{ __('Ends at') }}</label>
                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', optional($subscription?->ends_at)->format('Y-m-d\\TH:i')) }}">
                </div>

                <div>
                    <label class="form-label">{{ __('Source') }}</label>
                    <input type="text" name="source" class="form-control" value="{{ old('source', $subscription->source ?? 'admin_manual') }}" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.users') }}">{{ __('Back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
