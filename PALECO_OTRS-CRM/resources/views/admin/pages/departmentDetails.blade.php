@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Department Details')

@section('content')
    <!-- Page Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $dept->dept_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of department information and assignments</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3 flex-wrap">
            <x-contextual-back :default="session('department_list_url', route('admin.departments'))" default-label="Back to List" />
            
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

    <!-- Middle Section: Two-Column Layout for Tables -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        
        <!-- Supervisor Table -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden flex flex-col h-full" aria-labelledby="supervisors-heading">
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 id="supervisors-heading" class="text-base font-bold text-slate-800">Assigned Supervisors</h2>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $supervisorCount }} {{ Str::plural('Supervisor', $supervisorCount) }}
                </span>
            </div>

            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Assigned Supervisors for {{ $dept->dept_name }}</caption>
                    <thead>
                        <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th scope="col" class="px-6 py-4">Supervisor Name</th>
                            <th scope="col" class="px-6 py-4">Contact Number</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignedSupervisor as $supervisor)
                            <tr class="hover:bg-slate-50/75 transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-800">{{ $supervisor->full_name }}</span>
                                        <span class="text-xs text-slate-400 mt-0.5">{{ $supervisor->username }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $supervisor->contact ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.users.show', ['user' => $supervisor, 'source' => 'department', 'dept' => $dept->id]) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Supervisor Details">
                                        <x-icons.eye class="w-5 h-5" />
                                        <span class="sr-only">View {{ $supervisor->full_name }} profile</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg m-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3 text-slate-400">
                                        <x-icons.user class="w-6 h-6" />
                                    </div>
                                    <h3 class="text-sm font-semibold text-slate-800">No Supervisor assigned</h3>
                                    <p class="text-xs text-slate-500 mt-1">There are no active Supervisors linked to this department.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Supervisor Pagination -->
            @if($assignedSupervisor->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-white">
                    {{ $assignedSupervisor->onEachSide(0)->links() }}
                </div>
            @endif
        </section>

        <!-- Teams Table -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden flex flex-col h-full" aria-labelledby="teams-heading">
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 id="teams-heading" class="text-base font-bold text-slate-800">Assigned Teams</h2>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTeams->total() }} {{ Str::plural('Team', $assignedTeams->total()) }}
                </span>
            </div>

            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Assigned Teams for {{ $dept->dept_name }}</caption>
                    <thead>
                        <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th scope="col" class="px-6 py-4">Team Name</th>
                            <th scope="col" class="px-6 py-4">Shift Schedule</th>
                            <th scope="col" class="px-6 py-4 text-center">Members</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assignedTeams as $team)
                            <tr class="hover:bg-slate-50/75 transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-800">{{ $team->team_name }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-200">
                                        <x-icons.clock class="w-3.5 h-3.5 text-slate-500" />
                                        {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center justify-center bg-emerald-100 text-emerald-800 h-6 w-6 rounded-full font-bold text-xs">
                                        {{ $team->members_count }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.teams.show', ['team' => $team, 'source' => 'department', 'dept' => $dept->id]) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Team Details">
                                        <x-icons.eye class="w-5 h-5" />
                                        <span class="sr-only">View {{ $team->team_name }} details</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg m-4">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3 text-slate-400">
                                        <x-icons.team class="w-6 h-6" />
                                    </div>
                                    <h3 class="text-sm font-semibold text-slate-800">No teams assigned</h3>
                                    <p class="text-xs text-slate-500 mt-1">There are no teams currently assigned to this department.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Teams Pagination -->
            @if($assignedTeams->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-white">
                    {{ $assignedTeams->onEachSide(0)->links() }}
                </div>
            @endif
        </section>
    </div>

    <!-- Lower Section: Full-Width Tickets Table -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6" aria-labelledby="department-tickets-heading">
        
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <div>
                <h2 id="department-tickets-heading" class="text-base font-bold text-slate-800">Department Tickets</h2>
                <p class="text-xs text-slate-500 mt-1">Service tickets and complaints assigned to this department</p>
            </div>
            
            @if(method_exists($assignedTickets, 'total') && $assignedTickets->total() > 0)
                <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTickets->total() }} {{ Str::plural('Ticket', $assignedTickets->total()) }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Service tickets assigned to {{ $dept->dept_name }}</caption>
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
                                <p class="text-xs text-slate-500 mt-1">There are currently no active tickets routed to this department.</p>
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
