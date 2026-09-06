@extends('cwd.base.base')

@section('title', 'Escalation Requests')

@section('content')

    @include('cwd.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Escalation Requests</h1>
            <p class="text-sm text-gray-500 mt-1">Review and act on escalations submitted by foremen on active tickets</p>
        </div>
        
        @if($statusMetrics['pending'] > 0)
            <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-2 rounded-md text-sm font-bold shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                {{ $statusMetrics['pending'] }} awaiting review
            </div>
        @endif
    </div>

    <nav class="record-tabs" aria-label="Filter escalation requests">
        <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => null]) }}" @if(request('status', 'all') === 'all') aria-current="page" @endif>All requests</a>
        @foreach($statuses as $statusEnum)
            <a href="{{ request()->fullUrlWithQuery(['status' => $statusEnum->value, 'page' => null]) }}" @if(request('status') === $statusEnum->value) aria-current="page" @endif>{{ $statusEnum->label() }}</a>
        @endforeach
    </nav>

    <!-- Search & Filter Controls -->
    <div class="mb-6">
        <form action="{{ request()->url() }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-center w-full">
            
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="search" placeholder="Search escalations by Ticket ID..." value="{{ request('search') }}" class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
            </div>

        <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <input type="hidden" name="status" value="{{ request('status', 'all') }}">
                <button type="submit" class="queue-search" data-loading-text="Searching...">Search</button>
                @if(request()->filled('search') || request()->filled('status'))
                    <a href="{{ request()->url() }}" class="queue-clear">Clear filters</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-[22%]">Ticket</th>
                        <th class="px-6 py-4 w-[23%]">Foreman</th>
                        <th class="px-6 py-4 w-[18%] text-center">Target Dept</th>
                        <th class="px-6 py-4 w-[17%]">Requested</th>
                        <th class="px-6 py-4 w-[10%] text-center">Status</th>
                        <th class="px-6 py-4 w-[10%] text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($escalations as $escalation)
                        <tr class="hover:bg-gray-50 transition-colors">
                            
                            <!-- Ticket Info -->
                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-[#008f5d] text-sm">{{ $escalation->ticket->ticket_number ?? 'N/A' }}</div>
                                <div class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">
                                    {{ $escalation->ticket->subject }}
                                </div>
                            </td>

                            <!-- Foreman Info -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800 font-medium flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path></svg>
                                    {{ $escalation->creator->full_name ?? 'N/A' }}
                                </div>
                                <div class="text-[11px] text-gray-400 mt-1 ml-6">{{ $escalation->creator->department->dept_name ?? 'Field Team' }}</div>
                            </td>

                            <!-- Target Department -->
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-gray-50 text-gray-700 border border-gray-200 text-[11px] font-bold tracking-wide">
                                    {{ $escalation->suggestedDepartment?->dept_name ?? 'Unassigned' }}
                                </span>
                            </td>

                            <!-- Requested Date -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800">{{ $escalation->created_at->format('Y-m-d H:i') }}</div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4 align-top text-center">
                                @php
                                    $statusColor = match($escalation->status->value ?? 'pending') {
                                        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'approved' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                        default => 'bg-gray-100 text-gray-600 border-gray-200'
                                    };
                                    $statusLabel = method_exists($escalation->status, 'label') 
                                        ? $escalation->status->label() 
                                        : ucfirst($escalation->status->value ?? 'Pending');
                                @endphp
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $statusColor }} text-[11px] font-bold">
                                    {{ $statusLabel }}
                                </span>
                            </td>

                            <!-- Action -->
                            <td class="px-6 py-4 align-top text-center">
                                <a href="{{ route('cwd.escalations.show', $escalation) }}" class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 shadow-sm text-[11px] font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#008f5d] transition-colors">
                                    Review
                                </a>
                            </td>
                
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900">No escalation requests</h3>
                                <p class="text-xs text-gray-500 mt-1">There are currently no escalations matching your criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($escalations->hasPages())
        <div class="mt-4">
            {{ $escalations->onEachSide(1)->links() }}
        </div>
    @endif

@endsection
