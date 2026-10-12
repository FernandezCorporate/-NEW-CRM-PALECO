@php
    $layout = $layout ?? (auth()->user()?->role?->slug_identifier === 'admin' ? 'admin.base.base' : 'cwd.base.base');
@endphp

@extends($layout)

@section('title', 'Ticket Management')

@section('content')

    <x-alert />

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tickets</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ auth()->user()?->role?->slug_identifier === 'admin' ? 'Review all utility service requests across the organization (Read-Only)' : 'Manage all service requests and dispatches' }}
            </p>
        </div>
        
        @can('ticketForm', App\Models\Ticket::class)
            <a href="{{ route('cwd.tickets.createForm') }}" class="action-link inline-flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-5 py-2.5 rounded-md text-sm font-medium shadow-sm transition-all" data-loading-text="Loading...">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Ticket
            </a>
        @endcan
    </div>

    <!-- Inject the real-time Livewire Table Component -->
    <livewire:shared.ticket-table />

@endsection

