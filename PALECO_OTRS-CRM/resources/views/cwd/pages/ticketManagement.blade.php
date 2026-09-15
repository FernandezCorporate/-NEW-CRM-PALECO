@extends('cwd.base.base')

@section('title', 'Ticket Management')

@section('content')

    @include('cwd.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tickets</h1>
            <p class="text-sm text-gray-500 mt-1">Manage all service requests and dispatches</p>
        </div>
        
        <a href="{{ route('cwd.tickets.createForm') }}" class="action-link inline-flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-5 py-2.5 rounded-md text-sm font-medium shadow-sm transition-all" data-loading-text="Loading...">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Ticket
        </a>
    </div>

    <!-- Inject the real-time Livewire Table Component -->
    <livewire:cwd.ticket-table />

@endsection