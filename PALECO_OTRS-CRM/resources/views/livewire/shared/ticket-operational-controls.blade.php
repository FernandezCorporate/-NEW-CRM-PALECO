<div>
    @canany(['update', 'createChild', 'cancel', 'forceClose'], $ticket)
        <!-- Shared Ticket Operational Control Panel (Reactive Livewire Island) -->
        <aside aria-label="Ticket operational controls" class="bg-white border border-slate-200 rounded-xl px-5 py-3.5 mb-6 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all duration-300">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0">
                    <x-icons.sliders class="w-4 h-4" />
                </div>
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">Operational Controls</h2>
                    <p class="text-xs text-slate-500">Authorized administrative and dispatch actions for this ticket</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                @can('update', $ticket)
                    <a href="{{ route('cwd.tickets.editForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')])) }}" class="btn btn-secondary text-xs py-2 px-3.5 shadow-2xs">
                        <x-icons.pencil class="w-4 h-4 text-slate-500" />
                        <span>Edit Details</span>
                    </a>
                @endcan

                @can('createChild', $ticket)
                    <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 px-3.5 shadow-2xs">
                        <x-icons.plus class="w-4 h-4" />
                        <span>Create Child Ticket</span>
                    </a>
                @endcan

                @can('cancel', $ticket)
                    <a href="{{ route('cwd.tickets.cancelConfirm', $ticket) }}" class="btn bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs py-2 px-3.5 shadow-2xs transition-colors">
                        <x-icons.x-mark class="w-4 h-4 text-rose-600" />
                        <span>Cancel Ticket</span>
                    </a>
                @endcan

                @can('forceClose', $ticket)
                    <a href="{{ route('cwd.tickets.forceCloseConfirm', $ticket) }}" class="btn bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs py-2 px-3.5 shadow-2xs transition-colors">
                        <x-icons.exclamation-triangle class="w-4 h-4 text-amber-600" />
                        <span>Force Close</span>
                    </a>
                @endcan
            </div>
        </aside>
    @endcanany

    @if($ticket->status->value === 'cancelled')
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 shadow-xs transition-all duration-300">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-rose-100 text-rose-700 shrink-0 mt-0.5">
                    <x-icons.x-mark class="w-5 h-5" />
                </div>
                <div class="flex-grow min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-rose-900">
                            Service Ticket Cancelled
                        </h2>
                        @if($ticket->cancelled_at)
                            <span class="text-xs text-rose-700 font-medium">
                                Cancelled on {{ $ticket->cancelled_at->format('M d, Y h:i A') }}
                            </span>
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-rose-800">
                        <span class="font-semibold text-rose-900">Reason:</span> <span class="whitespace-pre-wrap break-words">{{ $ticket->cancellation_reason ?? 'Cancelled by dispatcher' }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($ticket->status->value === 'closed' && $ticket->is_force_closed)
        <div class="mb-6 p-4 rounded-xl bg-amber-50/80 border border-amber-200 text-amber-950 shadow-xs transition-all duration-300">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-lg bg-amber-100 text-amber-700 shrink-0 mt-0.5">
                    <x-icons.exclamation-triangle class="w-5 h-5" />
                </div>
                <div class="flex-grow min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-amber-900">
                            Administrative Force Closure
                        </h2>
                        @if($ticket->closed_at)
                            <span class="text-xs text-amber-700 font-medium">
                                Closed on {{ $ticket->closed_at->format('M d, Y h:i A') }}
                                @if($ticket->closedBy)
                                    by {{ $ticket->closedBy->first_name }} {{ $ticket->closedBy->last_name }}
                                @endif
                            </span>
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-amber-900">
                        <span class="font-semibold text-amber-950">Justification:</span> <span class="whitespace-pre-wrap break-words">{{ $ticket->closure_reason ?? 'Administratively closed by supervisor' }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

