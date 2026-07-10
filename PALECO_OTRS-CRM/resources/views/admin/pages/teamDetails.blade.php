@extends('admin.base.base')

@section('title', 'Team Details')

@section('content')
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <!-- CONTEXT: Team Name -->
            <h1 class="text-2xl font-bold text-slate-800">{{ $team->team_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of the field team and its roster</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.teams') }}" class="action-link inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Loading...">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
            
            <!-- CONTEXT: Check if Team is Soft Deleted -->
            @if($team->trashed())
                <form action="{{ route('admin.teams.restore', $team) }}" method="POST" class="inline-block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-2 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Restoring...">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                        Restore
                    </button>
                </form>
                <a href="{{ route('admin.teams.forceDeleteConfirm', $team) }}" class="action-link inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-rose-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Force Delete
                </a>
            @else
                <a href="{{ route('admin.teams.editForm', ['team' => $team, 'source' => 'details']) }}" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit
                </a>

                <a href="{{ route('admin.teams.deleteConfirm', ['team' => $team, 'source' => 'details']) }}" class="action-link inline-flex items-center gap-2 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    Archive
                </a>
            @endif
        </div>
    </div>

    <!-- Top Section: Grid Layout for Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Left Column: Primary Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">Team Information</h3>
                </div>
                
                <div class="p-6 space-y-6">
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Team Name</h4>
                        <!-- CONTEXT: Team Name -->
                        <p class="text-base text-slate-900 font-medium">{{ $team->team_name }}</p>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Department</h4>
                        <!-- CONTEXT: Department Name (Navigates through relationship) -->
                        <p class="text-base text-emerald-700 font-bold tracking-wide uppercase">
                            {{ $team->department->dept_name ?? 'Unassigned' }}
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</h4>
                        <!-- CONTEXT: Team Description -->
                        <p class="text-base text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
                            {{ $team->team_desc ?? 'No description provided.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Meta & Stats -->
        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">Operational Details</h3>
                </div>
                
                <div class="p-6 space-y-4 text-sm">
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Status</span>
                        <!-- CONTEXT: Trashed Boolean Check -->
                        @if($team->trashed())
                            <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Archived</span>
                        @else
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Shift Schedule</span>
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-800 font-bold px-2.5 py-1 rounded-md text-xs border border-slate-200">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <!-- CONTEXT: Shift Start and End (Carbon Formatted) -->
                            {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Date Created</span>
                        <!-- CONTEXT: Team Creation Date -->
                        <span class="text-slate-800">{{ $team->created_at->format('M d, Y') }}</span>
                    </div>
                    
                    @if($team->trashed())
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Date Archived</span>
                            <!-- CONTEXT: Team Deletion Date -->
                            <span class="text-slate-800">{{ $team->deleted_at->format('M d, Y - H:i') }}</span>
                        </div>
                    @endif
                    
                    <div class="flex justify-between items-center py-2">
                        <span class="text-slate-500 font-medium">Last Modified</span>
                        <!-- CONTEXT: Team Updated Date -->
                        <span class="text-slate-800">{{ $team->updated_at->format('M d, Y - H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lower Section: Members Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
        
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800">Team Roster</h3>
            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                <!-- CONTEXT: Paginated Total count -->
                {{ $members->total() }} {{ Str::plural('Member', $members->total()) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Member</th>
                        <th class="px-6 py-4">Team Role</th>
                        <th class="px-6 py-4">Date Added</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <!-- CONTEXT: Iterating through paginated $members array -->
                    @forelse ($members as $member)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            
                            <!-- Detailed Member Column (Avatar + Full Name + Email) -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <!-- CONTEXT: User Avatar Accessor -->
                                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-100">
                                        {{ $member->avatar_initials }}
                                    </div>
                                    <div class="flex flex-col">
                                        <!-- CONTEXT: User Full Name Accessor -->
                                        <span class="font-medium text-slate-800">{{ $member->full_name }}</span>
                                        <span class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                            @if($member->email)
                                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                <!-- CONTEXT: User Email Attribute -->
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
                                    <!-- CONTEXT: Data mapped strictly from the dictionary using 'team_role_id' -->
                                    {{ $teamRoles[$member->pivot->team_role_id] ?? 'Unknown Role' }}
                                </span>
                            </td>
                            
                            <!-- Date Added Column -->
                            <td class="px-6 py-4 text-sm text-slate-600 font-medium">
                                <!-- CONTEXT: Data pulled strictly from the 'created_at' Pivot attribute -->
                                {{ $member->pivot->created_at->format('M d, Y') }}
                            </td>

                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.users.show', ['user' => $member]) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Member Profile">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg m-4">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
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
                <!-- CONTEXT: Appends Pagination Links -->
                {{ $members->onEachSide(0)->links() }}
            </div>
        @endif
    </div>
@endsection