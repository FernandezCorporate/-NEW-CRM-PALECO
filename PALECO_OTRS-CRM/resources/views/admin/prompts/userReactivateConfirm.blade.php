@extends('admin.base.base')

@section('workspace-kind', 'confirmation')

@section('title', 'Reactivate User Account')

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
        title="Reactivate Account?"
        :action="route('admin.users.reactivate', $userAccount)"
        method="PATCH"
        :cancel-route="$cancelRoute"
        :back-label="$backLabel"
        confirm-text="Yes, Reactivate Account"
        loading-text="Reactivating..."
        variant="success"
        :consequences="[
            '<span class=\'font-bold\'>The user will be able to securely log in normally.</span>',
            'Their historical activity logs and records remain intact.',
            'You can suspend this account again later if needed.'
        ]"
        :hidden-inputs="[
            'source' => request('source'),
            'dept' => request('dept'),
            'team' => request('team'),
        ]"
    >
        You are about to restore system access for <strong class="font-bold text-slate-900">"{{ $userAccount->username }}"</strong>. 
        They will immediately regain the ability to log into the application.
        Are you sure you want to proceed?
    </x-confirm-card>
@endsection
