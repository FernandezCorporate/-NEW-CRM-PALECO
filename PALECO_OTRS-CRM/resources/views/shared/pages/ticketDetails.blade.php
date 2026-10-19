@php
    $layout = $layout ?? (auth()->user()?->role?->slug_identifier === 'admin' ? 'admin.base.base' : 'cwd.base.base');
    $isAdmin = auth()->user()?->role?->slug_identifier === 'admin';
@endphp

@extends($layout)

@section('workspace-kind', 'detail')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('content')

<div>

    <x-alert />

    <!-- Back Navigation -->
    <div class="mb-6">
        <x-contextual-back :default="session('ticket_list_url', $isAdmin ? route('admin.tickets') : route('cwd.tickets'))" default-label="Back to Tickets" variant="link" />
    </div>

    <!-- Header Section: Clean Identity & Informational Metadata -->
    <header class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-slate-900 font-mono tracking-tight whitespace-nowrap">
                        {{ $ticket->ticket_number }}
                    </h1>
                    
                    <!-- Standout Lifecycle Status Badge (Real-time Livewire Island) -->
                    <livewire:shared.ticket-status-badge :ticket="$ticket" />

                    @if($ticket->parent_ticket_id)
                        <span class="text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Child Ticket
                        </span>
                    @endif

                    @if($ticket->status->value === 'closed' && $ticket->is_force_closed)
                        <span class="text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Force Closed
                        </span>
                    @endif

                    @if($isAdmin)
                        <span class="text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Read-Only
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                    <p class="text-sm text-slate-600 font-medium leading-relaxed">{{ $ticket->subject }}</p>
                    @if($agingTelemetry = $ticket->getAgingTelemetry())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $agingTelemetry['badgeClasses'] }} shadow-2xs" title="Time unresolved since creation: {{ $agingTelemetry['duration'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $agingTelemetry['dotClass'] }} animate-pulse"></span>
                            <span>{{ $agingTelemetry['label'] }}</span>
                        </span>
                    @endif
                </div>
        </div>

        <!-- Real-time Operational Controls (Livewire Island) -->
        <livewire:shared.ticket-operational-controls :ticket="$ticket" />
    </header>

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
                    <!-- Complaint Source -->
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Complaint Source</dt>
                        <dd class="text-sm text-slate-800 font-medium">{{ $ticket->complaint_source->label() }}</dd>
                    </div>

                    <!-- Category -->
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Category</dt>
                        <dd class="text-sm text-slate-800 font-medium">
                            <span>{{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}</span>
                            @if(! $ticket->other_category && $ticket->category?->trashed())
                                <span class="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded font-medium italic ml-1">Archived</span>
                            @endif
                        </dd>
                    </div>

                    <!-- Assigned Department -->
                    <div>
                        <dt class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Assigned Department</dt>
                        <dd class="text-sm text-slate-800 font-medium">
                            <span>{{ $ticket->department->dept_name ?? 'Unassigned' }}</span>
                            @if($ticket->department?->trashed())
                                <span class="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded font-medium italic ml-1">Archived</span>
                            @endif
                        </dd>
                    </div>
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
            <!-- Assigned Personnel Card (Real-time Livewire Island) -->
            <livewire:shared.ticket-assigned-personnel :ticket="$ticket" />

            <!-- Ticket Hierarchy Tree (Real-time Livewire Island) -->
            <livewire:shared.ticket-hierarchy :ticket="$ticket" :is-admin="$isAdmin" />
        </div>
    </div>

    <!-- Bottom Section: Record History Tabs (Real-time Livewire Island) -->
    <livewire:shared.ticket-history-tabs :ticket="$ticket" :is-admin="$isAdmin" />
</div>
@endsection
