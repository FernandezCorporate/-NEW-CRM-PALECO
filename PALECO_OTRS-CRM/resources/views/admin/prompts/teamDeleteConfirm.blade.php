@extends('admin.base.base')

@section('workspace-kind', 'confirmation')

@section('title', $title)

@section('content')
    @php
        $source = request('source');
        $userId = request('user');
        $deptId = request('dept');
        $cancelRoute = match($source) {
            'details' => route('admin.teams.show', array_filter(['team' => $team, 'source' => $userId ? 'user' : ($deptId ? 'department' : null), 'user' => $userId, 'dept' => $deptId])),
            'user' => $userId ? route('admin.users.show', ['user' => $userId]) : route('admin.teams.show', ['team' => $team]),
            'department' => $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.teams.show', ['team' => $team]),
            default => session('team_list_url', route('admin.teams')),
        };
        $backLabel = match($source) {
            'user' => 'Back to User Profile',
            'department' => 'Back to Department',
            'details' => 'Back to Team Details',
            default => 'Back to Teams',
        };

        $consequences = $isForceDelete ? [
            '<span class=\'font-bold\'>This team will be destroyed entirely and cannot be restored.</span>',
            'Only allowed for a team created by mistake: it is refused if the team was ever assigned to a ticket.',
            'Its member list is removed with it. Teams that did real work should stay archived instead.'
        ] : [
            'The team record will be hidden, but not permanently deleted.',
            'Existing membership records tied to this team will remain intact.',
            'You can restore this team later from the system archives.'
        ];
    @endphp

    <x-confirm-card
        :title="$title . '?'"
        :action="$isForceDelete ? route('admin.teams.destroy', $team) : route('admin.teams.archive', $team)"
        method="DELETE"
        :cancel-route="$cancelRoute"
        :back-label="$backLabel"
        :confirm-text="$isForceDelete ? 'Yes, Permanently Delete' : 'Yes, Archive Team'"
        :loading-text="$isForceDelete ? 'Deleting...' : 'Archiving...'"
        variant="danger"
        :consequences="$consequences"
        :hidden-inputs="[
            'source' => request('source'),
            'dept' => request('dept'),
            'user' => request('user'),
        ]"
    >
        You are about to {{ $isForceDelete ? 'permanently delete' : 'archive' }} the <strong class="font-bold text-slate-900">"{{ $team->team_name }}"</strong> team. 
        @if($isForceDelete)
            This action is irreversible.
        @else
            This will remove it from active selection lists across the system. 
        @endif
        Are you absolutely sure you want to proceed?
    </x-confirm-card>
@endsection
