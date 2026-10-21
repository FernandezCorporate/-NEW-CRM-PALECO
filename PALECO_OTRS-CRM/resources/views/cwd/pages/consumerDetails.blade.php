@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Consumer Profile')

@section('content')

    @include('cwd.prompts.alert')

    <!-- Back Navigation -->
    <div class="mb-6">
        <x-contextual-back :default="session('consumer_list_url', route('cwd.consumers'))" default-label="Back to Consumers" variant="link" />
    </div>

    <!-- Header Section -->
    <header class="mb-8">
        <h1 class="text-2xl lg:text-3xl font-bold text-slate-900">Consumer Profile</h1>
        <p class="text-sm text-slate-500 mt-1">Account and service history</p>
    </header>

    <!-- Main Profile Card -->
    <article class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 lg:p-8 mb-8" aria-labelledby="consumer-profile-heading">
        
        <!-- Top Row: Avatar, Name, Badge -->
        <div class="flex justify-between items-start mb-8">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-emerald-600 flex items-center justify-center text-white text-2xl font-bold shrink-0 shadow-xs">
                    {{ strtoupper(substr($consumer->name, 0, 1)) }}
                </div>
                <h2 id="consumer-profile-heading" class="text-2xl font-bold text-slate-900">{{ $consumer->name }}</h2>
            </div>
            
            @if(in_array($consumer->status, ['A', 'Active']))
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wide uppercase">Active</span>
            @elseif(in_array($consumer->status, ['D', 'Deactivated']))
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 tracking-wide uppercase">Deactivated</span>
            @elseif(in_array($consumer->status, ['I', 'Inactive']))
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200 tracking-wide uppercase">Inactive</span>
            @else
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-zinc-100 text-zinc-700 border border-zinc-200 tracking-wide uppercase">{{ $consumer->status ?: 'Unknown' }}</span>
            @endif
        </div>

        <!-- Details Grid (Semantic Definition List) -->
        <dl class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Left Column: Identifiers -->
            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-slate-50 rounded-lg text-slate-500 border border-slate-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 font-medium">Meter Number</dt>
                        <dd class="text-sm font-medium text-slate-900 mt-0.5 font-mono">{{ $consumer->meter_serial ?? 'N/A' }}</dd>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="p-2 bg-slate-50 rounded-lg text-slate-500 border border-slate-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 font-medium">Account Identifiers</dt>
                        <dd class="text-sm font-mono font-medium text-slate-900 mt-0.5">{{ $consumer->acct_code }} <span class="text-slate-300 mx-1">|</span> {{ $consumer->acct_no }}</dd>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="p-2 bg-slate-50 rounded-lg text-slate-500 border border-slate-100 shrink-0">
                        <x-icons.phone class="w-5 h-5" />
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 font-medium">Last Known Contact (from tickets)</dt>
                        <dd class="text-sm font-medium text-slate-900 mt-0.5">{{ $lastKnownContact ?? 'No contact history' }}</dd>
                    </div>
                </div>
            </div>

            <!-- Right Column: Location & Summary -->
            <div class="space-y-6 flex flex-col">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-slate-50 rounded-lg text-slate-500 border border-slate-100 shrink-0">
                        <x-icons.map-pin class="w-5 h-5" />
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 font-medium">Service Address</dt>
                        <dd class="text-sm font-medium text-slate-900 mt-0.5 leading-relaxed">{{ $consumer->address }}</dd>
                    </div>
                </div>

                <!-- Green Open Tickets Metric Card -->
                <div class="mt-auto bg-emerald-50 rounded-xl p-5 border border-emerald-100 flex flex-col justify-center">
                    <dt class="text-xs text-emerald-700 font-bold tracking-wide uppercase mb-1">Open Tickets</dt>
                    <dd class="text-4xl font-bold text-emerald-900">
                        <data value="{{ $consumer->open_tickets_count }}">{{ $consumer->open_tickets_count }}</data>
                    </dd>
                </div>
            </div>
        </dl>
    </article>

    <!-- Intake Source Metrics -->
    <section class="mb-8" aria-labelledby="intake-sources-heading" data-live-region="consumer-sources" data-live-events="TicketCreated,TicketIntakeUpdated,TicketAssigned,TicketWorkStarted,TicketAccomplished,TicketReportRejected,TicketVerifiedAndClosed,TicketCancelled,TicketForceClosed,TicketEndorsementInitiated,TicketEndorsementDecided">
        <h2 id="intake-sources-heading" class="text-lg font-bold text-slate-900 mb-4">Complaint Sources</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            
            <article class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs text-center">
                <h3 class="text-xs text-slate-500 font-medium mb-1">Phone Calls</h3>
                <data class="text-2xl font-bold text-slate-900" value="{{ $sourceMetrics['phone_call'] ?? 0 }}">{{ $sourceMetrics['phone_call'] ?? 0 }}</data>
            </article>
            
            <article class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs text-center">
                <h3 class="text-xs text-slate-500 font-medium mb-1">Walk-in</h3>
                <data class="text-2xl font-bold text-slate-900" value="{{ $sourceMetrics['walk_in'] ?? 0 }}">{{ $sourceMetrics['walk_in'] ?? 0 }}</data>
            </article>
            
            <article class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs text-center">
                <h3 class="text-xs text-slate-500 font-medium mb-1">Facebook / Online</h3>
                <data class="text-2xl font-bold text-slate-900" value="{{ $sourceMetrics['online_platforms'] ?? 0 }}">{{ $sourceMetrics['online_platforms'] ?? 0 }}</data>
            </article>
            
            <article class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs text-center">
                <h3 class="text-xs text-slate-500 font-medium mb-1">SMS</h3>
                <data class="text-2xl font-bold text-slate-900" value="{{ $sourceMetrics['sms_message'] ?? 0 }}">{{ $sourceMetrics['sms_message'] ?? 0 }}</data>
            </article>
            
            <article class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs text-center">
                <h3 class="text-xs text-slate-500 font-medium mb-1">Email</h3>
                <data class="text-2xl font-bold text-slate-900" value="{{ $sourceMetrics['email'] ?? 0 }}">{{ $sourceMetrics['email'] ?? 0 }}</data>
            </article>

        </div>
    </section>

    <!-- Historical Tickets Table: refreshes when any ticket changes (live-refresh.js) -->
    <section aria-labelledby="ticket-history-heading" data-live-region="consumer-tickets" data-live-events="TicketCreated,TicketIntakeUpdated,TicketAssigned,TicketWorkStarted,TicketAccomplished,TicketReportRejected,TicketVerifiedAndClosed,TicketCancelled,TicketForceClosed,TicketEndorsementInitiated,TicketEndorsementDecided">
        <div class="flex justify-between items-end mb-4">
            <h2 id="ticket-history-heading" class="text-lg font-bold text-slate-900">Ticket History</h2>
        </div>
        
        <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <caption class="sr-only">Historical tickets for {{ $consumer->name }}</caption>
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Ticket #</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Category</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Reported Date</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-4 text-right font-bold tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($historicalTickets as $ticket)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-mono font-medium text-slate-900 whitespace-nowrap">
                                    {{ $ticket->ticket_number }}
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    {{ $ticket->other_category ? ($ticket->other_category_name ?? 'Custom Category') : ($ticket->category->category_name ?? 'Uncategorized') }}
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ $ticket->department->dept_name ?? 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                                    {{ $ticket->reported_at ? $ticket->reported_at->format('M d, Y h:i A') : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $ticket->status->badgeClasses() }}">
                                        {{ $ticket->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('cwd.tickets.show', ['ticket' => $ticket->id, 'source' => 'consumer', 'consumer' => $consumer->id]) }}" class="text-emerald-700 hover:text-emerald-800 font-semibold text-sm">View details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 border border-slate-200 mb-3 text-slate-400">
                                        <x-icons.ticket class="w-6 h-6" />
                                    </div>
                                    <p class="text-sm font-medium text-slate-900">No ticket history</p>
                                    <p class="text-xs text-slate-500 mt-1">This consumer hasn't filed any complaints yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Table Pagination -->
        @if($historicalTickets->hasPages())
            <div class="mt-4">
                {{ $historicalTickets->links() }}
            </div>
        @endif
    </section>

@endsection