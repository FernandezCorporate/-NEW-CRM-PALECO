@extends('cwd.base.base')

@section('title', 'Endorsement Requests')

@section('content')

    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Endorsement Requests</h1>
            <p class="text-sm text-slate-500 mt-1">Review and act on endorsements submitted by supervisors on active tickets</p>
        </div>
        
        @if($statusMetrics['pending'] > 0)
            <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-2 rounded-lg text-sm font-bold shadow-xs">
                <x-icons.exclamation-triangle class="w-5 h-5" />
                {{ $statusMetrics['pending'] }} awaiting review
            </div>
        @endif
    </header>

    <nav class="record-tabs" aria-label="Filter endorsement requests">
        <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => null]) }}" @if(request('status', 'all') === 'all') aria-current="page" @endif>
            All requests
            <span>{{ number_format($statusMetrics['total'] ?? 0) }}</span>
        </a>
        @foreach($statuses as $statusEnum)
            <a href="{{ request()->fullUrlWithQuery(['status' => $statusEnum->value, 'page' => null]) }}" @if(request('status') === $statusEnum->value) aria-current="page" @endif>
                {{ $statusEnum->label() }}
                <span>{{ number_format($statusMetrics[$statusEnum->value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <!-- Search & Filter Controls -->
    <search class="filter-toolbar mb-6" aria-label="Endorsement search and filter">
        <form action="{{ request()->url() }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-center w-full">
            
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <x-icons.search class="h-5 w-5 text-slate-400" />
                </div>
                <input type="text" name="search" placeholder="Search endorsements by Ticket ID..." value="{{ request('search') }}" class="form-input w-full pl-10 pr-3" aria-label="Search endorsements by Ticket ID">
            </div>

            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <input type="hidden" name="status" value="{{ request('status', 'all') }}">
                <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                @if(request()->filled('search') || request()->filled('status'))
                    <x-btn href="{{ request()->url() }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">Clear filters</x-btn>
                @endif
            </div>
        </form>
    </search>

    <!-- Main Table Container -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Endorsement requests queue</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-[22%]">Ticket</th>
                        <th scope="col" class="px-6 py-4 w-[23%]">Supervisor</th>
                        <th scope="col" class="px-6 py-4 w-[18%]">From &rarr; To</th>
                        <th scope="col" class="px-6 py-4 w-[17%]">Requested</th>
                        <th scope="col" class="px-6 py-4 w-[10%] text-center">Status</th>
                        <th scope="col" class="px-6 py-4 w-[10%] text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($endorsements as $endorsement)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            
                            <!-- Ticket Info -->
                            <td class="px-6 py-4 align-top">
                                <a href="{{ route('cwd.tickets.show', $endorsement->ticket_id) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                    {{ $endorsement->ticket->ticket_number ?? 'N/A' }}
                                </a>
                                <div class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">
                                    {{ $endorsement->ticket->subject }}
                                </div>
                            </td>

                            <!-- Supervisor Info -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">
                                    {{ $endorsement->creator->full_name ?? 'N/A' }}
                                </div>
                            </td>

                            <!-- From / To Departments (snapshotted on the endorsement record) -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium leading-snug">
                                    {{ $endorsement->oldDepartment->dept_name ?? 'Unknown' }}
                                    @if($endorsement->oldDepartment?->trashed())
                                        <span class="text-[10px] text-slate-400 font-normal ml-1">(Archived)</span>
                                    @endif
                                </div>
                                <div class="text-[11px] mt-1 {{ $endorsement->newDepartment ? 'text-slate-600' : 'text-slate-400 italic' }}">
                                    &rarr;
                                    @if($endorsement->newDepartment)
                                        {{ $endorsement->newDepartment->dept_name }}
                                        @if($endorsement->newDepartment->trashed())
                                            <span class="text-[10px] text-slate-400 ml-1">(Archived)</span>
                                        @endif
                                    @elseif($endorsement->status === \App\Enums\EndorsementStatus::PENDING)
                                        Awaiting decision
                                    @else
                                        Not transferred
                                    @endif
                                </div>
                            </td>

                            <!-- Requested Date -->
                            <td class="px-6 py-4 align-top">
                                <time datetime="{{ $endorsement->created_at->toIso8601String() }}">
                                    <div class="text-sm text-slate-800 font-medium">{{ $endorsement->created_at->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $endorsement->created_at->format('h:i A') }}</div>
                                </time>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $endorsement->status->badgeClasses() }} text-[11px] font-bold">
                                    {{ $endorsement->status->label() }}
                                </span>
                            </td>

                            <!-- Action -->
                            <td class="px-6 py-4 align-top text-center">
                                <x-btn href="{{ route('cwd.endorsements.show', $endorsement) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="Review endorsement request for ticket {{ $endorsement->ticket->ticket_number ?? 'N/A' }}">
                                    <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                                    <span>Review</span>
                                </x-btn>
                            </td>
                
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.endorsement class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No endorsement requests</h3>
                                <p class="text-xs text-slate-500 mt-1">There are currently no endorsements matching your criteria.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($endorsements->hasPages())
        <div class="mt-4">
            {{ $endorsements->onEachSide(1)->links() }}
        </div>
    @endif

@endsection