@php
    $layout = $layout ?? (auth()->user()?->role?->slug_identifier === 'admin' ? 'admin.base.base' : 'cwd.base.base');
@endphp

@extends($layout)

@section('title', 'Ticket Management')

@section('content')

    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Tickets</h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ auth()->user()?->role?->slug_identifier === 'admin' ? 'Review all utility service requests across the organization (Read-Only)' : 'Manage all service requests and dispatches' }}
            </p>
        </div>
        
        @can('access-cwd_officer')
            <x-btn href="{{ route('cwd.tickets.createForm') }}" variant="primary" class="action-link" data-loading-text="Loading...">
                <x-icons.plus class="w-4 h-4 mr-1.5" />
                New Ticket
            </x-btn>
        @endcan
    </header>

    <!-- Real-time Livewire Table Component Island -->
    <livewire:shared.ticket-table />

@endsection
