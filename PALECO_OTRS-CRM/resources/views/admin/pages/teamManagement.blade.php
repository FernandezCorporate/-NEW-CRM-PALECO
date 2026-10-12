@extends('admin.base.base')

@section('title', 'Team Management')

@section('content')
    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Team Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage field teams and their members.</p>
        </div>
        
        <x-btn href="{{ route('admin.teams.createForm') }}" variant="primary" class="action-link" data-loading-text="Loading...">
            <x-icons.plus class="w-4 h-4 mr-1.5" />
            New Team
        </x-btn>
    </header>

    <!-- Controls Row -->
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
        
        <search class="filter-toolbar w-full xl:w-auto" aria-label="Team search and filters">
            <form action="{{ route('admin.teams') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full xl:w-auto">
                
                <!-- Search -->
                <div class="flex items-center gap-2">
                    <input type="text" name="search" placeholder="Search teams..." value="{{ request('search') }}" class="form-input w-full md:w-64" aria-label="Search teams by name or description">
                    <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                    
                    @if(request()->filled('search'))
                        <x-btn href="{{ route('admin.teams', request()->except('search')) }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">
                            Clear
                        </x-btn>
                    @endif
                </div>

                <!-- Filters & Sort -->
                <div class="flex items-center gap-2 flex-wrap">
                    
                    <!-- Status Filter (Active/Archived) -->
                    <select name="status" class="ts-filter-dropdown" aria-label="Filter active or archived teams">
                        <option value="active" {{ request('status') !== 'archived' ? 'selected' : '' }}>Active Teams</option>
                        <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived Teams</option>
                    </select>

                    <!-- Department Filter -->
                    <div class="w-56 text-sm"> 
                        <select name="filter" class="ts-filter-dropdown" aria-label="Filter by department">
                            <option value="all" {{ request('filter') === 'all' || empty(request('filter')) ? 'selected' : '' }}>All Departments</option>
                            @foreach($departments as $id => $name)
                                <option value="{{ $id }}" {{ request('filter') == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort Dropdown -->
                    <select name="sort" class="ts-filter-dropdown" aria-label="Sort teams">
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                        <option value="team_nameASC" {{ request('sort') === 'team_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                        <option value="team_nameDESC" {{ request('sort') === 'team_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                        <option value="shift_startASC" {{ request('sort') === 'shift_startASC' ? 'selected' : '' }}>Shift Start (Earliest)</option>
                        <option value="shift_startDESC" {{ request('sort') === 'shift_startDESC' ? 'selected' : '' }}>Shift Start (Latest)</option>
                    </select>
                    
                    <noscript><x-btn type="submit" variant="secondary" size="md">Apply</x-btn></noscript>
                    
                    @if(request()->filled('filter') && request('filter') !== 'all')
                        <x-btn href="{{ route('admin.teams', request()->except('filter')) }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">
                            Reset Department
                        </x-btn>
                    @endif
                </div>
            </form>
        </search>

        <!-- View Toggle Buttons -->
        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-xs shrink-0" role="group" aria-label="View format toggle">
            <button id="list-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="List View" aria-label="Switch to list view" aria-pressed="true">
                <x-icons.list-bullet class="w-5 h-5" />
            </button>
            <div class="w-px h-5 bg-slate-200"></div>
            <button id="card-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="Card View" aria-label="Switch to card view" aria-pressed="false">
                <x-icons.squares-2x2 class="w-5 h-5" />
            </button>
        </div>
    </div>

    @if($teams->count() > 0)
        <!-- LIST VIEW -->
        <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6 block">
            <div class="overflow-x-auto">
                <table class="system-table w-full text-left border-collapse">
                    <caption class="sr-only">Field response teams directory</caption>
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th scope="col" class="px-6 py-4">Team Name</th>
                            <th scope="col" class="px-6 py-4">Department</th>
                            <th scope="col" class="px-6 py-4">Shift Schedule</th>
                            <th scope="col" class="px-6 py-4">Members</th>
                            <th scope="col" class="px-6 py-4">Tickets</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($teams as $team)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900">{{ $team->team_name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    {{ $team->department->dept_name ?? 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-xs font-medium border border-slate-200">
                                        <x-icons.clock class="w-3.5 h-3.5" />
                                        {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <x-badge variant="emerald">
                                        {{ $team->members_count }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <x-badge variant="emerald">
                                        {{ $team->ticket_count }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- View -->
                                        <a href="{{ route('admin.teams.show', ['team' => $team]) }}" class="action-link p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Team Details" aria-label="View details for {{ $team->team_name }}">
                                            <x-icons.eye class="w-5 h-5" />
                                        </a>

                                        @if($team->trashed())
                                            <!-- Restore -->
                                            <form action="{{ route('admin.teams.restore', ['team' => $team]) }}" method="POST" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Restore Team" aria-label="Restore {{ $team->team_name }}">
                                                    <x-icons.arrow-path class="w-5 h-5" />
                                                </button>
                                            </form>
                                            <!-- Force Delete -->
                                            <a href="{{ route('admin.teams.forceDeleteConfirm', ['team' => $team]) }}" class="action-link p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Permanently Delete Team" aria-label="Permanently delete {{ $team->team_name }}">
                                                <x-icons.trash class="w-5 h-5" />
                                            </a>
                                        @else
                                            <!-- Edit -->
                                            <a href="{{ route('admin.teams.editForm', ['team' => $team, 'source' => 'list']) }}" class="action-link p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Edit Team" aria-label="Edit {{ $team->team_name }}">
                                                <x-icons.pencil class="w-5 h-5" />
                                            </a>
                                            <!-- Archive -->
                                            <a href="{{ route('admin.teams.deleteConfirm', ['team' => $team, 'source' => 'list']) }}" class="action-link p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Archive Team" aria-label="Archive {{ $team->team_name }}">
                                                <x-icons.trash class="w-5 h-5" />
                                            </a>
                                        @endif
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
                <article class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 hover:shadow-sm transition-shadow flex flex-col h-full relative">

                    <!-- Header & Actions -->
                    <div class="flex justify-between items-start mb-4 gap-4">
                        <div class="pr-2">
                            <h3 class="text-lg font-bold text-slate-900 leading-tight">{{ $team->team_name }}</h3>
                            <div class="mt-1">
                                <x-badge variant="slate">
                                    {{ $team->department->dept_name ?? 'Unassigned' }}
                                </x-badge>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-1 shrink-0 bg-slate-50 p-1 rounded-lg border border-slate-100">
                            <!-- View -->
                            <a href="{{ route('admin.teams.show', ['team' => $team]) }}" class="action-link p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-100 rounded-md transition-colors" title="View Team Details" aria-label="View details for {{ $team->team_name }}">
                                <x-icons.eye class="w-4 h-4" />
                            </a>
                            
                            @if($team->trashed())
                                <!-- Restore -->
                                <form action="{{ route('admin.teams.restore', ['team' => $team]) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-100 rounded-md transition-colors" title="Restore Team" aria-label="Restore {{ $team->team_name }}">
                                        <x-icons.arrow-path class="w-4 h-4" />
                                    </button>
                                </form>
                                <!-- Force Delete -->
                                <a href="{{ route('admin.teams.forceDeleteConfirm', ['team' => $team]) }}" class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" title="Permanently Delete Team" aria-label="Permanently delete {{ $team->team_name }}">
                                    <x-icons.trash class="w-4 h-4" />
                                </a>
                            @else
                                <!-- Edit -->
                                <a href="{{ route('admin.teams.editForm', ['team' => $team, 'source' => 'card']) }}" class="action-link p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-100 rounded-md transition-colors" title="Edit Team" aria-label="Edit {{ $team->team_name }}">
                                    <x-icons.pencil class="w-4 h-4" />
                                </a>
                                <!-- Archive -->
                                <a href="{{ route('admin.teams.deleteConfirm', ['team' => $team, 'source' => 'card']) }}" class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" title="Archive Team" aria-label="Archive {{ $team->team_name }}">
                                    <x-icons.trash class="w-4 h-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                    
                    @if($team->team_desc)
                        <p class="text-sm text-slate-500 mb-6 flex-grow">{{ $team->team_desc }}</p>
                    @else
                        <p class="text-sm text-slate-400 italic mb-6 flex-grow">No description provided.</p>
                    @endif

                    <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-sm text-slate-600">
                        <div class="flex items-center gap-1.5">
                            <x-icons.clock class="w-4 h-4 text-slate-400" />
                            {{ $team->shift_start->format('h:i A') }} - {{ $team->shift_end->format('h:i A') }}
                        </div>
                        <div class="flex items-center gap-1.5 font-medium" title="Total Members">
                            <x-icons.team class="w-4 h-4 text-emerald-500" />
                            <span>{{ $team->members_count }} members</span>
                        </div>
                    </div>

                </article>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $teams->links() }}
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-xs">
            <div class="mx-auto h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                <x-icons.team class="h-8 w-8 text-slate-400" />
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-1">No teams found</h3>
            <p class="text-slate-500 mb-6">There are no teams matching your current filters or search query.</p>
        </div>
    @endif
@endsection