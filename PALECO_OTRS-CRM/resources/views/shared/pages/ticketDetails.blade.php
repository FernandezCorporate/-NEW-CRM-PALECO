@php
    $layout = $layout ?? (auth()->user()?->role?->slug_identifier === 'admin' ? 'admin.base.base' : 'cwd.base.base');
    $isAdmin = auth()->user()?->role?->slug_identifier === 'admin';
@endphp

@extends($layout)

@section('workspace-kind', 'detail')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            <x-contextual-back :default="session('ticket_list_url', $isAdmin ? route('admin.tickets') : route('cwd.tickets'))" default-label="Back to Tickets" variant="icon-box" />
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-slate-900 font-mono tracking-tight">
                        {{ $ticket->ticket_number }}
                    </h1>
                    
                    <!-- Standout Lifecycle Status Badge -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow-2xs border {{ $ticket->status->badgeClasses() }}">
                        <span class="w-2 h-2 rounded-full {{ $ticket->status->chartClass() }}"></span>
                        {{ $ticket->status->label() }}
                    </span>

                    @if($ticket->parent_ticket_id)
                        <span class="text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Child Ticket
                        </span>
                    @endif

                    @if($isAdmin)
                        <span class="text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Read-Only
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 mt-1 font-medium">{{ $ticket->subject }}</p>
            </div>
        </div>

        <!-- Action & Metadata Controls -->
        <div class="flex flex-wrap items-center gap-3 self-stretch md:self-auto justify-start md:justify-end">
            <!-- Informational Metadata Pills -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Source -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-semibold shadow-2xs {{ $ticket->complaint_source->badgeClasses() }}">
                    <x-icons.phone class="w-3.5 h-3.5" />
                    {{ $ticket->complaint_source->label() }}
                </span>
                <!-- Category -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold shadow-2xs">
                    <x-icons.category class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}</span>
                    @if(! $ticket->other_category && $ticket->category?->trashed())
                        <span class="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded font-medium italic">Archived</span>
                    @endif
                </span>
                <!-- Department -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold shadow-2xs">
                    <x-icons.department class="w-3.5 h-3.5" />
                    <span>{{ $ticket->department->dept_name ?? 'Unassigned' }}</span>
                    @if($ticket->department?->trashed())
                        <span class="text-[10px] bg-emerald-200 text-emerald-800 px-1.5 py-0.5 rounded font-medium italic">Archived</span>
                    @endif
                </span>
            </div>

            @can('update', $ticket)
                <a href="{{ route('cwd.tickets.editForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')])) }}" class="btn btn-secondary text-xs py-2 px-3.5 shadow-xs">
                    <x-icons.pencil class="w-4 h-4 text-slate-500" />
                    <span>Edit Details</span>
                </a>
            @endcan

            <!-- Distinct Action Button for Child Ticket (Mutating Action: CWD Only) -->
            @can('createChild', $ticket)
                <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 px-3.5 shadow-xs">
                    <x-icons.plus class="w-4 h-4" />
                    <span>Create Child Ticket</span>
                </a>
            @endcan
        </div>
    </header>

    <!-- Top Block: Info & Routing -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 items-start">
        
        <!-- Left Column Wrapper -->
        <div class="xl:col-span-2 flex flex-col gap-6">
            
            <!-- CRM Linked Consumer Profile (Modular Partial) -->
            @include('shared.pages.partials.ticket-consumer-card', ['ticket' => $ticket, 'isAdmin' => $isAdmin])

            <!-- Consumer Issue Details (Semantic Definition List) -->
            <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-6" aria-labelledby="issue-details-heading">
                <h2 id="issue-details-heading" class="text-base font-bold text-slate-900 mb-6">Complaint & Issue Details</h2>
                
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-y-6 gap-x-8">
                    <!-- Location -->
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Incident Address</dt>
                        <dd class="flex items-start gap-2 text-slate-800 font-medium text-sm">
                            <x-icons.map-pin class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
                            <span>{{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Landmark</dt>
                        <dd class="text-sm text-slate-700 font-medium">{{ $ticket->landmark ?? 'No landmark provided' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Consumer Contact</dt>
                        <dd class="text-sm text-slate-700 font-medium">{{ $ticket->consumer_contact ?? 'No contact provided' }}</dd>
                    </div>

                    <!-- Complaint Description -->
                    <div class="md:col-span-3">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Detailed Description</dt>
                        <dd class="text-sm text-slate-800 bg-slate-50 p-4 rounded-lg border border-slate-100 leading-relaxed whitespace-pre-wrap">{{ $ticket->complaint_description }}</dd>
                    </div>

                    <!-- Timestamps -->
                    <div class="md:col-span-2">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Date Logged</dt>
                        <dd class="flex items-center gap-2 text-slate-800 text-sm font-medium">
                            <x-icons.clock class="w-4 h-4 text-slate-400 shrink-0" />
                            <span>{{ $ticket->reported_at->format('M d, Y h:i A') }}</span>
                        </dd>
                    </div>

                    <div class="md:col-span-1">
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Created By</dt>
                        <dd class="flex items-center gap-2 text-slate-800 text-sm font-medium">
                            <x-icons.user class="w-4 h-4 text-slate-400 shrink-0" />
                            <span>{{ $ticket->creator->full_name ?? 'System' }}</span>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Right Column Wrapper: Assigned Personnel & Hierarchy Tree -->
        <div class="flex flex-col gap-6 h-full">
            <!-- Assigned Personnel Card (Modular Partial) -->
            @include('shared.pages.partials.ticket-assigned-personnel', ['ticket' => $ticket])

            <!-- Ticket Hierarchy Tree (Modular Partial) -->
            @include('shared.pages.partials.ticket-hierarchy', ['ticket' => $ticket, 'isAdmin' => $isAdmin])
        </div>
    </div>

    <!-- Bottom Section: Record History Tabs -->
    <section class="ticket-history-tabs mt-8" aria-labelledby="ticket-history-title">
        <header class="ticket-history-header mb-4">
            <p class="eyebrow text-xs font-bold text-emerald-700 uppercase tracking-wider">Ticket record</p>
            <h2 id="ticket-history-title" class="text-xl font-bold text-slate-900 tracking-tight mt-1 font-sans">Activity & service history</h2>
            <p class="text-sm text-slate-500 mt-1">Follow status changes, team handovers, endorsements, submitted field reports, and communication remarks.</p>
        </header>
        <nav class="ticket-history-nav" aria-label="Ticket record history tabs" role="tablist">
            <a href="#status-history" role="tab" id="tab-status-history" data-tab-target="status-history" aria-controls="status-history" aria-selected="true" aria-current="page">
                Status changes <span>{{ $ticket->statusLog->count() }}</span>
            </a>
            <a href="#assignment-history" role="tab" id="tab-assignment-history" data-tab-target="assignment-history" aria-controls="assignment-history" aria-selected="false">
                Team assignments <span>{{ $ticket->assignments->count() }}</span>
            </a>
            <a href="#endorsement-history" role="tab" id="tab-endorsement-history" data-tab-target="endorsement-history" aria-controls="endorsement-history" aria-selected="false">
                Endorsements <span>{{ $ticket->endorsements->count() }}</span>
            </a>
            <a href="#accomplishment-history" role="tab" id="tab-accomplishment-history" data-tab-target="accomplishment-history" aria-controls="accomplishment-history" aria-selected="false">
                Field reports <span>{{ $ticket->accomplishments->count() }}</span>
            </a>
            <a href="#remark-history" role="tab" id="tab-remark-history" data-tab-target="remark-history" aria-controls="remark-history" aria-selected="false">
                Activity remarks <span>{{ $ticket->remarks->count() }}</span>
            </a>
        </nav>

        <x-ticket-history-table id="status-history" title="Status changes"
            description="How this ticket progressed through the service workflow."
            :count="$ticket->statusLog->count()" :columns="['Recorded', 'Status transition', 'Updated by']">
            @forelse($ticket->statusLog as $log)
                <tr>
                    <td class="history-date"><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M d, Y') }}<small>{{ $log->created_at->format('h:i A') }}</small></time></td>
                    <td>
                        <div class="history-transition flex items-center gap-1.5 flex-wrap">
                            @if($log->old_status)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $log->old_status->badgeClasses() }}">
                                    {{ $log->old_status->label() }}
                                </span>
                                <span aria-hidden="true" class="text-slate-400 font-bold text-xs">→</span><span class="sr-only"> changed to </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $log->new_status->badgeClasses() }}">
                                {{ $log->new_status->label() }}
                            </span>
                        </div>
                    </td>
                    <td>{{ $log->updater->full_name ?? 'System' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="history-empty"><strong>No status changes yet</strong><span>Status updates will appear here as the ticket progresses.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="assignment-history" title="Team assignments"
            description="Assignment and release dates for the teams that handled this ticket."
            :count="$ticket->assignments->count()" :columns="['Assigned', 'Team', 'Assigned by', 'Released']">
            @forelse($ticket->assignments as $assignment)
                <tr>
                    <td class="history-date"><time datetime="{{ $assignment->created_at->toIso8601String() }}">{{ $assignment->created_at->format('M d, Y') }}<small>{{ $assignment->created_at->format('h:i A') }}</small></time></td>
                    <td>
                        <strong>{{ $assignment->team->team_name ?? 'Unknown team' }}</strong>
                        @if($assignment->team?->trashed())
                            <span class="ml-1 text-[10px] bg-slate-100 text-slate-500 border border-slate-200 px-1.5 py-0.5 rounded font-normal italic">Archived</span>
                        @endif
                    </td>
                    <td>{{ $assignment->assigner->full_name ?? 'Unknown user' }}</td>
                    <td class="history-date">
                        @if($assignment->unassigned_at)
                            <time datetime="{{ $assignment->unassigned_at->toIso8601String() }}">{{ $assignment->unassigned_at->format('M d, Y') }}<small>{{ $assignment->unassigned_at->format('h:i A') }}</small></time>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ \App\Enums\TicketStatus::ASSIGNED->badgeClasses() }}">Active Handover</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="history-empty"><strong>No team assignments yet</strong><span>Team handovers will appear here once an assignment is recorded.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="endorsement-history" title="Endorsement requests"
            description="Requests to route this ticket to another department."
            :count="$ticket->endorsements->count()" :columns="['Requested', 'Requested by', 'Target department', 'Decision', 'Details']">
            @forelse($ticket->endorsements as $endorsement)
                <tr>
                    <td class="history-date"><time datetime="{{ $endorsement->created_at->toIso8601String() }}">{{ $endorsement->created_at->format('M d, Y') }}<small>{{ $endorsement->created_at->format('h:i A') }}</small></time></td>
                    <td>{{ $endorsement->creator->full_name ?? 'Unknown user' }}</td>
                    <td>{{ $endorsement->suggestedDepartment->dept_name ?? 'Not specified' }}</td>
                    <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $endorsement->status->badgeClasses() }}">{{ $endorsement->status->label() }}</span></td>
                    <td>
                        @if(! $isAdmin)
                            <a class="history-report-link" href="{{ route('cwd.endorsements.show', ['endorsement' => $endorsement, 'source' => 'ticket', 'ticket' => $ticket->id]) }}">View endorsement<span class="sr-only"> request</span><span aria-hidden="true"> ↗</span></a>
                        @else
                            <span class="text-xs text-slate-500 font-medium">Recorded</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="history-empty"><strong>No endorsement requests</strong><span>Requests and their decisions will be listed here.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="accomplishment-history" title="Field accomplishments"
            description="Submitted work, review outcomes, and the supporting field reports."
            :count="$ticket->accomplishments->count()" :columns="['Accomplished', 'Field worker', 'Review status', 'Remarks', 'Report']">
            @forelse($ticket->accomplishments as $acc)
                <tr>
                    <td class="history-date"><time datetime="{{ $acc->accomplished_at->toIso8601String() }}">{{ $acc->accomplished_at->format('M d, Y') }}<small>{{ $acc->accomplished_at->format('h:i A') }}</small></time></td>
                    <td>{{ $acc->accomplishedBy->full_name ?? 'Unknown user' }}</td>
                    <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $acc->status->badgeClasses() }}">{{ $acc->status->label() }}</span></td>
                    <td class="history-remarks">{{ $acc->remarks ?: 'No remarks provided.' }}</td>
                    <td>
                        @if(! $isAdmin)
                            <a class="history-report-link" href="{{ route('cwd.tickets.accomplishments.show', array_filter(['ticket' => $ticket, 'accomplishment' => $acc, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')])) }}">View report<span class="sr-only"> by {{ $acc->accomplishedBy->full_name ?? 'unknown user' }} on {{ $acc->accomplished_at->format('M d, Y h:i A') }}</span><span aria-hidden="true"> ↗</span></a>
                        @else
                            <span class="text-xs text-slate-500 font-medium">Recorded</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="history-empty"><strong>No field reports submitted</strong><span>Completed work and supporting reports will appear here.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <!-- Remarks & Communication Thread (Modular Partial) -->
        @include('shared.pages.partials.ticket-remarks-thread', ['ticket' => $ticket, 'isAdmin' => $isAdmin])
    </section>
@endsection
