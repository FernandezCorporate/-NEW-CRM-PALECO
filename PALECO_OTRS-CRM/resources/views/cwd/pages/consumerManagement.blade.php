@extends('cwd.base.base')

@section('title', 'Consumers')

@section('content')

    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Consumers</h1>
            <p class="text-sm text-slate-500 mt-1">Member-consumer accounts and service history</p>
        </div>
    </header>

    <!-- Search & Filter Controls -->
    <search class="filter-toolbar mb-8" aria-label="Consumer search">
        <form action="{{ request()->url() }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-center w-full">
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <x-icons.search class="h-5 w-5 text-slate-400" />
                </div>
                <input type="text" name="search" placeholder="Search by name or account ID..." value="{{ request('search') }}" class="form-input w-full pl-10 pr-3" aria-label="Search consumers by name or account ID">
            </div>

            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                @if(request()->filled('search'))
                    <x-btn href="{{ request()->url() }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">Clear filters</x-btn>
                @endif
            </div>
        </form>
    </search>

    <!-- Consumer Cards Grid: refreshes when any ticket changes, since cards summarise each consumer's tickets (live-refresh.js) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-6" data-live-region="consumer-cards" data-live-events="TicketCreated,TicketIntakeUpdated,TicketAssigned,TicketWorkStarted,TicketAccomplished,TicketReportRejected,TicketVerifiedAndClosed,TicketCancelled,TicketForceClosed,TicketEndorsementInitiated,TicketEndorsementDecided">
        @forelse($consumers as $consumer)
            <a href="{{ route('cwd.consumers.show', $consumer->id) }}" class="block group">
                <article class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 group-hover:shadow-sm transition-shadow flex flex-col h-full">
                    
                    <!-- Card Header -->
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-lg font-bold text-slate-900 leading-tight pr-2 group-hover:text-emerald-700 transition-colors">{{ $consumer->name }}</h2>
                        
                        @if(in_array($consumer->status, ['A', 'Active']))
                            <x-badge variant="emerald">Active</x-badge>
                        @elseif(in_array($consumer->status, ['D', 'Deactivated']))
                            <x-badge variant="amber">Deactivated</x-badge>
                        @elseif(in_array($consumer->status, ['I', 'Inactive']))
                            <x-badge variant="slate">Inactive</x-badge>
                        @else
                            <x-badge variant="zinc">{{ $consumer->status ?: 'Unknown' }}</x-badge>
                        @endif
                    </div>

                    <!-- Card Body -->
                    <div class="space-y-3 mb-6 flex-grow">
                        <div class="text-xs text-slate-500 font-mono font-medium tracking-wide">{{ $consumer->acct_code }}</div>
                        
                        <div class="flex items-center gap-3 text-sm text-slate-700">
                            <x-icons.phone class="w-4 h-4 text-slate-400" />
                            <span>{{ $consumer->contact_number ?? 'No contact provided' }}</span>
                        </div>

                        <div class="flex items-start gap-3 text-sm text-slate-700">
                            <x-icons.map-pin class="w-4 h-4 text-slate-400 mt-0.5" />
                            <span class="line-clamp-2 leading-relaxed">{{ $consumer->address }}</span>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-sm text-slate-500">Open tickets: <strong class="text-slate-900 ml-1">{{ $consumer->open_tickets_count ?? 0 }}</strong></span>
                    </div>
                </article>
            </a>
        @empty
            <div class="col-span-1 md:col-span-2 xl:col-span-3 py-16 text-center border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white border border-slate-200 mb-3 shadow-xs">
                    <x-icons.user class="w-6 h-6 text-slate-400" />
                </div>
                <h3 class="text-sm font-bold text-slate-900">No consumers found</h3>
                <p class="text-xs text-slate-500 mt-1">There are currently no consumer profiles matching your search criteria.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div data-live-region="consumer-pagination" data-live-events="TicketCreated,TicketIntakeUpdated,TicketAssigned,TicketWorkStarted,TicketAccomplished,TicketReportRejected,TicketVerifiedAndClosed,TicketCancelled,TicketForceClosed,TicketEndorsementInitiated,TicketEndorsementDecided">
        @if($consumers->hasPages())
            <div class="mt-4">
                {{ $consumers->links() }}
            </div>
        @endif
    </div>

@endsection