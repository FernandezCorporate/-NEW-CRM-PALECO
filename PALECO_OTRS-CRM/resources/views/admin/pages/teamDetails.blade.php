@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Team Details')

@section('content')
    <!-- Back Navigation -->
    <div class="mb-6">
        <x-contextual-back :default="session('team_list_url', route('admin.teams'))" default-label="Back to Teams" variant="link" />
    </div>

    <x-alert />

    <!-- Page Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <!-- CONTEXT: Team Name -->
            <h1 class="text-2xl font-bold text-slate-800">{{ $team->team_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of the field team and its roster</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3 flex-wrap">
            
            <!-- CONTEXT: Check if Team is Soft Deleted -->
            @if($team->trashed())
                <form action="{{ route('admin.teams.restore', $team) }}" method="POST" class="inline-block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary text-indigo-700 hover:bg-indigo-50 hover:border-indigo-300 border-indigo-200 px-4 py-2 text-sm shadow-xs" data-loading-text="Restoring...">
                        <x-icons.arrow-path class="w-4 h-4 text-indigo-600" />
                        <span>Restore</span>
                    </button>
                </form>
                <a href="{{ route('admin.teams.forceDeleteConfirm', $team) }}" class="btn btn-danger px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.trash class="w-4 h-4" />
                    <span>Force Delete</span>
                </a>
            @else
                <a href="{{ route('admin.teams.editForm', array_filter(['team' => $team, 'source' => request('source') ?: 'details', 'dept' => request('dept'), 'user' => request('user')])) }}" class="btn btn-primary px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.pencil class="w-4 h-4" />
                    <span>Edit</span>
                </a>

                <a href="{{ route('admin.teams.deleteConfirm', array_filter(['team' => $team, 'source' => request('source') ?: 'details', 'dept' => request('dept'), 'user' => request('user')])) }}" class="btn btn-secondary text-rose-700 hover:bg-rose-50 hover:border-rose-300 border-rose-200 px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.trash class="w-4 h-4 text-rose-600" />
                    <span>Archive</span>
                </a>
            @endif
        </div>
    </header>

    <!-- Top Section: Grid Layout for Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Left Column: Primary Details (Semantic Definition List) -->
        <section class="lg:col-span-2 space-y-6" aria-labelledby="team-info-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="team-info-heading" class="text-base font-bold text-slate-800">Team Information</h2>
                </div>
                
                <dl class="p-6 space-y-6">
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Team Name</dt>
                        <dd class="text-base text-slate-900 font-medium">{{ $team->team_name }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Department</dt>
                        <dd>
                            @if($team->department)
                                <a href="{{ route('admin.departments.show', ['dept' => $team->department, 'source' => 'team', 'team' => $team->id]) }}" class="text-base text-emerald-700 hover:underline font-bold tracking-wide uppercase inline-flex items-center gap-1.5 group">
                                    {{ $team->department->dept_name }}
                                    <x-icons.chevron-right class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                                </a>
                            @else
                                <span class="text-base text-slate-500 font-medium">Unassigned</span>
                            @endif
                        </dd>
                    </div>
                    
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</dt>
                        <dd class="text-base text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
                            {{ $team->team_desc ?? 'No description provided.' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Right Column: Meta & Stats (Semantic Definition List) -->
        <section class="space-y-6" aria-labelledby="operational-details-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="operational-details-heading" class="text-base font-bold text-slate-800">Operational Details</h2>
                </div>
                
                <dl class="p-6 space-y-4 text-sm divide-y divide-slate-100">
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Status</dt>
                        <dd>
                            @if($team->trashed())
                                <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Archived</span>
                            @else
                                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                            @endif
                        </dd>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Shift Schedule</dt>
                        <dd>
                            <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-800 font-bold px-2.5 py-1 rounded-md text-xs border border-slate-200">
                                <x-icons.clock class="w-3.5 h-3.5 text-slate-500" />
                                {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                            </span>
                        </dd>
                    </div>

                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Date Created</dt>
                        <dd class="text-slate-800">{{ $team->created_at->format('M d, Y') }}</dd>
                    </div>
                    
                    @if($team->trashed())
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-slate-500 font-medium">Date Archived</dt>
                            <dd class="text-slate-800">{{ $team->deleted_at->format('M d, Y - H:i') }}</dd>
                        </div>
                    @endif
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Last Modified</dt>
                        <dd class="text-slate-800">{{ $team->updated_at->format('M d, Y - H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <!-- Record Tabs Section -->
    <section class="ticket-history-tabs mt-8" aria-labelledby="team-records-title">
        <header class="ticket-history-header mb-4">
            <p class="eyebrow text-xs font-bold text-emerald-700 uppercase tracking-wider">Team records</p>
            <h2 id="team-records-title" class="text-xl font-bold text-slate-900 tracking-tight mt-1 font-sans">Roster & assigned tickets</h2>
            <p class="text-sm text-slate-500 mt-1">Review active personnel members and service tickets currently assigned to this team.</p>
        </header>

        <nav class="ticket-history-nav" aria-label="Team records tabs" role="tablist">
            <a href="#roster-tab" role="tab" id="tab-roster" data-tab-target="roster-tab" aria-controls="roster-tab" aria-selected="true" aria-current="page">
                Team roster <span>{{ $members->total() }}</span>
            </a>
            <a href="#tickets-tab" role="tab" id="tab-tickets" data-tab-target="tickets-tab" aria-controls="tickets-tab" aria-selected="false">
                Assigned tickets <span>{{ $assignedTickets->total() }}</span>
            </a>
        </nav>

        <!-- 1. Team Roster Tab Panel -->
        <x-ticket-history-table 
            id="roster-tab" 
            title="Team Roster" 
            description="Active field personnel and assigned operational roles for {{ $team->team_name }}."
            :count="$members->total()" 
            :columns="['Member', 'Team Role', 'Date Added', 'Actions']"
            back-href="#team-records-title"
            back-label="Back to tabs ↑"
            caption="Team roster for {{ $team->team_name }}"
        >
            @forelse ($members as $member)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0 border border-emerald-100">
                                {{ $member->avatar_initials }}
                            </div>
                            <div class="flex flex-col">
                                <a href="{{ route('admin.users.show', array_filter(['user' => $member, 'source' => 'team', 'team' => $team->id, 'dept' => request('dept')])) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                    {{ $member->full_name }}
                                </a>
                                <span class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                    @if($member->email)
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        {{ $member->email }}
                                    @else
                                        <span class="italic text-slate-400">No email provided</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-bold px-2.5 py-1 rounded-full tracking-wide uppercase">
                            {{ $member->assigned_role_name }}
                        </span>
                    </td>
                    <td class="history-date">
                        <time datetime="{{ $member->pivot->created_at->toIso8601String() }}">
                            {{ $member->pivot->created_at->format('M d, Y') }}
                            <small>{{ $member->pivot->created_at->format('h:i A') }}</small>
                        </time>
                    </td>
                    <td class="text-center">
                        <x-btn href="{{ route('admin.users.show', array_filter(['user' => $member, 'source' => 'team', 'team' => $team->id, 'dept' => request('dept')])) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="View member {{ $member->full_name }} profile">
                            <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                            <span>View</span>
                        </x-btn>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="history-empty">
                        <strong>No members assigned</strong>
                        <span>There are currently no users assigned to this team roster.</span>
                    </td>
                </tr>
            @endforelse

            @if($members->hasPages())
                <x-slot:footer>
                    {{ $members->fragment('roster-tab')->onEachSide(0)->links() }}
                </x-slot:footer>
            @endif
        </x-ticket-history-table>

        <!-- 2. Assigned Tickets Tab Panel -->
        <x-ticket-history-table 
            id="tickets-tab" 
            title="Assigned Tickets" 
            description="Service tickets and complaint tasks currently assigned to {{ $team->team_name }}."
            :count="$assignedTickets->total()" 
            :columns="['Ticket ID', 'Source', 'Full Address', 'Landmark', 'Nature of Complaint', 'Status']"
            back-href="#team-records-title"
            back-label="Back to tabs ↑"
            caption="Service tickets assigned to {{ $team->team_name }}"
            table-class="min-w-[55rem]"
        >
            @forelse ($assignedTickets as $ticket)
                <tr>
                    <td class="history-date">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="font-bold text-emerald-700 text-sm hover:underline font-mono" aria-label="View ticket #{{ $ticket->ticket_number }}">
                            {{ $ticket->ticket_number }}
                        </a>
                        <small>{{ $ticket->reported_at->format('M d, Y') }}</small>
                    </td>
                    <td>
                        <span class="text-sm text-slate-800 font-medium">{{ $ticket->complaint_source->label() }}</span>
                    </td>
                    <td>
                        <span class="text-sm text-slate-800">
                            {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                        </span>
                    </td>
                    <td>
                        <span class="text-sm text-slate-600">{{ $ticket->landmark ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="text-sm text-slate-800 font-medium">
                            {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $ticket->status->badgeClasses() }} text-[11px] font-bold tracking-wide">
                            {{ $ticket->status->label() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="history-empty">
                        <strong>No tickets assigned</strong>
                        <span>There are currently no active tickets routed to this team.</span>
                    </td>
                </tr>
            @endforelse

            @if(method_exists($assignedTickets, 'hasPages') && $assignedTickets->hasPages())
                <x-slot:footer>
                    {{ $assignedTickets->fragment('tickets-tab')->onEachSide(0)->links() }}
                </x-slot:footer>
            @endif
        </x-ticket-history-table>
    </section>
@endsection
