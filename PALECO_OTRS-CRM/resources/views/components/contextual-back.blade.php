@props([
    'url' => null,
    'label' => null,
    'default' => null,
    'defaultLabel' => 'Back',
    'variant' => 'button',
])

@php
    $source = old('source', request('source'));
    $consumerId = old('consumer', request('consumer'));
    $parentId = old('parent', request('parent'));
    $endorsementId = old('endorsement', request('endorsement'));
    $deptId = old('dept', request('dept'));
    $teamId = old('team', request('team'));
    $userId = old('user', request('user'));
    $ticketId = old('ticket', request('ticket'));

    $resolvedUrl = $url;
    $resolvedLabel = $label;

    if (! $resolvedUrl) {
        if ($source === 'dashboard') {
            $resolvedUrl = auth()->user()?->role?->slug_identifier === 'admin'
                ? route('admin.dashboard')
                : route('cwd.dashboard');
            $resolvedLabel = $resolvedLabel ?? 'Back to Dashboard';
        } elseif ($source === 'consumer' && $consumerId) {
            $resolvedUrl = route('cwd.consumers.show', ['consumer' => $consumerId]);
            $resolvedLabel = $resolvedLabel ?? 'Back to Consumer Profile';
        } elseif ($source === 'parent' && $parentId) {
            $resolvedUrl = route('cwd.tickets.show', ['ticket' => $parentId]);
            $resolvedLabel = $resolvedLabel ?? 'Back to Parent Ticket';
        } elseif ($source === 'endorsement' && $endorsementId) {
            $resolvedUrl = route('cwd.endorsements.show', ['endorsement' => $endorsementId]);
            $resolvedLabel = $resolvedLabel ?? 'Back to Endorsement';
        } elseif ($source === 'department' && $deptId) {
            $resolvedUrl = route('admin.departments.show', ['dept' => $deptId]);
            $resolvedLabel = $resolvedLabel ?? 'Back to Department';
        } elseif ($source === 'team' && $teamId) {
            $resolvedUrl = route('admin.teams.show', array_filter(['team' => $teamId, 'source' => $deptId ? 'department' : null, 'dept' => $deptId]));
            $resolvedLabel = $resolvedLabel ?? 'Back to Team';
        } elseif ($source === 'user' && $userId) {
            $resolvedUrl = route('admin.users.show', array_filter(['user' => $userId, 'source' => $deptId ? 'department' : null, 'dept' => $deptId]));
            $resolvedLabel = $resolvedLabel ?? 'Back to User Profile';
        } elseif ($source === 'ticket' && $ticketId) {
            $resolvedUrl = route('cwd.tickets.show', ['ticket' => $ticketId]);
            $resolvedLabel = $resolvedLabel ?? 'Back to Ticket';
        } else {
            $resolvedUrl = $default ?? url()->previous();
            $resolvedLabel = $resolvedLabel ?? $defaultLabel;
        }
    } else {
        $resolvedLabel = $resolvedLabel ?? $defaultLabel;
    }
@endphp

@if ($variant === 'icon-box')
    <a href="{{ $resolvedUrl }}" aria-label="{{ $resolvedLabel }}" title="{{ $resolvedLabel }}" {{ $attributes->merge(['class' => 'min-h-11 min-w-11 inline-flex items-center justify-center p-2 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-slate-500 shadow-2xs']) }}>
        <x-icons.arrow-left class="w-5 h-5" />
    </a>
@elseif ($variant === 'icon-circle')
    <a href="{{ $resolvedUrl }}" title="{{ $resolvedLabel }}" aria-label="{{ $resolvedLabel }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center w-10 h-10 rounded-full bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2']) }}>
        <x-icons.arrow-left class="w-5 h-5" />
    </a>
@elseif ($variant === 'link')
    <a href="{{ $resolvedUrl }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-700 transition-colors']) }}>
        <x-icons.arrow-left class="w-4 h-4" />
        <span>{{ $resolvedLabel }}</span>
    </a>
@else
    <a href="{{ $resolvedUrl }}" data-loading-text="Loading..." {{ $attributes->merge(['class' => 'btn btn-secondary px-4 py-2 text-sm shadow-xs']) }}>
        <x-icons.arrow-left class="w-4 h-4 text-slate-500" />
        <span>{{ $resolvedLabel }}</span>
    </a>
@endif
