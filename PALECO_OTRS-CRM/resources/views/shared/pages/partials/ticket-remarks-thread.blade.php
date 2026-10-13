@props(['ticket', 'isAdmin'])

<div id="remark-history" data-tab-panel="remark-history" role="tabpanel" aria-labelledby="tab-remark-history" class="ticket-history-section border border-slate-200 rounded-xl bg-white shadow-xs overflow-hidden mb-6 hidden">
    <header class="ticket-history-heading border-b border-slate-100 bg-slate-50/50">
        <div>
            <h3 id="remark-history-heading">Activity remarks & notes <span class="ticket-history-count">{{ number_format($ticket->remarks->count()) }}</span></h3>
            <p>Chronological operational communication, supervisor instructions, and internal memos.</p>
        </div>
        <a class="ticket-history-back" href="#ticket-history">Back to history ↑</a>
    </header>

    <div class="p-6">
        <!-- Post Remark Form: Restricted to authorized roles (CWD only via clean TicketPolicy check) -->
        @can('createRemark', $ticket)
            <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-5 mb-8">
                <div class="flex items-center justify-between mb-3 border-b border-slate-200/60 pb-2.5">
                    <span class="text-xs font-bold text-slate-700 flex items-center gap-2">
                        <x-icons.pencil class="w-4 h-4 text-emerald-600" />
                        Add Operational Remark
                    </span>
                    @if(auth()->check())
                        <span class="text-[11px] text-slate-500 font-medium">
                            Posting as <strong class="text-slate-800">{{ auth()->user()->full_name }}</strong>
                        </span>
                    @endif
                </div>

                <form action="{{ route('shared.tickets.remarks.store', $ticket) }}#remark-history" method="POST">
                    @csrf
                    <div class="mb-3">
                        <textarea 
                            name="body" 
                            rows="3" 
                            required 
                            placeholder="Add an operational update, dispatch instructions, or customer communication note..." 
                            class="form-input w-full min-h-[96px] text-sm rounded-lg border border-slate-300 bg-white hover:border-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 p-3.5 shadow-xs transition-all placeholder:text-slate-400 text-slate-800 outline-none @error('body') !border-rose-500 !ring-2 !ring-rose-500/20 @enderror"></textarea>
                        @error('body')
                            <p class="text-xs text-rose-500 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none py-1">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                            <span class="text-xs font-medium text-slate-700">Internal memo only <span class="text-slate-400 font-normal">(hide from mobile field crews)</span></span>
                        </label>

                        <button type="submit" class="btn btn-primary text-xs py-2 px-4 shadow-xs">
                            <x-icons.arrow-path class="w-3.5 h-3.5" />
                            <span>Post Remark</span>
                        </button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- Remarks Feed -->
        <div class="space-y-4">
            @forelse($ticket->remarks as $remark)
                <div class="p-4 rounded-xl border transition-colors {{ $remark->is_internal ? 'bg-amber-50/60 border-amber-200' : 'bg-slate-50/80 border-slate-200/70 hover:border-slate-300' }}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold text-[11px] flex items-center justify-center shrink-0 border border-slate-300">
                                {{ $remark->author->avatar_initials ?? '?' }}
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-slate-900">{{ $remark->author->full_name ?? 'Unknown User' }}</span>
                                @if($remark->author?->role)
                                    <span class="text-[10px] text-slate-500 uppercase tracking-wide font-medium">• {{ $remark->author->role->role_name }}</span>
                                @endif
                            </div>
                            @if($remark->is_internal)
                                <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-300 uppercase tracking-wider">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    Internal
                                </span>
                            @endif
                        </div>
                        <span class="text-[11px] text-slate-400 font-medium">{{ $remark->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                    <p class="text-sm text-slate-800 whitespace-pre-wrap leading-relaxed pl-9">{{ $remark->body }}</p>
                </div>
            @empty
                <div class="text-center py-10 border-2 border-dashed border-slate-200 rounded-xl">
                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-400 mb-2">
                        <x-icons.pencil class="w-5 h-5 text-slate-400" />
                    </div>
                    <h4 class="text-xs font-bold text-slate-700">No remarks recorded</h4>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $isAdmin ? 'No operational communication notes recorded for this ticket.' : 'Use the form above to add an operational update or internal note.' }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

