@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'User Details')

@section('content')
    <!-- Page Header -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <!-- CONTEXT: User Initials Avatar -->
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xl shrink-0 border border-emerald-100 shadow-xs">
                {{ $user->avatar_initials }}
            </div>
            <div>
                <!-- CONTEXT: User Full Name -->
                <h1 class="text-2xl font-bold text-slate-800">{{ $user->full_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">Detailed view of user profile and system access</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
            @php
                $isFromDept = request('source') === 'department' && request('dept');
                $isFromTeam = request('source') === 'team' && request('team');
                if ($isFromDept) {
                    $backRoute = route('admin.departments.show', ['dept' => request('dept')]);
                    $backText = 'Back to Department';
                } elseif ($isFromTeam) {
                    $backRoute = route('admin.teams.show', array_filter(['team' => request('team'), 'source' => request('dept') ? 'department' : null, 'dept' => request('dept')]));
                    $backText = 'Back to Team';
                } else {
                    $backRoute = session('user_list_url', route('admin.users'));
                    $backText = 'Back to List';
                }
            @endphp
            <a href="{{ $backRoute }}" class="action-link btn btn-secondary text-sm shadow-2xs" data-loading-text="Loading...">
                <x-icons.arrow-left class="w-4 h-4" />
                <span>{{ $backText }}</span>
            </a>
            
            @can('update', $user)
                <a href="{{ route('admin.users.editForm', array_filter(['user' => $user, 'source' => request('source') ?: 'details', 'dept' => request('dept'), 'team' => request('team')])) }}" class="action-link btn btn-primary text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.pencil class="w-4 h-4" />
                    <span>Edit User</span>
                </a>
            @endcan

            @if($user->is_active)
                @can('deactivateConfirm', $user)
                    <a href="{{ route('admin.users.deactivateConfirm', array_filter(['user' => $user, 'source' => request('source') ?: 'details', 'dept' => request('dept'), 'team' => request('team')])) }}" class="action-link btn btn-secondary text-amber-700 hover:bg-amber-50 hover:border-amber-300 text-sm shadow-2xs" data-loading-text="Loading...">
                        <x-icons.exclamation-triangle class="w-4 h-4" />
                        <span>Deactivate</span>
                    </a>
                @endcan
            @else
                @can('reactivateConfirm', $user)
                    <a href="{{ route('admin.users.reactivateConfirm', array_filter(['user' => $user, 'source' => request('source') ?: 'details', 'dept' => request('dept'), 'team' => request('team')])) }}" class="action-link btn btn-secondary text-indigo-700 hover:bg-indigo-50 hover:border-indigo-300 text-sm shadow-2xs" data-loading-text="Loading...">
                        <x-icons.arrow-path class="w-4 h-4" />
                        <span>Reactivate</span>
                    </a>
                @endcan
            @endif
        </div>
    </header>

    <!-- Top Section: Grid Layout for Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        
        <!-- Left Column: Primary Personal Details (Semantic Definition List) -->
        <section class="lg:col-span-2 space-y-6" aria-labelledby="personal-info-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="personal-info-heading" class="text-base font-bold text-slate-800">Personal Information</h2>
                </div>
                
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">First Name</dt>
                            <dd class="text-base text-slate-900 font-medium">{{ str($user->first_name)->title() }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Middle Name</dt>
                            <dd class="text-base text-slate-900 font-medium">{{ str($user->middle_name)->title() ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Last Name</dt>
                            <dd class="text-base text-slate-900 font-medium">{{ str($user->last_name)->title() }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Extension</dt>
                            <dd class="text-base text-slate-900 font-medium">{{ str($user->name_ext)->upper() ?? '—' }}</dd>
                        </div>

                        <div class="md:col-span-2 pt-4 border-t border-slate-100">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</dt>
                            <dd class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <span class="text-base text-slate-900 font-medium">{{ $user->email ?? 'No email provided' }}</span>
                            </dd>
                        </div>

                        <div class="md:col-span-2">
                            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Contact Number</dt>
                            <dd class="flex items-center gap-2">
                                <x-icons.phone class="w-4 h-4 text-slate-400 shrink-0" />
                                <span class="text-base text-slate-900 font-medium">{{ $user->contact ?? '—' }}</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- Right Column: Meta & Account Security (Semantic Definition List) -->
        <section class="space-y-6" aria-labelledby="account-details-heading">
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden h-full">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h2 id="account-details-heading" class="text-base font-bold text-slate-800">Account Details</h2>
                </div>
                
                <dl class="p-6 space-y-4 text-sm divide-y divide-slate-100">
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">System Role</dt>
                        <dd>
                            <span class="bg-slate-100 text-slate-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">
                                {{ Str::headline($user->role->role_name) }}
                            </span>
                        </dd>
                    </div>

                    @if($user->department_id)
                        <div class="flex justify-between items-center py-2">
                            <dt class="text-slate-500 font-medium">Office Department</dt>
                            <dd>
                                <a href="{{ route('admin.departments.show', ['dept' => $user->department_id, 'source' => 'user', 'user' => $user->id]) }}" class="text-emerald-700 hover:underline font-bold tracking-wide uppercase inline-flex items-center gap-1 group">
                                    {{ $user->department->dept_name }}
                                    <x-icons.chevron-right class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                                </a>
                            </dd>
                        </div>
                    @endif

                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Account Status</dt>
                        <dd>
                            @if($user->is_active)
                                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                            @else
                                <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Deactivated</span>
                            @endif
                        </dd>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Username</dt>
                        <dd>
                            <span class="text-slate-800 font-medium bg-slate-50 px-2 py-0.5 rounded border border-slate-200 font-mono text-xs">
                                {{ $user->username }}
                            </span>
                        </dd>
                    </div>

                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Last Login</dt>
                        <dd class="text-slate-800 font-medium">
                            {{ $user->last_login ? $user->last_login->format('M d, Y - H:i') : 'Never logged in' }}
                        </dd>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Account Created</dt>
                        <dd class="text-slate-800">{{ $user->created_at->format('M d, Y') }}</dd>
                    </div>
                    
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-slate-500 font-medium">Last Modified</dt>
                        <dd class="text-slate-800">{{ $user->updated_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <!-- Conditional Lower Section: Assigned Teams (Only shown if user has teams) -->
    @if($assignedTeams->total() > 0)
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-4" aria-labelledby="assigned-teams-heading">
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h2 id="assigned-teams-heading" class="text-base font-bold text-slate-800">Field Team Assignments</h2>
                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTeams->total() }} {{ Str::plural('Team', $assignedTeams->total()) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">Field Team Assignments for {{ $user->full_name }}</caption>
                    <thead>
                        <tr class="bg-white border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th scope="col" class="px-6 py-4">Team Name</th>
                            <th scope="col" class="px-6 py-4">Assigned Role</th>
                            <th scope="col" class="px-6 py-4">Shift Schedule</th>
                            <th scope="col" class="px-6 py-4">Date Assigned</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
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
                                        <x-icons.clock class="w-3.5 h-3.5 text-slate-500" />
                                        {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-slate-600 font-medium">
                                    {{ $team->pivot->created_at->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.teams.show', array_filter(['team' => $team, 'source' => 'user', 'user' => $user->id, 'dept' => request('dept')])) }}" class="inline-flex p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Team Details">
                                        <x-icons.eye class="w-5 h-5" />
                                        <span class="sr-only">View {{ $team->team_name }} details</span>
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
        </section>
    @endif
@endsection
