@extends('admin.base.base')

@section('workspace-kind', 'confirmation')

@section('title', $title)

@section('content')
    @php
        $source = request('source');
        $userId = request('user');
        $teamId = request('team');
        $cancelRoute = match($source) {
            'details' => route('admin.departments.show', array_filter(['dept' => $dept, 'source' => $userId ? 'user' : ($teamId ? 'team' : null), 'user' => $userId, 'team' => $teamId])),
            'user' => $userId ? route('admin.users.show', ['user' => $userId]) : route('admin.departments.show', ['dept' => $dept]),
            'team' => $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.departments.show', ['dept' => $dept]),
            default => session('department_list_url', route('admin.departments')),
        };
        $backLabel = match($source) {
            'user' => 'Back to User Profile',
            'team' => 'Back to Team',
            'details' => 'Back to Department Details',
            default => 'Back to Departments',
        };

        $consequences = $isForceDelete ? [
            '<span class=\'font-bold\'>This department will be destroyed entirely.</span>',
            'Any users currently attached to this department may lose their association.',
            'You CANNOT restore this department later.'
        ] : [
            'The department record will be hidden, but not permanently deleted.',
            'Existing historical records tied to this department will remain intact.',
            'You can restore this department later from the system archives.'
        ];
    @endphp

    <x-confirm-card
        :title="$title . '?'"
        :action="$isForceDelete ? route('admin.departments.destroy', $dept) : route('admin.departments.archive', $dept)"
        method="DELETE"
        :cancel-route="$cancelRoute"
        :back-label="$backLabel"
        :confirm-text="$isForceDelete ? 'Yes, Permanently Delete' : 'Yes, Archive Department'"
        :loading-text="$isForceDelete ? 'Deleting...' : 'Archiving...'"
        variant="danger"
        :consequences="$consequences"
        :hidden-inputs="[
            'source' => request('source'),
            'user' => request('user'),
            'team' => request('team'),
        ]"
    >
        You are about to {{ $isForceDelete ? 'permanently delete' : 'archive' }} the <strong class="font-bold text-slate-900">"{{ $dept->dept_name }}"</strong> department. 
        @if($isForceDelete)
            This action is irreversible and all data will be permanently wiped.
        @else
            This will remove it from active selection lists across the system. 
        @endif
        Are you absolutely sure you want to proceed?
    </x-confirm-card>
@endsection
