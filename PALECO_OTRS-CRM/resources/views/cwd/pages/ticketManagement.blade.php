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

    <!-- Search & Filter Controls -->
    <div class="mb-6">
        <form action="{{ route('cwd.tickets') }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-center w-full">
            
            <!-- Wide Search Bar -->
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="search" placeholder="Search tickets by ID, location, or description..." value="{{ request('search') }}" class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
            </div>

            <!-- Filter Dropdowns -->
            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <select name="filter" class="ts-filter-dropdown hidden">
                    <option value="all">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('filter') == $category->id ? 'selected' : '' }}>
                            {{ $category->category_name }}
                        </option>
                    @endforeach
                    <option value="other" {{ request('filter') == 'other' ? 'selected' : '' }}>Custom/Other Categories</option>
                </select>

                <select name="sort" class="ts-filter-dropdown hidden">
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest First</option>
                    <option value="status" {{ request('sort') == 'status' ? 'selected' : '' }}>Sort by Status</option>
                </select>
                <noscript><button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded-md">Apply</button></noscript>
            </div>
        </form>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
        
        <!-- Table Header Section -->
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Active Tickets</h2>
            <p class="text-xs text-gray-500 mt-1">Open tickets in the system queue</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-40">Ticket ID</th>
                        <th class="px-6 py-4 w-40">Source / Caller</th>
                        <th class="px-6 py-4 w-56">Address & Landmark</th>
                        <th class="px-6 py-4 w-48">Nature of Complaint</th>
                        <th class="px-6 py-4 w-40 text-center">Assigned Dept</th>
                        <th class="px-6 py-4 w-32 text-center">Status</th>
                        <th class="px-6 py-4">Complaint Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tickets as $ticket)
                        <tr class="hover:bg-gray-50 transition-colors">
                            
                            <!-- Ticket ID -->
                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-[#008f5d] text-sm">{{ $ticket->ticket_number }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $ticket->reported_at->format('M d, Y') }}</div>
                            </td>

                            <!-- Source / Caller -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800 font-medium">{{ $ticket->complaint_source->label() }}</div>
                                @if($ticket->consumer_id)
                                    <div class="text-xs text-gray-500 mt-0.5">Acc: {{ $ticket->consumer_id }}</div>
                                @endif
                            </td>

                            <!-- Address & Landmark -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800">
                                    {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                                </div>
                                @if($ticket->landmark)
                                    <div class="text-[11px] text-gray-500 mt-1 flex items-start gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        {{ Str::limit($ticket->landmark, 40) }}
                                    </div>
                                @endif
                            </td>

                            <!-- Nature of Complaint -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800 font-medium">
                                    {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
                                </div>
                            </td>

                            <!-- Assigned Department -->
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-green-50 text-green-700 border border-green-200 text-[11px] font-bold tracking-wide">
                                    {{ $ticket->department->dept_name ?? 'Unassigned' }}
                                </span>
                            </td>

                            <!-- Ticket Status -->
                            <td class="px-6 py-4 align-top text-center">
                                @php
                                    $statusColor = match($ticket->status->value) {
                                        'open' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'assigned' => 'bg-purple-50 text-purple-700 border-purple-200',
                                        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'closed' => 'bg-gray-100 text-gray-700 border-gray-300',
                                        default => 'bg-gray-100 text-gray-600 border-gray-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $statusColor }} text-[11px] font-bold">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>

                            <!-- Description / Remarks -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-700 leading-relaxed line-clamp-2">
                                    {{ $ticket->complaint_description }}
                                </div>
                                <div class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider">
                                    Logged at {{ $ticket->reported_at->format('H:i') }}
                                </div>
                            </td>
                
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900">No tickets found</h3>
                                <p class="text-xs text-gray-500 mt-1">Adjust your search or filter criteria to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($tickets->hasPages())
        <div class="mt-4">
            {{ $tickets->onEachSide(1)->links() }}
        </div>
    @endif

@endsection