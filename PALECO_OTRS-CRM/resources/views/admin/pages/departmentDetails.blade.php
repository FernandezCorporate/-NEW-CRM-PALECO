@extends('admin.base.base')

@section('title', 'Department Details')

@section('content')
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $dept->dept_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">Detailed view of department information and assignments</p>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.departments') }}" class="action-link inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Loading...">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
            
            @if($dept->trashed())
                <form action="{{ route('admin.departments.restore', $dept) }}" method="POST" class="inline-block">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-2 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Restoring...">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        Restore
                    </button>
                </form>
                <a href="{{ route('admin.departments.forceDeleteConfirm', $dept) }}" class="action-link inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-rose-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Force Delete
                </a>
            @else
                <a href="{{ route('admin.departments.editForm', ['dept' => $dept, 'source' => 'details']) }}" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit
                </a>

                <a href="{{ route('admin.departments.deleteConfirm', ['dept' => $dept, 'source' => 'details']) }}" class="action-link inline-flex items-center gap-2 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200 hover:border-transparent px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-[0.98]" data-loading-text="Loading...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                    Archive
                </a>
            @endif
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Primary Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">General Information</h3>
                </div>
                
                <div class="p-6 space-y-6">
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Department Name</h4>
                        <p class="text-base text-slate-900 font-medium">{{ $dept->dept_name }}</p>
                    </div>
                    
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Description</h4>
                        <p class="text-base text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
                            {{ $dept->dept_desc ?? 'No description provided.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Meta & Stats -->
        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-lg font-bold text-slate-800">System Details</h3>
                </div>
                
                <div class="p-6 space-y-4 text-sm">
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Status</span>
                        @if($dept->trashed())
                            <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Archived</span>
                        @else
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Active</span>
                        @endif
                    </div>
                    
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Assigned Personnel</span>
                        <span class="text-slate-800 font-bold bg-slate-100 px-2.5 py-1 rounded-md">#Number Count#</span>
                    </div>

                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Date Created</span>
                        <span class="text-slate-800">{{ $dept->created_at->format('M d, Y') }}</span>
                    </div>
                    
                    @if($dept->trashed())
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Date Archived</span>
                            <span class="text-slate-800">{{ $dept->deleted_at->format('M d, Y - H:i') }}</span>
                        </div>
                    @endif
                    
                    <div class="flex justify-between items-center py-2">
                        <span class="text-slate-500 font-medium">Last Modified</span>
                        <span class="text-slate-800">{{ $dept->updated_at->format('M d, Y - H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
@endsection