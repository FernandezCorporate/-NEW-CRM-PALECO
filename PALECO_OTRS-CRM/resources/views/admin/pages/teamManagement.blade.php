@extends('admin.base.base')

@section('title', 'Team Management')

@section('content')
    @include('admin.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Team Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage field teams and their members.</p>
        </div>
        
        <a href="{{ route('admin.teams.createForm') }}" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Team
        </a>
    </div>

    <!-- Controls Row -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        
        <!-- Unified Form for all GET parameters -->
        <form action="{{ route('admin.teams') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
            
            <!-- Search -->
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search teams..." value="{{ request('search') }}" class="border border-slate-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-full md:w-64">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Searching...">Search</button>
                
                @if(request()->filled('search'))
                    <!-- UPDATED: Preserves 'filter' and 'sort' parameters, but removes 'search' -->
                    <a href="{{ route('admin.teams', request()->except('search')) }}" class="action-link bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Clearing...">
                        Clear
                    </a>
                @endif
            </div>

            <!-- Filter and Sort Group -->
            <div class="flex items-center gap-2">
                
                <!-- The Generalized Tom Select Dropdown -->
                <div class="w-56 text-sm"> 
                    <select name="filter" class="tom-select-sync hidden" data-autosubmit="true" autocomplete="off">
                        
                        <!-- Explicit "All" option. It gets marked 'selected' if the URL filter is 'all' OR completely empty -->
                        <option value="all" {{ request('filter') === 'all' || empty(request('filter')) ? 'selected' : '' }}></option>
                        
                        @foreach($departments as $id => $name)
                            <option value="{{ $id }}" {{ request('filter') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div> 

                @if(request()->filled('filter') && request('filter') !== 'all')
                    <!-- UPDATED: Preserves 'search' and 'sort' parameters, but removes 'filter' -->
                    <a href="{{ route('admin.teams', request()->except('filter')) }}" class="action-link bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Clearing...">
                        Reset
                    </a>
                @endif

                <!-- Sort Dropdown -->
                <select name="sort" onchange="this.form.submit()" class="border border-slate-200 p-2.5 rounded-lg text-sm bg-white cursor-pointer focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                    <option value="team_nameASC" {{ request('sort') === 'team_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="team_nameDESC" {{ request('sort') === 'team_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="shift_startASC" {{ request('sort') === 'shift_startASC' ? 'selected' : '' }}>Shift Start (Earliest)</option>
                    <option value="shift_startDESC" {{ request('sort') === 'shift_startDESC' ? 'selected' : '' }}>Shift Start (Latest)</option>
                </select>
                <noscript><button type="submit" class="bg-slate-500 text-white px-4 py-2 rounded">Apply</button></noscript>
            </div>
        </form>

        <!-- View Toggle -->
        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm shrink-0">
            <button id="list-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="List View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            </button>
            <div class="w-px h-5 bg-slate-200"></div>
            <button id="card-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="Card View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </button>
        </div>
    </div>

        <!-- View Toggle -->
        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm shrink-0">
            <button id="list-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="List View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            </button>
            <div class="w-px h-5 bg-slate-200"></div>
            <button id="card-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="Card View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </button>
        </div>
    </div>

    <!-- Data Display Area -->
    @if($teams->count() > 0)
        <!-- LIST VIEW -->
        <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6 block">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-sm text-slate-600 uppercase tracking-wider">
                            <th class="p-4 font-medium">Team Name</th>
                            <th class="p-4 font-medium">Department</th>
                            <th class="p-4 font-medium">Shift Schedule</th>
                            <th class="p-4 font-medium">Members</th>
                            <th class="p-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($teams as $team)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900">{{ $team->team_name }}</div>
                                    @if($team->team_desc)
                                        <div class="text-xs text-slate-500 mt-1 truncate max-w-xs">{{ $team->team_desc }}</div>
                                    @endif
                                </td>
                                <td class="p-4 text-sm text-slate-700">
                                    {{ $team->department->dept_name ?? 'Unassigned' }}
                                </td>
                                <td class="p-4 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-200">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-slate-700">
                                    <span class="inline-flex items-center justify-center bg-emerald-100 text-emerald-800 h-6 w-6 rounded-full font-bold text-xs">
                                        {{ $team->members_count }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-3">
                                        <a href="#" class="text-slate-400 hover:text-emerald-600 transition-colors" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <a href="#" class="text-slate-400 hover:text-amber-500 transition-colors" title="Archive">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                        </a>
                                        <a href="#" class="text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CARD VIEW -->
        <div id="card-view-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6 hidden">
            @foreach($teams as $team)
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow flex flex-col h-full">
                    
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $team->team_name }}</h3>
                            <span class="inline-block mt-1 text-xs font-medium bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                {{ $team->department->dept_name ?? 'Unassigned' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-400">
                            <a href="#" class="hover:text-emerald-600 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></a>
                            <a href="#" class="hover:text-amber-500 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg></a>
                        </div>
                    </div>
                    
                    @if($team->team_desc)
                        <p class="text-sm text-slate-500 mb-6 flex-grow">{{ $team->team_desc }}</p>
                    @else
                        <p class="text-sm text-slate-400 italic mb-6 flex-grow">No description provided.</p>
                    @endif

                    <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-sm text-slate-600">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                        </div>
                        <div class="flex items-center gap-1.5 font-medium" title="Total Members">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            {{ $team->members_count }}
                        </div>
                    </div>

                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $teams->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
            <div class="mx-auto h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-1">No teams found</h3>
            <p class="text-slate-500 mb-6">There are no teams matching your current filters or search query.</p>
            <a href="#" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors">
                Create First Team
            </a>
        </div>
    @endif
@endsection