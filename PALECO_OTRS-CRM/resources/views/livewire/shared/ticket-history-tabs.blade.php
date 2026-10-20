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
                        @if($ticket->is_force_closed && $log->new_status->value === 'closed')
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                Force Closed
                            </span>
                        @endif
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
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border bg-purple-50 text-purple-700 border-purple-200">Active Handover</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="history-empty"><strong>No team assignments yet</strong><span>Team handovers will appear here once an assignment is recorded.</span></td></tr>
        @endforelse
    </x-ticket-history-table>

    <x-ticket-history-table id="endorsement-history" title="Endorsement requests"
        description="Requests to route this ticket to another department."
        :count="$ticket->endorsements->count()" :columns="['Requested', 'Requested by', 'From &rarr; To', 'Decision', 'Details']">
        @forelse($ticket->endorsements as $endorsement)
            <tr>
                <td class="history-date"><time datetime="{{ $endorsement->created_at->toIso8601String() }}">{{ $endorsement->created_at->format('M d, Y') }}<small>{{ $endorsement->created_at->format('h:i A') }}</small></time></td>
                <td>{{ $endorsement->creator->full_name ?? 'Unknown user' }}</td>
                <td>
                    <span class="text-slate-800 font-medium">{{ $endorsement->oldDepartment->dept_name ?? 'Unknown' }}</span>
                    <small class="block {{ $endorsement->newDepartment ? 'text-slate-600' : 'text-slate-400 italic' }}">
                        &rarr; {{ $endorsement->newDepartment->dept_name ?? ($endorsement->status === \App\Enums\EndorsementStatus::PENDING ? 'Awaiting decision' : 'Not transferred') }}
                    </small>
                </td>
                <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $endorsement->status->badgeClasses() }}">{{ $endorsement->status->label() }}</span></td>
                <td>
                    @if(! $isAdmin)
                        <x-btn href="{{ route('cwd.endorsements.show', ['endorsement' => $endorsement, 'source' => 'ticket', 'ticket' => $ticket->id]) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="View endorsement request">
                            <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                            <span>View</span>
                        </x-btn>
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
                        <x-btn href="{{ route('cwd.tickets.accomplishments.show', array_filter(['ticket' => $ticket, 'accomplishment' => $acc, 'source' => $source, 'consumer' => $consumer, 'parent' => $parent])) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="View field accomplishment report by {{ $acc->accomplishedBy->full_name ?? 'unknown user' }} on {{ $acc->accomplished_at->format('M d, Y h:i A') }}">
                            <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                            <span>View</span>
                        </x-btn>
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

