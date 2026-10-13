@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Team Details')

@section('content')
    <!-- Page Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <!-- CONTEXT: Team Name -->
            <h1 class="text-2xl font-bold text-slate-800">{{ $team->team_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of the field team and its roster</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3 flex-wrap">
            <x-contextual-back :default="session('team_list_url', route('admin.teams'))" default-label="Back to List" />
            
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

    <!-- Middle Section: Members Table -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6" aria-labelledby="team-roster-heading">
        
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h2 id="team-roster-heading" class="text-base font-bold text-slate-800">Team Roster</h2>
            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                {{ $members->total() }} {{ Str::plural('Member', $members->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <caption class="sr-only">Team Roster for {{ $team->team_name }}</caption>
                <thead>
                    <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4">Member</th>
                        <th scope="col" class="px-6 py-4">Team Role</th>
                        <th scope="col" class="px-6 py-4">Date Added</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($members as $member)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            
                            <!-- Detailed Member Column (Avatar + Full Name + Email) -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-100">
                                        {{ $member->avatar_initials }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-800">{{ $member->full_name }}</span>
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
                            
                            <!-- Team Role Column -->
                            <td class="px-6 py-4">
                                <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-bold px-2.5 py-1 rounded-full tracking-wide uppercase">
                                    {{ $member->assigned_role_name }}
                                </span>
                            </td>
                            
                            <!-- Date Added Column -->
                            <td class="px-6 py-4 text-sm text-slate-600 font-medium">
                                {{ $member->pivot->created_at->format('M d, Y') }}
                            </td>

                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.users.show', array_filter(['user' => $member, 'source' => 'team', 'team' => $team->id, 'dept' => request('dept')])) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Member Profile">
                                    <x-icons.eye class="w-5 h-5" />
                                    <span class="sr-only">View {{ $member->full_name }} profile</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg m-4">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3 text-slate-400">
                                    <x-icons.users class="w-6 h-6" />
                                </div>
                                <h3 class="text-sm font-semibold text-slate-800">No members assigned</h3>
                                <p class="text-xs text-slate-500 mt-1">There are currently no users assigned to this team roster.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($members->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $members->onEachSide(0)->links() }}
            </div>
        @endif
    </section>

    <!-- Lower Section: Full-Width Tickets Table -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6" aria-labelledby="team-tickets-heading">
        
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <div>
                <h2 id="team-tickets-heading" class="text-base font-bold text-slate-800">Assigned Tickets</h2>
                <p class="text-xs text-slate-500 mt-1">Service tickets currently assigned to this team</p>
            </div>
            
            @if(method_exists($assignedTickets, 'total') && $assignedTickets->total() > 0)
                <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTickets->total() }} {{ Str::plural('Ticket', $assignedTickets->total()) }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Service tickets assigned to {{ $team->team_name }}</caption>
                <thead>
                    <tr class="bg-white border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-40">Ticket ID</th>
                        <th scope="col" class="px-6 py-4 w-40">Source</th>
                        <th scope="col" class="px-6 py-4 w-56">Full Address</th>
                        <th scope="col" class="px-6 py-4 w-48">Landmark</th>
                        <th scope="col" class="px-6 py-4 w-48">Nature of Complaint</th>
                        <th scope="col" class="px-6 py-4 w-32 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignedTickets as $ticket)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            
                            <!-- Ticket ID & Date -->
                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-emerald-600 text-sm">{{ $ticket->ticket_number }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $ticket->reported_at->format('M d, Y') }}</div>
                            </td>

                            <!-- Source -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $ticket->complaint_source->label() }}</div>
                            </td>

                            <!-- Full Address -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800">
                                    {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                                </div>
                            </td>

                            <!-- Landmark -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-600">
                                    {{ $ticket->landmark ?? '—' }}
                                </div>
                            </td>

                            <!-- Nature of Complaint -->
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">
                                    {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
                                </div>
                            </td>

                            <!-- Ticket Status -->
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $ticket->status->badgeClasses() }} text-[11px] font-bold">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>
                
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3 text-slate-400">
                                    <x-icons.ticket class="w-6 h-6" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No tickets assigned</h3>
                                <p class="text-xs text-slate-500 mt-1">There are currently no active tickets routed to this team.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Ticket Pagination (If Paginated) -->
        @if(method_exists($assignedTickets, 'hasPages') && $assignedTickets->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $assignedTickets->onEachSide(0)->links() }}
            </div>
        @endif
    </section>
@endsection
