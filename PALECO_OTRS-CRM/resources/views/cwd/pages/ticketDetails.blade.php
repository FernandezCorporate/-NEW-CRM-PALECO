@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('cwd.tickets') }}" aria-label="Back to tickets" class="min-h-11 min-w-11 inline-flex items-center justify-center p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex flex-wrap items-center gap-2">
                    {{ $ticket->ticket_number }}
                    @if($ticket->parent_ticket_id)
                        <span class="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded uppercase tracking-wide">Child Ticket</span>
                    @endif
                </h1>
                <p class="text-sm text-gray-500 mt-1">{{ $ticket->subject }}</p>
            </div>
        </div>

        <!-- Dynamic Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Blue: Source -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                {{ $ticket->complaint_source->label() }}
            </span>
            <!-- Red: Category -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 border border-red-200 text-red-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
            </span>
            <!-- Green: Department -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                {{ $ticket->department->dept_name ?? 'Unassigned' }}
            </span>
        </div>
    </div>

    <!-- Top Block: Info & Routing -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 items-start">
        
        <!-- Consumer Issue Details -->
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-6">Complaint & Issue Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                <!-- Location -->
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Address</span>
                    <div class="flex items-start gap-2 text-gray-800 font-medium">
                        <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Landmark</span>
                    <div class="text-sm text-gray-700">{{ $ticket->landmark ?? 'No landmark provided' }}</div>
                </div>

                <!-- Complaint -->
                <div class="md:col-span-2">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Detailed Description</span>
                    <div class="text-sm text-gray-800 bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed whitespace-pre-wrap">
                        {{ $ticket->complaint_description }}
                    </div>
                </div>

                <!-- Timestamps -->
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Date Logged</span>
                    <div class="flex items-center gap-2 text-gray-800 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ $ticket->reported_at->format('M d, Y h:i A') }}
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Created By</span>
                    <div class="flex items-center gap-2 text-gray-800 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        {{ $ticket->creator->full_name ?? 'System' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients & Escalation Tree -->
        <div class="flex flex-col gap-6 h-full">
            
            <!-- Recipients Block -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 flex-grow">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Assigned Personnel</h2>
                
                <div class="space-y-3">
                    <!-- Foremen List -->
                    @if($ticket->department && $ticket->department->foremen->isNotEmpty())
                        @foreach($ticket->department->foremen as $foreman)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ $foreman->avatar_initials }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-800 text-sm">{{ $foreman->full_name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wide">Foreman • {{ $ticket->department->dept_name }}</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-3 bg-gray-50 border border-gray-100 rounded-lg text-sm text-gray-500 italic text-center">No foremen assigned to this department.</div>
                    @endif

                    <!-- Field Team List -->
                    @if($ticket->team)
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between mb-3 border-b border-gray-200 pb-2">
                                <span class="font-bold text-gray-900 text-sm">{{ $ticket->team->team_name }}</span>
                                <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Field Team</span>
                            </div>
                            <div class="space-y-2">
                                @forelse($ticket->team->members as $member)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                            {{ $member->avatar_initials }}
                                        </div>
                                        <span class="text-xs text-gray-700 font-medium">{{ $member->full_name }}</span>
                                    </div>
                                @empty
                                    <div class="text-xs text-gray-400 italic">No members in this team.</div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 text-center flex flex-col items-center justify-center h-24">
                            <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Team not yet assigned
                        </div>
                    @endif
                </div>
            </div>

            <!-- Escalation Tree -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 shrink-0">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                    Escalation Tree
                </h2>

                <div class="space-y-4">
                    @if(is_null($ticket->parent_ticket_id))
                        <!-- This ticket is the PARENT -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin Ticket</span>
                            <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                                <div class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</div>
                                <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                            </div>
                        </div>

                        @if($ticket->childTickets->isNotEmpty())
                            <div>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Escalated To (Child Tickets)</span>
                                <div class="space-y-2 pl-4 border-l-2 border-gray-200 ml-2">
                                    @foreach($ticket->childTickets as $child)
                                        <a href="{{ route('cwd.tickets.show', $child) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                            <div class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $child->ticket_number }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $child->department->dept_name ?? 'Unassigned' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    @else
                        <!-- This ticket is a CHILD -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin Ticket</span>
                            <a href="{{ route('cwd.tickets.show', $ticket->parentTicket) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                <div class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $ticket->parentTicket->ticket_number }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $ticket->parentTicket->department->dept_name ?? 'Unassigned' }}</div>
                            </a>
                        </div>
                        
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Escalated Ticket</span>
                            <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg pl-4 ml-2">
                                <div class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</div>
                                <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <section id="ticket-history" class="ticket-history" aria-labelledby="ticket-history-title">
        <header class="ticket-history-intro">
            <p class="eyebrow">Ticket record</p>
            <h2 id="ticket-history-title">Activity & service history</h2>
            <p>Follow status changes, team handovers, escalations, and submitted field reports.</p>
        </header>
        <nav class="ticket-history-nav" aria-label="Jump to ticket history section">
            <a href="#status-history">Status changes <span>{{ $ticket->statusLog->count() }}</span></a>
            <a href="#assignment-history">Team assignments <span>{{ $ticket->assignments->count() }}</span></a>
            <a href="#escalation-history">Escalations <span>{{ $ticket->escalations->count() }}</span></a>
            <a href="#accomplishment-history">Field reports <span>{{ $ticket->accomplishments->count() }}</span></a>
        </nav>

        <x-ticket-history-table id="status-history" title="Status changes"
            description="How this ticket progressed through the service workflow."
            :count="$ticket->statusLog->count()" :columns="['Recorded', 'Status transition', 'Updated by']">
            @forelse($ticket->statusLog as $log)
                <tr>
                    <td class="history-date"><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('M d, Y') }}<small>{{ $log->created_at->format('h:i A') }}</small></time></td>
                    <td>
                        <div class="history-transition">
                            @if($log->old_status)
                                <span class="history-previous">{{ $log->old_status->label() }}</span>
                                <span aria-hidden="true" class="history-arrow">→</span><span class="sr-only"> changed to </span>
                            @endif
                            <span class="history-status" data-status="{{ $log->new_status->value }}">{{ $log->new_status->label() }}</span>
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
                    <td><strong>{{ $assignment->team->team_name ?? 'Unknown team' }}</strong></td>
                    <td>{{ $assignment->assigner->full_name ?? 'Unknown user' }}</td>
                    <td class="history-date">
                        @if($assignment->unassigned_at)
                            <time datetime="{{ $assignment->unassigned_at->toIso8601String() }}">{{ $assignment->unassigned_at->format('M d, Y') }}<small>{{ $assignment->unassigned_at->format('h:i A') }}</small></time>
                        @else
                            <span class="history-status" data-status="assigned">Not released</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="history-empty"><strong>No team assignments yet</strong><span>Team handovers will appear here once an assignment is recorded.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="escalation-history" title="Escalation requests"
            description="Requests to route this ticket to another department."
            :count="$ticket->escalations->count()" :columns="['Requested', 'Requested by', 'Target department', 'Decision']">
            @forelse($ticket->escalations as $escalation)
                <tr>
                    <td class="history-date"><time datetime="{{ $escalation->created_at->toIso8601String() }}">{{ $escalation->created_at->format('M d, Y') }}<small>{{ $escalation->created_at->format('h:i A') }}</small></time></td>
                    <td>{{ $escalation->creator->full_name ?? 'Unknown user' }}</td>
                    <td>{{ $escalation->suggestedDepartment->dept_name ?? 'Not specified' }}</td>
                    <td><span class="history-status" data-status="{{ $escalation->status->value }}">{{ $escalation->status->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="history-empty"><strong>No escalation requests</strong><span>Requests and their decisions will be listed here.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="accomplishment-history" title="Field accomplishments"
            description="Submitted work, review outcomes, and the supporting field reports."
            :count="$ticket->accomplishments->count()" :columns="['Accomplished', 'Field worker', 'Review status', 'Remarks', 'Report']">
            @forelse($ticket->accomplishments as $acc)
                <tr>
                    <td class="history-date"><time datetime="{{ $acc->accomplished_at->toIso8601String() }}">{{ $acc->accomplished_at->format('M d, Y') }}<small>{{ $acc->accomplished_at->format('h:i A') }}</small></time></td>
                    <td>{{ $acc->accomplishedBy->full_name ?? 'Unknown user' }}</td>
                    <td><span class="history-status" data-status="{{ $acc->status->value }}">{{ $acc->status->label() }}</span></td>
                    <td class="history-remarks">{{ $acc->remarks ?: 'No remarks provided.' }}</td>
                    <td><a class="history-report-link" href="{{ route('cwd.tickets.accomplishments.show', ['ticket' => $ticket, 'accomplishment' => $acc]) }}">View report<span class="sr-only"> by {{ $acc->accomplishedBy->full_name ?? 'unknown user' }} on {{ $acc->accomplished_at->format('M d, Y h:i A') }}</span><span aria-hidden="true"> ↗</span></a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="history-empty"><strong>No field reports submitted</strong><span>Completed work and supporting reports will appear here.</span></td></tr>
            @endforelse
        </x-ticket-history-table>
    </section>
@endsection
