@php
    $pins = \App\Models\Pin::query()
        ->where('controller_id', $entry->id)
        ->orderBy('pin')
        ->get(['id', 'pin', 'label']);
@endphp

<div class="mt-3">
    <div class="fw-bold mb-2">{{ __('Pins') }}</div>
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>{{ __('Pin') }}</th>
                <th>{{ __('Label') }}</th>
                <th>{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pins as $pin)
                <tr>
                    <td>{{ $pin->id }}</td>
                    <td>{{ $pin->pin }}</td>
                    <td>{{ __($pin->label) }}</td>
                    <td class="text-nowrap">
                        <a class="btn btn-xs btn-outline-primary" href="{{ backpack_url('pins/'.$pin->id.'/show') }}">{{ __('View') }}</a>
                        <a class="btn btn-xs btn-outline-secondary" href="{{ backpack_url('pins/'.$pin->id.'/edit') }}">{{ __('Edit') }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">{{ __('No pins found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

