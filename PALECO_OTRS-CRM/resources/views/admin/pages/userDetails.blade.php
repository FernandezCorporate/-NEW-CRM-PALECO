@extends('admin.base.base')

@section('title', 'User Details')

@section('content')
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <!-- CONTEXT: User Initials Avatar -->
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xl shrink-0 border border-emerald-100 shadow-sm">
                {{ $user->avatar_initials }}
            </div>
            <div>
                <!-- CONTEXT: User Full Name -->
                <h1 class="text-2xl font-bold text-slate-800">{{$user->full_name}}</h1>
                <p class="text-sm text-slate-500 mt-1">Detailed view of user profile and system access</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users') }}" class="action-link inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Loading...">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
            
            @can('update', $user)
                <a href="{{ route('admin.users.editForm', ['user' => $user]) }}" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit User
                </a>
            @endcan

            @if($user->is_active)
                @can('deactivateConfirm', $user)
                    <a href="{{ route('admin.users.deactivateConfirm', ['user' => $user]) }}" class="action-link inline-flex items-center gap-2 bg-amber-50 hover:bg-amber-600 text-amber-700 hover:text-white border border-amber-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Loading...">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                        Deactivate
                    </a>
                @endcan
            @else
                @can('reactivateConfirm', $user)
                    <a href="{{ route('admin.users.reactivateConfirm', ['user' => $user]) }}" class="action-link inline-flex items-center gap-2 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Loading...">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                        Reactivate
                    </a>
                @endcan
            @endif
        </div>
    </div>

    <!-- Top Section: Grid Layout for Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Left Column: Primary Personal Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">Personal Information</h3>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">First Name</h4>
                            <p class="text-base text-slate-900 font-medium">{{ str($user->first_name)->title() }}</p>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Middle Name</h4>
                            <p class="text-base text-slate-900 font-medium">{{ str($user->middle_name)->title() ?? '—' }}</p>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Last Name</h4>
                            <p class="text-base text-slate-900 font-medium">{{ str($user->last_name)->title() }}</p>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Extension</h4>
                            <p class="text-base text-slate-900 font-medium">{{ str($user->name_ext)->upper() ?? '—' }}</p>
                        </div>

                        <div class="md:col-span-2 pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</h4>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <p class="text-base text-slate-900 font-medium">{{ $user->email ?? 'No email provided' }}</p>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Contact Number</h4>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                <p class="text-base text-slate-900 font-medium">{{ $user->contact ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Meta & Account Security -->
        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">Account Details</h3>
                </div>
                
                <div class="p-6 space-y-4 text-sm">
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">System Role</span>
                        <span class="bg-slate-100 text-slate-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                            {{ Str::headline($user->role->role_name) }}
                        </span>
                    </div>

                    @if($user->department_id)
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Office Department</span>
                            <span class="text-emerald-700 font-bold tracking-wide uppercase">
                                {{ $user->department->dept_name }}
                            </span>
                        </div>
                    @endif

                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Account Status</span>
                        @if($user->is_active)
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                        @else
                            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Deactivated</span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Username</span>
                        <span class="text-slate-800 font-medium bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
                            {{  $user->username }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Last Login</span>
                        <span class="text-slate-800 font-medium">
                            {{ $user->last_login ? $user->last_login->format('M d, Y - H:i') : 'Never logged in' }}
                        </span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Account Created</span>
                        <span class="text-slate-800">{{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <span class="text-slate-500 font-medium">Last Modified</span>
                        <span class="text-slate-800">{{ $user->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Conditional Lower Section: Assigned Teams (Only shown if user has teams) -->
    @if($assignedTeams->total() > 0)
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
            
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-800">Field Team Assignments</h3>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTeams->total() }} {{ Str::plural('Team', $assignedTeams->total()) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Team Name</th>
                            <th class="px-6 py-4">Assigned Role</th>
                            <th class="px-6 py-4">Shift Schedule</th>
                            <th class="px-6 py-4">Date Assigned</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($assignedTeams as $team)
                            <tr class="hover:bg-slate-50/75 transition-colors group">
                                
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800">{{ $team->team_name }}</span>
                                        <span class="text-xs text-slate-500 mt-0.5">{{ $team->department->dept_name ?? 'Unassigned Department' }}</span>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-bold px-2.5 py-1 rounded-full tracking-wide uppercase">
                                        {{ Str::headline($team->assigned_role_name) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-200">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-slate-600 font-medium">
                                    {{ $team->pivot->created_at->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.teams.show', ['team' => $team]) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Team Details">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Block -->
            @if($assignedTeams->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-white">
                    {{ $assignedTeams->onEachSide(0)->links() }}
                </div>
            @endif

        </div>
    @endif
@endsection