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

        <!-- Informational Metadata Pills -->
        <div class="flex items-center gap-2 flex-wrap shrink-0">
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
    </header>

    <!-- Real-time Operational Controls & Terminal Notices (Livewire Island) -->
    <livewire:shared.ticket-operational-controls :ticket="$ticket" />

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
