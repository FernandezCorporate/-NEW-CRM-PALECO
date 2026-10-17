@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Department Details')

@section('content')
    <!-- Back Navigation -->
    <div class="mb-6">
        <x-contextual-back :default="session('department_list_url', route('admin.departments'))" default-label="Back to Departments" variant="link" />
    </div>

    <x-alert />

    <!-- Page Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $dept->dept_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of department information and assignments</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3 flex-wrap">
            
            @if($dept->trashed())
                <form action="{{ route('admin.departments.restore', $dept) }}" method="POST" class="inline-block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary text-indigo-700 hover:bg-indigo-50 hover:border-indigo-300 border-indigo-200 px-4 py-2 text-sm shadow-xs" data-loading-text="Restoring...">
                        <x-icons.arrow-path class="w-4 h-4 text-indigo-600" />
                        <span>Restore</span>
                    </button>
                </form>
                <a href="{{ route('admin.departments.forceDeleteConfirm', $dept) }}" class="btn btn-danger px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.trash class="w-4 h-4" />
                    <span>Force Delete</span>
                </a>
            @else
                <a href="{{ route('admin.departments.editForm', array_filter(['dept' => $dept, 'source' => request('source') ?: 'details', 'user' => request('user'), 'team' => request('team')])) }}" class="btn btn-primary px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.pencil class="w-4 h-4" />
                    <span>Edit</span>
                </a>

                <a href="{{ route('admin.departments.deleteConfirm', array_filter(['dept' => $dept, 'source' => request('source') ?: 'details', 'user' => request('user'), 'team' => request('team')])) }}" class="btn btn-secondary text-rose-700 hover:bg-rose-50 hover:border-rose-300 border-rose-200 px-4 py-2 text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.trash class="w-4 h-4 text-rose-600" />
                    <span>Archive</span>
                </a>
            @endif
        </div>
    </header>

    <!-- Top Section: Grid Layout for Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Left Column: Primary Details (Semantic Definition List) -->
        <section class="lg:col-span-2 space-y-6" aria-labelledby="general-info-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="general-info-heading" class="text-base font-bold text-slate-800">General Information</h2>
                </div>
                
                <dl class="p-6 space-y-6">
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Department Name</dt>
                        <dd class="text-base text-slate-900 font-medium">{{ $dept->dept_name }}</dd>
                    </div>
                    
                    <div>
                        <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</dt>
                        <dd class="text-base text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
                            {{ $dept->dept_desc ?? 'No description provided.' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Right Column: Meta & Stats (Semantic Definition List) -->
        <section class="space-y-6" aria-labelledby="system-details-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="system-details-heading" class="text-base font-bold text-slate-800">System Details</h2>
                </div>
                
                <dl class="p-6 space-y-4 text-sm divide-y divide-slate-100">
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Status</dt>
                        <dd>
                            @if($dept->trashed())
                                <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Archived</span>
                            @else
                                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                            @endif
                        </dd>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Active Field Personnel</dt>
                        <dd class="text-slate-800 font-bold bg-slate-100 px-2.5 py-1 rounded-md">{{ $personnelCount }}</dd>
                    </div>

                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Date Created</dt>
                        <dd class="text-slate-800">{{ $dept->created_at->format('M d, Y') }}</dd>
                    </div>
                    
                    @if($dept->trashed())
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-slate-500 font-medium">Date Archived</dt>
                            <dd class="text-slate-800">{{ $dept->deleted_at->format('M d, Y - H:i') }}</dd>
                        </div>
                    @endif
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Last Modified</dt>
                        <dd class="text-slate-800">{{ $dept->updated_at->format('M d, Y - H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <!-- Record Tabs Section -->
    <section class="ticket-history-tabs mt-8" aria-labelledby="department-records-title">
        <header class="ticket-history-header mb-4">
            <p class="eyebrow text-xs font-bold text-emerald-700 uppercase tracking-wider">Department records</p>
            <h2 id="department-records-title" class="text-xl font-bold text-slate-900 tracking-tight mt-1 font-sans">Associated units & service tickets</h2>
            <p class="text-sm text-slate-500 mt-1">Review assigned supervisors, operational field teams, and service tickets routed to this department.</p>
        </header>

        <nav class="ticket-history-nav" aria-label="Department records tabs" role="tablist">
            <a href="#supervisors-tab" role="tab" id="tab-supervisors" data-tab-target="supervisors-tab" aria-controls="supervisors-tab" aria-selected="true" aria-current="page">
                Assigned supervisors <span>{{ $assignedSupervisor->total() ?? $supervisorCount }}</span>
            </a>
            <a href="#teams-tab" role="tab" id="tab-teams" data-tab-target="teams-tab" aria-controls="teams-tab" aria-selected="false">
                Assigned teams <span>{{ $assignedTeams->total() }}</span>
            </a>
            <a href="#tickets-tab" role="tab" id="tab-tickets" data-tab-target="tickets-tab" aria-controls="tickets-tab" aria-selected="false">
                Department tickets <span>{{ $assignedTickets->total() }}</span>
            </a>
        </nav>

        <!-- 1. Supervisors Tab Panel -->
        <x-ticket-history-table 
            id="supervisors-tab" 
            title="Assigned Supervisors" 
            description="Active supervisors managing personnel and operations for {{ $dept->dept_name }}."
            :count="$assignedSupervisor->total() ?? $supervisorCount" 
            :columns="['Supervisor Name', 'Contact Number', 'Actions']"
            back-href="#department-records-title"
            back-label="Back to tabs ↑"
            caption="Assigned supervisors for {{ $dept->dept_name }}"
        >
            @forelse ($assignedSupervisor as $supervisor)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0 border border-emerald-100">
                                {{ $supervisor->avatar_initials }}
                            </div>
                            <div class="flex flex-col">
                                <a href="{{ route('admin.users.show', ['user' => $supervisor, 'source' => 'department', 'dept' => $dept->id]) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                    {{ $supervisor->full_name }}
                                </a>
                                <span class="text-xs text-slate-400 mt-0.5 font-mono">{{ $supervisor->username }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="text-sm text-slate-600">{{ $supervisor->contact ?? '—' }}</span>
                    </td>
                    <td class="text-center">
                        <x-btn href="{{ route('admin.users.show', ['user' => $supervisor, 'source' => 'department', 'dept' => $dept->id]) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="View supervisor {{ $supervisor->full_name }} profile">
                            <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                            <span>View</span>
                        </x-btn>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="history-empty">
                        <strong>No supervisors assigned</strong>
                        <span>There are no active supervisors linked to this department.</span>
                    </td>
                </tr>
            @endforelse

            @if($assignedSupervisor->hasPages())
                <x-slot:footer>
                    {{ $assignedSupervisor->fragment('supervisors-tab')->onEachSide(0)->links() }}
                </x-slot:footer>
            @endif
        </x-ticket-history-table>

        <!-- 2. Teams Tab Panel -->
        <x-ticket-history-table 
            id="teams-tab" 
            title="Assigned Teams" 
            description="Operational field teams deployed under {{ $dept->dept_name }}."
            :count="$assignedTeams->total()" 
            :columns="['Team Name', 'Shift Schedule', 'Members', 'Actions']"
            back-href="#department-records-title"
            back-label="Back to tabs ↑"
            caption="Assigned teams for {{ $dept->dept_name }}"
        >
            @forelse ($assignedTeams as $team)
                <tr>
                    <td>
                        <a href="{{ route('admin.teams.show', ['team' => $team, 'source' => 'department', 'dept' => $dept->id]) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                            {{ $team->team_name }}
                        </a>
                        @if($team->trashed())
                            <span class="ml-1 text-[10px] bg-slate-100 text-slate-500 border border-slate-200 px-1.5 py-0.5 rounded font-normal italic">Archived</span>
                        @endif
                    </td>
                    <td>
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-200">
                            <x-icons.clock class="w-3.5 h-3.5 text-slate-500" />
                            {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $team->members_count }}
                        </span>
                    </td>
                    <td class="text-center">
                        <x-btn href="{{ route('admin.teams.show', ['team' => $team, 'source' => 'department', 'dept' => $dept->id]) }}" variant="secondary" size="sm" class="action-link inline-flex items-center gap-1.5 shadow-2xs" aria-label="View team {{ $team->team_name }} details">
                            <x-icons.eye class="w-3.5 h-3.5 text-slate-500" />
                            <span>View</span>
                        </x-btn>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="history-empty">
                        <strong>No teams assigned</strong>
                        <span>There are no operational teams assigned to this department.</span>
                    </td>
                </tr>
            @endforelse

            @if($assignedTeams->hasPages())
                <x-slot:footer>
                    {{ $assignedTeams->fragment('teams-tab')->onEachSide(0)->links() }}
                </x-slot:footer>
            @endif
        </x-ticket-history-table>

        <!-- 3. Department Tickets Tab Panel -->
        <x-ticket-history-table 
            id="tickets-tab" 
            title="Department Tickets" 
            description="Service tickets and consumer complaints routed to {{ $dept->dept_name }}."
            :count="$assignedTickets->total()" 
            :columns="['Ticket ID', 'Source', 'Full Address', 'Landmark', 'Nature of Complaint', 'Status']"
            back-href="#department-records-title"
            back-label="Back to tabs ↑"
            caption="Service tickets assigned to {{ $dept->dept_name }}"
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
                        <span>There are currently no active tickets routed to this department.</span>
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
