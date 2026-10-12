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
    <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse">
                <caption class="sr-only">Department directory and metrics list</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4">Department Name</th>
                        <th scope="col" class="px-6 py-4">Active Supervisors</th>
                        <th scope="col" class="px-6 py-4">Active Teams</th>
                        <th scope="col" class="px-6 py-4">Assigned Tickets</th>
                        <th scope="col" class="px-6 py-4">Date Created</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($departments as $department)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $department->dept_name }}</td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <div class="truncate max-w-md" title="{{ $department->active_supervisors_count }}">
                                    {{ $department->active_supervisors_count ?? 0 }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <div class="truncate max-w-md" title="{{ $department->active_team_count }}">
                                    {{ $department->active_team_count ?? 0 }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                <div class="truncate max-w-md" title="{{ $department->assigned_ticket_count }}">
                                    {{ $department->assigned_ticket_count ?? 0 }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-sm">{{ $department->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.departments.show', $department) }}" 
                                    class="action-link p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-loading-text=""
                                    title="View Department Details" aria-label="View details for {{ $department->dept_name }}">
                                        <x-icons.eye class="w-5 h-5" />
                                    </a>
                                    
                                    @if($department->trashed())
                                        <form action="{{ route('admin.departments.restore', $department->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" data-loading-text=""
                                                title="Restore Department" aria-label="Restore {{ $department->dept_name }}">
                                                <x-icons.arrow-path class="w-5 h-5" />
                                            </button>
                                        </form>

                                        <a href="{{ route('admin.departments.forceDeleteConfirm', $department) }}"
                                            class="action-link p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" data-loading-text=""
                                            title="Permanently Delete Department" aria-label="Permanently delete {{ $department->dept_name }}">
                                            <x-icons.trash class="w-5 h-5" />
                                        </a>
                                    @else
                                        <a href="{{ route('admin.departments.editForm', ['dept' => $department, 'source' => 'list']) }}" 
                                        class="action-link p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" data-loading-text="" 
                                        title="Edit Department" aria-label="Edit {{ $department->dept_name }}">
                                            <x-icons.pencil class="w-5 h-5" />
                                        </a>
                                        <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $department, 'source' => 'list']) }}"
                                            class="action-link p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" data-loading-text="" 
                                            title="Archive Department" aria-label="Archive {{ $department->dept_name }}">
                                            <x-icons.trash class="w-5 h-5" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.department class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-semibold text-slate-800">No departments found</h3>
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
                    <h3 class="text-lg font-bold text-slate-900 leading-tight">{{ $department->dept_name }}</h3>
                    
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
        <div class="mt-6">
            {{ $departments->onEachSide(0)->links() }}
        </div>
    @endif
@endsection
