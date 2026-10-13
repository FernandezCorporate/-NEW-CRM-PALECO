@props(['ticket', 'isAdmin'])

<div class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 shrink-0">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <x-icons.ticket class="w-5 h-5 text-indigo-600" />
            Ticket Hierarchy
        </h2>
        @can('createChild', $ticket)
            <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                <x-icons.plus class="w-3.5 h-3.5" />
                New Child
            </a>
        @endcan
    </div>

    <div class="space-y-4">
        @if(is_null($ticket->parent_ticket_id))
            <!-- Origin / Parent Ticket -->
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Origin Ticket</span>
                <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                    </div>
                    <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                </div>
            </div>

            @if($ticket->childTickets->isNotEmpty())
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Child Tickets ({{ $ticket->childTickets->count() }})</span>
                    <div class="space-y-2 pl-4 border-l-2 border-slate-200 ml-2">
                        @foreach($ticket->childTickets as $child)
                            <a href="{{ route($isAdmin ? 'admin.tickets.show' : 'cwd.tickets.show', ['ticket' => $child, 'source' => 'parent', 'parent' => $ticket->id]) }}" class="block p-3 bg-slate-50 border border-slate-200 rounded-lg hover:border-indigo-300 hover:shadow-xs transition-all group">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $child->ticket_number }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $child->status->badgeClasses() }}">{{ $child->status->label() }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $child->department->dept_name ?? 'Unassigned' }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        @else
            <!-- Current ticket is a CHILD -->
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Origin / Parent Ticket</span>
                <a href="{{ route($isAdmin ? 'admin.tickets.show' : 'cwd.tickets.show', ['ticket' => $ticket->parentTicket, 'source' => 'child', 'child' => $ticket->id]) }}" class="block p-3 bg-slate-50 border border-slate-200 rounded-lg hover:border-indigo-300 hover:shadow-xs transition-all group">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $ticket->parentTicket->ticket_number }}</span>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $ticket->parentTicket->status->badgeClasses() }}">{{ $ticket->parentTicket->status->label() }}</span>
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ $ticket->parentTicket->department->dept_name ?? 'Unassigned' }}</div>
                </a>
            </div>
            
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Current Child Ticket</span>
                <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg pl-4 ml-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                    </div>
                    <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                </div>
            </div>

            @if($ticket->childTickets->isNotEmpty())
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Sub-Child Tickets ({{ $ticket->childTickets->count() }})</span>
                    <div class="space-y-2 pl-4 border-l-2 border-slate-200 ml-2">
                        @foreach($ticket->childTickets as $subChild)
                            <a href="{{ route($isAdmin ? 'admin.tickets.show' : 'cwd.tickets.show', ['ticket' => $subChild, 'source' => 'parent', 'parent' => $ticket->id]) }}" class="block p-3 bg-slate-50 border border-slate-200 rounded-lg hover:border-indigo-300 hover:shadow-xs transition-all group">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $subChild->ticket_number }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $subChild->status->badgeClasses() }}">{{ $subChild->status->label() }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ $subChild->department->dept_name ?? 'Unassigned' }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

