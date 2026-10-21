<div class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 flex-grow">
    <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
        <x-icons.users class="w-5 h-5 text-emerald-600" />
        Assigned Personnel
    </h2>
    
    <div class="space-y-3">
        <!-- Supervisors List -->
        @php($activeSupervisors = $ticket->department?->supervisors->where('is_active', true) ?? collect())
        @if($activeSupervisors->isNotEmpty())
            @foreach($activeSupervisors as $supervisor)
                <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-lg">
                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0 border border-amber-200">
                        {{ $supervisor->avatar_initials }}
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-slate-800 text-sm truncate">{{ $supervisor->full_name }}</span>
                        <span class="text-[10px] text-slate-500 uppercase tracking-wide truncate">Supervisor • {{ $ticket->department->dept_name }}</span>
                    </div>
                </div>
            @endforeach
        @else
            <div class="p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs text-slate-500 italic text-center">
                No supervisors assigned to this department.
            </div>
        @endif

        <!-- Field Team List -->
        @if($ticket->team)
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
                <div class="flex items-center justify-between mb-3 border-b border-slate-200/80 pb-2">
                    <span class="font-bold text-slate-900 text-sm">{{ $ticket->team->team_name }}</span>
                    @if($ticket->team->trashed())
                        <span class="text-[10px] bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Archived Team</span>
                    @else
                        <span class="text-[10px] bg-slate-200 text-slate-700 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Field Team</span>
                    @endif
                </div>
                <div class="space-y-2">
                    @forelse($ticket->team->members as $member)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] shrink-0 border border-emerald-200">
                                {{ $member->avatar_initials }}
                            </div>
                            <span class="text-xs text-slate-700 font-medium truncate">{{ $member->full_name }}</span>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 italic">No members in this team roster.</div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-sm text-slate-400 text-center flex flex-col items-center justify-center h-24">
                <x-icons.team class="w-6 h-6 text-slate-300 mb-1.5" />
                {{-- The empty state must say why there is no team: waiting, transferred away, or never needed --}}
                @if($ticket->status === \App\Enums\TicketStatus::ENDORSED)
                    <span class="font-medium text-slate-600 text-xs">Work transferred through endorsement</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">The receiving department handles it in a child ticket</span>
                @elseif($ticket->status === \App\Enums\TicketStatus::CANCELLED)
                    <span class="font-medium text-slate-600 text-xs">No team was dispatched</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">The ticket was cancelled before assignment</span>
                @else
                    <span class="font-medium text-slate-600 text-xs">Team not yet assigned</span>
                    <span class="text-[10px] text-slate-400 mt-0.5">Awaiting supervisor dispatch</span>
                @endif
            </div>
        @endif
    </div>
</div>

