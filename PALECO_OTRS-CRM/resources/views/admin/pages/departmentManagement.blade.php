@extends('admin.base.base')

@section('title', 'Department Management')

@section('content')
    @include('admin.prompts.alert')

    <!-- Top Header Row -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Department Management</h1>
            <p class="text-sm text-slate-500 mt-1">Monitor and manage department information</p>
        </div>
        
        <a href="{{ route('admin.departments.createForm') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New Department
        </a>
    </div>

    <!-- Controls Row (Search, Filter, Sort, View Toggle) -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        
        <!-- Unified Form for all GET parameters -->
        <form action="{{ route('admin.departments') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
            
            <!-- Search -->
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search departments..." value="{{ request('search') }}" class="border border-slate-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-full md:w-64">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors">Search</button>
                
                @if(request()->filled('search'))
                    <!-- Styled Clear Button -->
                    <a href="{{ route('admin.departments', request()->except('search')) }}" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors">
                        Clear
                    </a>
                @endif
            </div>

            <!-- Filter and Sort Group -->
            <div class="flex items-center gap-2">
                <select name="filter" onchange="this.form.submit()" class="border border-slate-200 p-2.5 rounded-lg text-sm bg-white cursor-pointer focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="active" {{ request('filter') === 'active' ? 'selected' : '' }}>Active Departments</option>
                    <option value="archived" {{ request('filter') === 'archived' ? 'selected' : '' }}>Archived Departments</option>
                </select>

                <select name="sort" onchange="this.form.submit()" class="border border-slate-200 p-2.5 rounded-lg text-sm bg-white cursor-pointer focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                    <option value="dept_nameASC" {{ request('sort') === 'dept_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="dept_nameDESC" {{ request('sort') === 'dept_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="dept_descASC" {{ request('sort') === 'dept_descASC' ? 'selected' : '' }}>Description (A-Z)</option>
                    <option value="dept_descDESC" {{ request('sort') === 'dept_descDESC' ? 'selected' : '' }}>Description (Z-A)</option>
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

    <!-- List View Container -->
    <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-4">Department Name</th>
                    <th class="px-6 py-4">Department Description</th>
                    <th class="px-6 py-4">Date Created</th>
                    <th class="px-6 py-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($departments as $department)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-800">{{ $department->dept_name }}</td>
                        <td class="px-6 py-4 text-slate-500 text-sm">
                            <div class="truncate max-w-md" title="{{ $department->dept_desc }}">
                                {{ $department->dept_desc }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-sm">{{ $department->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.departments.show', $department) }}" 
                                class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                                title="View Department Details">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                
                                @if($department->trashed())
                                    <!-- RESTORE BUTTON FORM WRAPPER -->
                                    <form action="{{ route('admin.departments.restore', $department->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" 
                                            class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" 
                                            title="Restore Department">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                            </svg>
                                        </button>
                                    </form>

                                    <!-- UNTOUCHED DELETE BUTTON -->
                                    <a href="#"
                                        class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                                        title="Permanently Delete Department">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </a>
                                @else
                                    <a href="{{ route('admin.departments.editForm', ['dept' => $department, 'source' => 'list']) }}" 
                                    class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" 
                                    title="Edit Department">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $department, 'source' => 'list']) }}"
                                        class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                                        title="Archive Department">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <!-- Archive icon -->
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-slate-400 text-sm">No departments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
<!-- Card View Container -->
    <div id="card-view-container" class="hidden grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-4">
        @forelse ($departments as $department)
            <!-- Added flex flex-col and h-full to ensure equal heights -->
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow relative flex flex-col h-full">
                
                <div class="absolute top-4 right-4 flex items-center gap-1">
                    <a href="{{ route('admin.departments.show', $department) }}" 
                    class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                    title="View Department Details">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </a> 
                    
                    @if($department->trashed())
                        <!-- RESTORE BUTTON FORM WRAPPER -->
                        <form action="{{ route('admin.departments.restore', $department->id) }}" method="POST" class="inline-block">
                            @csrf
                            @method('PATCH')
                            <button type="submit" 
                                class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" 
                                title="Restore Department">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                </svg>
                            </button>
                        </form>

                        <!-- UNTOUCHED DELETE BUTTON -->
                        <a href="{{ route('admin.departments.forceDeleteConfirm', $department) }}"
                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                            title="Permanently Delete Department">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <!-- Trash icon -->
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('admin.departments.editForm', ['dept' => $department, 'source' => 'list']) }}" 
                        class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" 
                        title="Edit Department">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </a>
                        <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $department, 'source' => 'list']) }}"
                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                            title="Archive Department">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                        </a>
                    @endif
                </div>
                               
                <h3 class="text-lg font-bold text-slate-800 pr-32">{{ $department->dept_name }}</h3>
                
                <!-- Added flex-grow so the description takes up available space, pushing the footer down -->
                <p class="text-sm text-slate-500 mt-3 leading-relaxed line-clamp-3 flex-grow" title="{{ $department->dept_desc }}">
                    {{ $department->dept_desc }}
                </p>

                <!-- NEW: Footer section for Created At -->
                <div class="mt-5 pt-4 border-t border-slate-100 flex items-center gap-2 text-xs font-medium text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Added {{ $department->created_at->format('M d, Y') }}
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 border-2 border-dashed border-slate-200 rounded-xl">
                No departments found.
            </div>
        @endforelse
    </div>
    <div class="mt-6">
        {{ $departments->onEachSide(0)->links() }}
    </div>
@endsection