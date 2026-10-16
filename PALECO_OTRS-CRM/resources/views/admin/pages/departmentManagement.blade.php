@extends('admin.base.base')

@section('title', 'Department Management')

@section('content')
    <x-alert />

    <!-- Top Header Row -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Department Management</h1>
            <p class="text-sm text-slate-500 mt-1">Monitor and manage department information</p>
        </div>
        
        <x-btn href="{{ route('admin.departments.createForm') }}" variant="primary" class="action-link" data-loading-text="Loading...">
            <x-icons.plus class="w-4 h-4 mr-1.5" />
            New Department
        </x-btn>
    </header>

    <!-- Controls Row (Search, Filter, Sort, View Toggle) -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        
        <!-- Filter and Search Toolbar -->
        <search class="filter-toolbar w-full lg:w-auto" aria-label="Department search and filters">
            <form action="{{ route('admin.departments') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
                
                <!-- Search -->
                <div class="flex items-center gap-2">
                    <input type="text" name="search" placeholder="Search departments..." value="{{ request('search') }}" class="form-input w-full md:w-64" aria-label="Search departments by name or description">
                    <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                    
                    @if(request()->filled('search'))
                        <x-btn href="{{ route('admin.departments', request()->except('search')) }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">
                            Clear
                        </x-btn>
                    @endif
                </div>

                <!-- Filter and Sort Group -->
                <div class="flex items-center gap-2">
                    <select name="filter" class="ts-filter-dropdown" aria-label="Filter active or archived departments">
                        <option value="active" {{ request('filter') === 'active' ? 'selected' : '' }}>Active Departments</option>
                        <option value="archived" {{ request('filter') === 'archived' ? 'selected' : '' }}>Archived Departments</option>
                    </select>

                    <select name="sort" class="ts-filter-dropdown" aria-label="Sort departments">
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                        <option value="dept_nameASC" {{ request('sort') === 'dept_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                        <option value="dept_nameDESC" {{ request('sort') === 'dept_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                        <option value="dept_descASC" {{ request('sort') === 'dept_descASC' ? 'selected' : '' }}>Description (A-Z)</option>
                        <option value="dept_descDESC" {{ request('sort') === 'dept_descDESC' ? 'selected' : '' }}>Description (Z-A)</option>
                    </select>
                    <noscript><x-btn type="submit" variant="secondary" size="md">Apply</x-btn></noscript>
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

    <!-- List View Container -->
    <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Department directory <span class="record-count ml-2">{{ number_format($departments->total()) }}</span></h2>
                <p class="text-xs text-slate-500 mt-0.5">Operational units, supervisory hierarchy, and departmental queues</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse min-w-[950px]">
                <caption class="sr-only">Department directory and metrics list</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-60">Department Name</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Active Supervisors</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Active Teams</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Assigned Tickets</th>
                        <th scope="col" class="px-6 py-4 w-40">Date Created</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($departments as $department)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <a href="{{ route('admin.departments.show', $department) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                    {{ $department->dept_name }}
                                </a>
                                @if($department->dept_desc)
                                    <div class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ $department->dept_desc }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ $department->active_supervisors_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ $department->active_team_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ $department->assigned_ticket_count ?? 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $department->created_at->format('M d, Y') }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $department->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.departments.show', $department) }}" 
                                    class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" data-loading-text=""
                                    title="View Department Details" aria-label="View details for {{ $department->dept_name }}">
                                        <x-icons.eye class="w-4 h-4" />
                                    </a>
                                    
                                    @if($department->trashed())
                                        <form action="{{ route('admin.departments.restore', $department->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" data-loading-text=""
                                                title="Restore Department" aria-label="Restore {{ $department->dept_name }}">
                                                <x-icons.arrow-path class="w-4 h-4" />
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.departments.forceDeleteConfirm', $department) }}"
                                            class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" data-loading-text=""
                                            title="Permanently Delete Department" aria-label="Permanently delete {{ $department->dept_name }}">
                                            <x-icons.trash class="w-4 h-4" />
                                        </a>
                                    @else
                                        <a href="{{ route('admin.departments.editForm', ['dept' => $department, 'source' => 'list']) }}" 
                                        class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" data-loading-text="" 
                                        title="Edit Department" aria-label="Edit {{ $department->dept_name }}">
                                            <x-icons.pencil class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $department, 'source' => 'list']) }}"
                                            class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" data-loading-text="" 
                                            title="Archive Department" aria-label="Archive {{ $department->dept_name }}">
                                            <x-icons.trash class="w-4 h-4" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.department class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No departments found</h3>
                                <p class="text-xs text-slate-500 mt-1">Adjust your search or filter to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Card View Container -->
    <div id="card-view-container" class="hidden grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-4">
        @forelse ($departments as $department)
            <article class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs hover:shadow-sm transition-shadow flex flex-col h-full">
                
                <!-- Card Header (Name & Actions) -->
                <div class="flex justify-between items-start mb-4 gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-slate-900 leading-tight break-words">{{ $department->dept_name }}</h3>
                    </div>
                    
                    <div class="flex items-center gap-1 shrink-0 bg-slate-50 p-1 rounded-lg border border-slate-100">
                        <a href="{{ route('admin.departments.show', $department) }}" 
                        class="action-link p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-100 rounded-md transition-colors" data-loading-text="" 
                        title="View Department Details" aria-label="View details for {{ $department->dept_name }}">
                            <x-icons.eye class="w-4 h-4" />
                        </a> 
                        
                        @if($department->trashed())
                            <form action="{{ route('admin.departments.restore', $department->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-100 rounded-md transition-colors" data-loading-text="" 
                                    title="Restore Department" aria-label="Restore {{ $department->dept_name }}">
                                    <x-icons.arrow-path class="w-4 h-4" />
                                </button>
                            </form>

                            <a href="{{ route('admin.departments.forceDeleteConfirm', $department) }}"
                                class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" data-loading-text="" 
                                title="Permanently Delete Department" aria-label="Permanently delete {{ $department->dept_name }}">
                                <x-icons.trash class="w-4 h-4" />
                            </a>
                        @else
                            <a href="{{ route('admin.departments.editForm', ['dept' => $department, 'source' => 'list']) }}" 
                            class="action-link p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-100 rounded-md transition-colors" data-loading-text="" 
                            title="Edit Department" aria-label="Edit {{ $department->dept_name }}">
                                <x-icons.pencil class="w-4 h-4" />
                            </a>
                            <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $department, 'source' => 'list']) }}"
                                class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" data-loading-text="" 
                                title="Archive Department" aria-label="Archive {{ $department->dept_name }}">
                                <x-icons.trash class="w-4 h-4" />
                            </a>
                        @endif
                    </div>
                </div>
                               
                <!-- Description -->
                <p class="text-sm text-slate-500 mb-6 flex-grow line-clamp-2" title="{{ $department->dept_desc }}">
                    {{ $department->dept_desc ?? 'No description provided.' }}
                </p>

                <!-- Statistics Grid -->
                <div class="grid grid-cols-2 gap-3 mb-5">
                    <!-- Supervisors -->
                    <div class="bg-slate-50 border border-slate-100 rounded-lg p-3 text-center flex flex-col justify-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Supervisors</span>
                        <span class="text-lg font-bold text-slate-700">{{ $department->active_supervisors_count ?? 0 }}</span>
                    </div>
                    <!-- Teams -->
                    <div class="bg-slate-50 border border-slate-100 rounded-lg p-3 text-center flex flex-col justify-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Teams</span>
                        <span class="text-lg font-bold text-slate-700">{{ $department->active_team_count ?? 0 }}</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-400">
                    <div class="flex items-center gap-1.5 font-medium">
                        Added {{ $department->created_at->format('M d, Y') }}
                    </div>
                    @if($department->trashed())
                        <x-badge variant="slate">Archived</x-badge>
                    @endif
                </div>
            </article>
        @empty
            <div class="col-span-full py-16 flex flex-col items-center justify-center text-center bg-white border-2 border-dashed border-slate-200 rounded-xl">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                    <x-icons.department class="w-8 h-8 text-slate-400" />
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">No departments found</h3>
                <p class="text-sm text-slate-500">Try adjusting your search query or filters.</p>
            </div>
        @endforelse
    </div>
    
    <!-- Pagination -->
    @if($departments->hasPages())
        <div class="mt-4">
            {{ $departments->onEachSide(0)->links() }}
        </div>
    @endif
@endsection
