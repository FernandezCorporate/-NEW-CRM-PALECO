@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            @php
                $isFromConsumer = request('source') === 'consumer' && request('consumer');
                $isFromParent = request('source') === 'parent' && request('parent');
                $isFromEndorsement = request('source') === 'endorsement' && request('endorsement');
                if ($isFromConsumer) {
                    $backRoute = route('cwd.consumers.show', ['consumer' => request('consumer')]);
                    $backLabel = 'Back to Consumer Profile';
                } elseif ($isFromParent) {
                    $backRoute = route('cwd.tickets.show', ['ticket' => request('parent')]);
                    $backLabel = 'Back to Parent Ticket';
                } elseif ($isFromEndorsement) {
                    $backRoute = route('cwd.endorsements.show', ['endorsement' => request('endorsement')]);
                    $backLabel = 'Back to Endorsement';
                } else {
                    $backRoute = session('ticket_list_url', route('cwd.tickets'));
                    $backLabel = 'Back to Tickets';
                }
            @endphp
            <a href="{{ $backRoute }}" aria-label="{{ $backLabel }}" title="{{ $backLabel }}" class="min-h-11 min-w-11 inline-flex items-center justify-center p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-gray-500 shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900 font-mono tracking-tight">
                        {{ $ticket->ticket_number }}
                    </h1>
                    
                    <!-- Standout Lifecycle Status Badge -->
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow-xs border {{ $ticket->status->badgeClasses() }}">
                        <span class="w-2 h-2 rounded-full {{ $ticket->status->chartClass() }}"></span>
                        {{ $ticket->status->label() }}
                    </span>

                    @if($ticket->parent_ticket_id)
                        <span class="text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                            Child Ticket
                        </span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mt-1 font-medium">{{ $ticket->subject }}</p>
            </div>
        </div>

        <!-- Action & Metadata Controls -->
        <div class="flex flex-wrap items-center gap-3 self-stretch md:self-auto justify-start md:justify-end">
            <!-- Informational Metadata Pills -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Source -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-semibold shadow-xs {{ $ticket->complaint_source->badgeClasses() }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    {{ $ticket->complaint_source->label() }}
                </span>
                <!-- Category -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold shadow-xs">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
                </span>
                <!-- Department -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    {{ $ticket->department->dept_name ?? 'Unassigned' }}
                </span>
            </div>

            <!-- Distinct Action Button for Child Ticket -->
            @can('createChild', $ticket)
                <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition-all hover:shadow focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Create Child Ticket</span>
                </a>
            @endcan
        </div>
    </div>

    <!-- Top Block: Info & Routing -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 items-start">
        
        <!-- Left Column Wrapper -->
        <div class="xl:col-span-2 flex flex-col gap-6">
            
            <!-- CRM Linked Consumer Profile -->
            @if($ticket->consumer)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 border-l-4 border-l-[#008f5d]">
                <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#008f5d]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Linked Consumer Profile
                    </h2>
                    <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-1 rounded border border-emerald-200 uppercase tracking-wider">Verified CRM Match</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-6 gap-x-8">
                    <div class="lg:col-span-2">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Account Name</span>
                        <div class="text-sm font-bold text-gray-900">
                            <a href="{{ route('cwd.consumers.show', ['consumer' => $ticket->consumer, 'source' => 'ticket', 'ticket' => $ticket->id]) }}" class="hover:text-emerald-700 hover:underline inline-flex items-center gap-1 group">
                                {{ $ticket->consumer->name }}
                                <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-emerald-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Status</span>
                        <div class="text-sm font-bold {{ $ticket->consumer->status === 'A' ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $ticket->consumer->status === 'A' ? 'Active' : ($ticket->consumer->status ?? 'Unknown') }}
                        </div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Account Code</span>
                        <div class="text-sm text-gray-800 font-medium font-mono">{{ $ticket->consumer->acct_code }}</div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Account Number</span>
                        <div class="text-sm text-gray-800 font-medium font-mono">{{ $ticket->consumer->acct_no }}</div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Meter Serial</span>
                        <div class="text-sm text-gray-800 font-medium font-mono">{{ $ticket->consumer->meter_serial ?? 'N/A' }}</div>
                    </div>

                    <div class="md:col-span-2 lg:col-span-3">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Registered Billing Address</span>
                        <div class="text-sm text-gray-800 flex items-start gap-2">
                            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            {{ $ticket->consumer->address }}
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Consumer Issue Details (Existing) -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-6">Complaint & Issue Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-y-6 gap-x-8">
                    <!-- Location -->
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Incident Address</span>
                        <div class="flex items-start gap-2 text-gray-800 font-medium">
                            <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                        </div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Landmark</span>
                        <div class="text-sm text-gray-700">{{ $ticket->landmark ?? 'No landmark provided' }}</div>
                    </div>

                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Consumer contact</span>
                        <div class="text-sm text-gray-700">{{ $ticket->consumer_contact ?? 'No contact provided' }}</div>
                    </div>

                    <!-- Complaint -->
                    <div class="md:col-span-3">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Detailed Description</span>
                        <div class="text-sm text-gray-800 bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed whitespace-pre-wrap">{{ $ticket->complaint_description }}</div>
                    </div>

                    <!-- Timestamps -->
                    <div class="md:col-span-2">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Date Logged</span>
                        <div class="flex items-center gap-2 text-gray-800 text-sm">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ $ticket->reported_at->format('M d, Y h:i A') }}
                        </div>
                    </div>

                    <div class="md:col-span-1">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Created By</span>
                        <div class="flex items-center gap-2 text-gray-800 text-sm">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            {{ $ticket->creator->full_name ?? 'System' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients & Endorsement Tree -->
        <div class="flex flex-col gap-6 h-full">
            
            <!-- Recipients Block -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 flex-grow">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Assigned Personnel</h2>
                
                <div class="space-y-3">
                    <!-- Supervisors List -->
                    @if($ticket->department && $ticket->department->supervisors->isNotEmpty())
                        @foreach($ticket->department->supervisors as $supervisor)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ $supervisor->avatar_initials }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-800 text-sm">{{ $supervisor->full_name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wide">Supervisor • {{ $ticket->department->dept_name }}</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-3 bg-gray-50 border border-gray-100 rounded-lg text-sm text-gray-500 italic text-center">No supervisors assigned to this department.</div>
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
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-200 rounded-lg text-sm text-gray-400 text-center flex flex-col items-center justify-center h-24">
                            <svg class="w-6 h-6 text-gray-300 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span class="font-medium text-gray-500 text-xs">Team not yet assigned</span>
                            <span class="text-[10px] text-gray-400 mt-0.5">Awaiting supervisor dispatch</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Ticket Hierarchy Tree -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                        Ticket Hierarchy
                    </h2>
                    @can('createChild', $ticket)
                        <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            New Child
                        </a>
                    @endcan
                </div>

                <div class="space-y-4">
                    @if(is_null($ticket->parent_ticket_id))
                        <!-- This ticket is the PARENT -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin Ticket</span>
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
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Child Tickets ({{ $ticket->childTickets->count() }})</span>
                                <div class="space-y-2 pl-4 border-l-2 border-gray-200 ml-2">
                                    @foreach($ticket->childTickets as $child)
                                        <a href="{{ route('cwd.tickets.show', ['ticket' => $child, 'source' => 'parent', 'parent' => $ticket->id]) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                             <div class="flex items-center justify-between">
                                                 <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $child->ticket_number }}</span>
                                                 <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $child->status->badgeClasses() }}">{{ $child->status->label() }}</span>
                                             </div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $child->department->dept_name ?? 'Unassigned' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    @else
                        <!-- This ticket is a CHILD -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin / Parent Ticket</span>
                            <a href="{{ route('cwd.tickets.show', ['ticket' => $ticket->parentTicket, 'source' => 'child', 'child' => $ticket->id]) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $ticket->parentTicket->ticket_number }}</span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $ticket->parentTicket->status->badgeClasses() }}">{{ $ticket->parentTicket->status->label() }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $ticket->parentTicket->department->dept_name ?? 'Unassigned' }}</div>
                            </a>
                        </div>
                        
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Current Child Ticket</span>
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
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Sub-Child Tickets ({{ $ticket->childTickets->count() }})</span>
                                <div class="space-y-2 pl-4 border-l-2 border-gray-200 ml-2">
                                    @foreach($ticket->childTickets as $subChild)
                                        <a href="{{ route('cwd.tickets.show', ['ticket' => $subChild, 'source' => 'parent', 'parent' => $ticket->id]) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                             <div class="flex items-center justify-between">
                                                 <span class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $subChild->ticket_number }}</span>
                                                 <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $subChild->status->badgeClasses() }}">{{ $subChild->status->label() }}</span>
                                             </div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $subChild->department->dept_name ?? 'Unassigned' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>


        </div>
    </div>

    <section id="ticket-history" class="ticket-history" aria-labelledby="ticket-history-title">
        <header class="ticket-history-intro">
            <p class="eyebrow">Ticket record</p>
            <h2 id="ticket-history-title">Activity & service history</h2>
            <p>Follow status changes, team handovers, endorsements, submitted field reports, and communication remarks.</p>
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
                                <span aria-hidden="true" class="text-gray-400 font-bold text-xs">→</span><span class="sr-only"> changed to </span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $log->new_status->badgeClasses() }}">
                                {{ $log->new_status->label() }}
                            </span>
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
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ \App\Enums\TicketStatus::ASSIGNED->badgeClasses() }}">Active Handover</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="history-empty"><strong>No team assignments yet</strong><span>Team handovers will appear here once an assignment is recorded.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <x-ticket-history-table id="endorsement-history" title="Endorsement requests"
            description="Requests to route this ticket to another department."
            :count="$ticket->endorsements->count()" :columns="['Requested', 'Requested by', 'Target department', 'Decision', 'Details']">
            @forelse($ticket->endorsements as $endorsement)
                <tr>
                    <td class="history-date"><time datetime="{{ $endorsement->created_at->toIso8601String() }}">{{ $endorsement->created_at->format('M d, Y') }}<small>{{ $endorsement->created_at->format('h:i A') }}</small></time></td>
                    <td>{{ $endorsement->creator->full_name ?? 'Unknown user' }}</td>
                    <td>{{ $endorsement->suggestedDepartment->dept_name ?? 'Not specified' }}</td>
                    <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $endorsement->status->badgeClasses() }}">{{ $endorsement->status->label() }}</span></td>
                    <td><a class="history-report-link" href="{{ route('cwd.endorsements.show', ['endorsement' => $endorsement, 'source' => 'ticket', 'ticket' => $ticket->id]) }}">View endorsement<span class="sr-only"> request</span><span aria-hidden="true"> ↗</span></a></td>
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
                    <td><a class="history-report-link" href="{{ route('cwd.tickets.accomplishments.show', array_filter(['ticket' => $ticket, 'accomplishment' => $acc, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')])) }}">View report<span class="sr-only"> by {{ $acc->accomplishedBy->full_name ?? 'unknown user' }} on {{ $acc->accomplished_at->format('M d, Y h:i A') }}</span><span aria-hidden="true"> ↗</span></a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="history-empty"><strong>No field reports submitted</strong><span>Completed work and supporting reports will appear here.</span></td></tr>
            @endforelse
        </x-ticket-history-table>

        <!-- Remarks & Communication Thread (Panel) -->
        <div id="remark-history" data-tab-panel="remark-history" role="tabpanel" aria-labelledby="tab-remark-history" class="ticket-history-section border border-[#dce5df] rounded-xl bg-white shadow-xs overflow-hidden mb-6 hidden">
            <header class="ticket-history-heading border-b border-gray-100 bg-gray-50/50">
                <div>
                    <h3 id="remark-history-heading">Activity remarks & notes <span class="ticket-history-count">{{ number_format($ticket->remarks->count()) }}</span></h3>
                    <p>Chronological operational communication, supervisor instructions, and internal memos.</p>
                </div>
            </header>

            <div class="p-6">
                <!-- Post Remark Form -->
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-xl p-5 mb-8">
                    <div class="flex items-center justify-between mb-3 border-b border-gray-200/60 pb-2.5">
                        <span class="text-xs font-bold text-gray-700 flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#008f5d]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Add Operational Remark
                        </span>
                        @if(auth()->check())
                            <span class="text-[11px] text-gray-500 font-medium">
                                Posting as <strong class="text-gray-800">{{ auth()->user()->full_name }}</strong>
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
                                class="w-full text-sm rounded-lg border-gray-300 bg-white focus:border-[#008f5d] focus:ring-2 focus:ring-[#008f5d]/20 p-3.5 shadow-xs transition-colors placeholder:text-gray-400 text-gray-800"></textarea>
                            @error('body')
                                <p class="text-xs text-rose-500 mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-1">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none py-1">
                                <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-[#008f5d] focus:ring-[#008f5d] h-4 w-4">
                                <span class="text-xs font-medium text-gray-700">Internal memo only <span class="text-gray-400 font-normal">(hide from mobile field crews)</span></span>
                            </label>

                            <button type="submit" class="inline-flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white text-xs font-semibold px-4.5 py-2.5 rounded-lg transition-all shadow-sm active:scale-[0.98]">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                <span>Post Remark</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Remarks Feed -->
                <div class="space-y-4">
                    @forelse($ticket->remarks as $remark)
                        <div class="p-4 rounded-xl border transition-colors {{ $remark->is_internal ? 'bg-amber-50/60 border-amber-200' : 'bg-gray-50/80 border-gray-200/70 hover:border-gray-300' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold text-[11px] flex items-center justify-center shrink-0">
                                        {{ $remark->author->avatar_initials ?? '?' }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-gray-900">{{ $remark->author->full_name ?? 'Unknown User' }}</span>
                                        @if($remark->author?->role)
                                            <span class="text-[10px] text-gray-500 uppercase tracking-wide font-medium">• {{ $remark->author->role->role_name }}</span>
                                        @endif
                                    </div>
                                    @if($remark->is_internal)
                                        <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded border border-amber-300 uppercase tracking-wider">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Internal
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-gray-400 font-medium">{{ $remark->created_at->format('M d, Y h:i A') }}</span>
                            </div>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed pl-9">{{ $remark->body }}</p>
                        </div>
                    @empty
                        <div class="text-center py-10 border-2 border-dashed border-gray-200 rounded-xl">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 text-gray-400 mb-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            </div>
                            <h4 class="text-xs font-bold text-gray-700">No remarks recorded</h4>
                            <p class="text-xs text-gray-400 mt-0.5">Use the form above to add an operational update or internal note.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection