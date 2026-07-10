@extends('admin.base.base')

@section('title', 'Department Management')

@section('content')
    @include('admin.prompts.alert')

    <!-- Top Header Row -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Ticket Category Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage complaint categories used during ticket creation</p>
        </div>
        
        <a href="" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Ticket Category
        </a>
    </div>
@endsection