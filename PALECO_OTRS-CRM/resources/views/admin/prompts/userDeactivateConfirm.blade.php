@extends('admin.base.base')

@section('workspace-kind', 'confirmation')

@section('title', 'Deactivate User Account')

@section('content')
    @php
        $source = request('source');
        $teamId = request('team');
        $deptId = request('dept');
        $cancelRoute = match($source) {
            'details' => route('admin.users.show', array_filter(['user' => $userAccount, 'source' => $teamId ? 'team' : ($deptId ? 'department' : null), 'team' => $teamId, 'dept' => $deptId])),
            'team' => $teamId ? route('admin.teams.show', ['team' => $teamId]) : route('admin.users.show', ['user' => $userAccount]),
            'department' => $deptId ? route('admin.departments.show', ['dept' => $deptId]) : route('admin.users.show', ['user' => $userAccount]),
            default => session('user_list_url', route('admin.users')),
        };
        $backLabel = match($source) {
            'team' => 'Back to Team',
            'department' => 'Back to Department',
            'details' => 'Back to User Details',
            default => 'Back to Users',
        };
    @endphp

    <x-confirm-card
        title="Deactivate Account?"
        :action="route('admin.users.deactivate', $userAccount)"
        method="PATCH"
        :cancel-route="$cancelRoute"
        :back-label="$backLabel"
        confirm-text="Yes, Deactivate Account"
        loading-text="Deactivating..."
        variant="warning"
        :consequences="[
            '<span class=\'font-bold\'>The user will be blocked from logging in.</span>',
            'Their historical activity logs will remain intact.',
            'If the user is a field personnel account, their team memberships will be removed.',
            'Mobile users will be logged out of their account.',
            'You can reactivate this account later from the user management dashboard.'
        ]"
        :hidden-inputs="[
            'source' => request('source'),
            'dept' => request('dept'),
            'team' => request('team'),
        ]"
    >
        You are about to suspend system access for <strong class="font-bold text-slate-900">"{{ $userAccount->username }}"</strong>. 
        They will immediately lose the ability to log into the application.
        Are you sure you want to proceed?
    </x-confirm-card>
@endsection
