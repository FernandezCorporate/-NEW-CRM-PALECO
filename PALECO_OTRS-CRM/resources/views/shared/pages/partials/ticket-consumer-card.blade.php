@props(['ticket', 'isAdmin'])

@if($ticket->consumer)
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 border-l-4 border-l-emerald-600">
        <div class="flex items-center justify-between mb-5 border-b border-slate-100 pb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <x-icons.user class="w-5 h-5 text-emerald-600" />
                Linked Consumer Profile
            </h2>
            <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded-full border border-emerald-200 uppercase tracking-wider">
                Verified CRM Match
            </span>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-6 gap-x-8">
            <div class="lg:col-span-2">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Account Name</dt>
                <dd class="text-sm font-bold text-slate-900">
                    @if(! $isAdmin)
                        <a href="{{ route('cwd.consumers.show', ['consumer' => $ticket->consumer, 'source' => 'ticket', 'ticket' => $ticket->id]) }}" class="text-emerald-700 hover:text-emerald-800 hover:underline inline-flex items-center gap-1 group">
                            {{ $ticket->consumer->name }}
                            <x-icons.chevron-right class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                        </a>
                    @else
                        <span class="text-slate-900">{{ $ticket->consumer->name }}</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Status</dt>
                <dd class="text-sm font-bold {{ $ticket->consumer->status === 'A' ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $ticket->consumer->status === 'A' ? 'Active' : ($ticket->consumer->status ?? 'Unknown') }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Account Code</dt>
                <dd class="text-sm text-slate-800 font-medium font-mono">{{ $ticket->consumer->acct_code }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Account Number</dt>
                <dd class="text-sm text-slate-800 font-medium font-mono">{{ $ticket->consumer->acct_no }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Meter Serial</dt>
                <dd class="text-sm text-slate-800 font-medium font-mono">{{ $ticket->consumer->meter_serial ?? 'N/A' }}</dd>
            </div>

            <div class="md:col-span-2 lg:col-span-3">
                <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Registered Billing Address</dt>
                <dd class="text-sm text-slate-800 flex items-start gap-2">
                    <x-icons.map-pin class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" />
                    <span>{{ $ticket->consumer->address }}</span>
                </dd>
            </div>
        </dl>
    </div>
@endif

