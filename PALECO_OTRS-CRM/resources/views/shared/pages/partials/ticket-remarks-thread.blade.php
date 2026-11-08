@props(['ticket', 'isAdmin'])

{{-- Re-rendered by the TicketHistoryTabs Livewire component, so new remarks appear live; history-tabs.js restores the open tab after each update --}}
<div id="remark-history" data-tab-panel="remark-history" role="tabpanel" aria-labelledby="tab-remark-history" class="ticket-history-section border border-slate-200 rounded-2xl bg-white shadow-xs overflow-hidden mb-6 hidden">
    <!-- Stream Header -->
    <header class="ticket-history-heading border-b border-slate-100 bg-slate-50/70 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 shadow-2xs">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a.75.75 0 0 1-.974-.94 4.05 4.05 0 0 0 .524-1.923C3.376 16.713 2.25 14.49 2.25 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 id="remark-history-heading" class="text-sm font-bold text-slate-800">Operational Remarks & Notes</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100/80 text-emerald-800 border border-emerald-200">
                        {{ number_format($ticket->remarks->count()) }} {{ Str::plural('message', $ticket->remarks->count()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live communication thread between dispatch, supervisors, and mobile field crews</span>
                </p>
            </div>
        </div>
        <a class="ticket-history-back text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors" href="#ticket-history">Back to history ↑</a>
    </header>

    <!-- Remarks History Feed (Scrollable Container with GPU Compositing) -->
    <div class="p-5 sm:p-6 bg-white">
        <div 
            x-data="{
                scrollToBottom(smooth = false) {
                    this.$nextTick(() => {
                        if (smooth) {
                            $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' });
                        } else {
                            $el.scrollTop = $el.scrollHeight;
                        }
                    });
                }
            }" 
            x-init="
                scrollToBottom(false);
                const observer = new MutationObserver(() => {
                    const isNearBottom = ($el.scrollHeight - $el.scrollTop - $el.clientHeight) < 220;
                    if (isNearBottom) {
                        scrollToBottom(true);
                    }
                });
                observer.observe($el, { childList: true, subtree: true });
                $cleanup(() => observer.disconnect());
            "
            x-on:remark-posted.window="scrollToBottom(true)"
            x-on:scroll-remarks-to-bottom.window="scrollToBottom(false)"
            class="space-y-3.5 max-h-[380px] overflow-y-auto pr-1 ticket-remarks-scroll">
            @forelse($ticket->remarks as $remark)
                <div class="ticket-remark-card p-4 rounded-xl border transition-colors {{ $remark->is_internal ? 'bg-amber-50/60 border-amber-200' : 'bg-slate-50/80 border-slate-200/70 hover:border-slate-300' }}">
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
                    <p class="text-sm text-slate-800 whitespace-pre-wrap leading-relaxed pl-9 select-text">{{ $remark->body }}</p>
                </div>
            @empty
                <div class="text-center py-10 border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/40">
                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 text-slate-400 mb-2 shadow-2xs">
                        <x-icons.pencil class="w-5 h-5 text-slate-400" />
                    </div>
                    <h4 class="text-xs font-bold text-slate-700">No communication remarks yet</h4>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        {{ $isAdmin ? 'No operational communication notes recorded for this ticket.' : 'Post an operational update, crew instruction, or customer note using the composer below.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Message Composer Docked at Bottom (AJAX/Fetch Driven, DRY Compliant) -->
    @can('createRemark', $ticket)
        <div class="border-t border-slate-100 bg-slate-50/70 p-4 sm:p-5">
            <form 
                action="{{ route('shared.tickets.remarks.store', $ticket) }}" 
                method="POST" 
                wire:ignore
                data-no-modal
                data-no-lock
                data-ajax
                data-prevent-double-submit="false"
                x-data="{
                    isSubmitting: false,
                    body: '',
                    isInternal: false,
                    errorMessage: '',
                    async submitRemark() {
                        const trimmed = this.body.trim();
                        if (trimmed.length < 2 || this.isSubmitting) return;

                        this.isSubmitting = true;
                        this.errorMessage = '';

                        try {
                            const actionUrl = new URL($el.action, window.location.origin).pathname;
                            const csrfToken = $el.querySelector('input[name=_token]')?.value || document.querySelector('meta[name=csrf-token]')?.content;

                            const response = await fetch(actionUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    _token: csrfToken,
                                    body: trimmed,
                                    is_internal: this.isInternal ? 1 : 0
                                })
                            });

                            if (response.ok) {
                                this.body = '';
                                this.isInternal = false;
                                const textarea = $el.querySelector('textarea[name=body]');
                                if (textarea) textarea.style.height = '';
                                
                                window.dispatchEvent(new CustomEvent('remark-posted'));
                            } else {
                                const data = await response.json().catch(() => ({}));
                                this.errorMessage = data.errors?.body?.[0] || data.message || 'Failed to post remark. Please try again.';
                            }
                        } catch (err) {
                            this.errorMessage = 'Network connection error. Please try again.';
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                }"
                x-on:submit.prevent="submitRemark()">
                @csrf

                <!-- Composer Controls Header -->
                <div class="flex items-center justify-between gap-3 mb-2 px-1">
                    <span class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span>Posting as <strong class="text-slate-800">{{ auth()->user()->full_name }}</strong></span>
                    </span>

                    <!-- Internal Memo Toggle -->
                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-slate-600 hover:text-slate-900 select-none transition-colors">
                        <input type="checkbox" x-model="isInternal" name="is_internal" value="1" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500 h-3.5 w-3.5">
                        <span class="flex items-center gap-1 text-[11px] text-amber-800 font-semibold bg-amber-50 border border-amber-200/80 px-2 py-0.5 rounded-full">
                            <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Internal memo only
                        </span>
                    </label>
                </div>

                <!-- Input Box -->
                <div class="relative flex items-end gap-2 p-2 rounded-xl border border-slate-200 bg-white focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all shadow-2xs">
                    <textarea 
                        x-model="body"
                        x-on:keydown.enter.exact.prevent="submitRemark()"
                        name="body" 
                        rows="2" 
                        required 
                        placeholder="Type an operational update, crew instruction, or note... (Press Enter to send)" 
                        class="w-full text-sm border-0 bg-transparent p-2 text-slate-800 placeholder:text-slate-400 focus:ring-0 focus:outline-none resize-none leading-relaxed"></textarea>

                    <button 
                        type="submit" 
                        class="btn btn-primary text-xs py-2 px-3.5 rounded-lg shadow-xs flex items-center gap-1.5 shrink-0 self-end transition-all" 
                        :disabled="isSubmitting || body.trim().length < 2"
                        :class="{ 'opacity-60 cursor-not-allowed': isSubmitting || body.trim().length < 2 }">
                        <template x-if="!isSubmitting">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 rotate-90" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                </svg>
                                <span>Post Remark</span>
                            </span>
                        </template>
                        <template x-if="isSubmitting">
                            <span class="flex items-center gap-1.5">
                                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Posting...</span>
                            </span>
                        </template>
                    </button>
                </div>

                <template x-if="errorMessage">
                    <p class="text-xs text-rose-500 mt-1.5 font-medium px-1" x-text="errorMessage"></p>
                </template>
            </form>
        </div>
    @else
        <div class="border-t border-slate-100 bg-slate-50/60 p-3.5 text-center text-xs text-slate-400">
            {{ $isAdmin ? 'Operational communication thread is managed by CWD operations.' : 'Ticket is read-only. Further remarks can no longer be added.' }}
        </div>
    @endcan
</div>
