@extends('admin.base.base')

@section('title', $title)

@section('content')
    <div class="max-w-2xl mx-auto mt-10">
        
        <!-- Breadcrumb / Back Link -->
        <div class="mb-6">
            <a href="{{ route('admin.teams') }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors" data-loading-text="Returning...">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Teams
            </a>
        </div>

        <!-- Confirmation Card -->
        <div class="bg-white border {{ $isForceDelete ? 'border-red-200' : 'border-rose-200' }} rounded-xl shadow-sm overflow-hidden">
            
            <div class="p-6 sm:p-10 text-center">
                <!-- Warning Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full {{ $isForceDelete ? 'bg-red-100' : 'bg-rose-100' }} mb-6">
                    <svg class="h-8 w-8 {{ $isForceDelete ? 'text-red-600' : 'text-rose-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ $title }}?</h2>
                
                <p class="text-slate-600 mb-6 leading-relaxed">
                    You are about to {{ $isForceDelete ? 'permanently delete' : 'archive' }} the <span class="font-bold text-slate-900">"{{ $team->team_name }}"</span> team. 
                    @if($isForceDelete)
                        This action is irreversible, and all system logs referencing this team will lose their subject association.
                    @else
                        This will remove it from active selection lists across the system. 
                    @endif
                    Are you absolutely sure you want to proceed?
                </p>

                <!-- Context Box -->
                <div class="{{ $isForceDelete ? 'bg-red-50 border-red-100 text-red-800' : 'bg-rose-50 border-rose-100 text-rose-800' }} border rounded-lg p-4 mb-8 text-left text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @if($isForceDelete)
                            <li class="font-bold">This team will be destroyed entirely.</li>
                            <li>The current member roster will be unassigned and permanently wiped from this team.</li>
                            <li>You CANNOT restore this team later.</li>
                        @else
                            <li>The team record will be hidden, but not permanently deleted.</li>
                            <li>Existing membership records tied to this team will remain intact.</li>
                            <li>You can restore this team later from the system archives.</li>
                        @endif
                    </ul>
                </div>

                <!-- Form & Actions -->
                <form action="{{ $isForceDelete ? route('admin.teams.destroy', $team) : route('admin.teams.archive', $team) }}" method="POST" class="flex flex-col-reverse sm:flex-row justify-center gap-3">
                    @csrf
                    @method('DELETE')
                    
                    <a href="{{ route('admin.teams') }}" class="action-link inline-flex justify-center items-center px-5 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 w-full sm:w-auto" data-loading-text="Canceling...">
                        Cancel
                    </a>
                    
                    <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 {{ $isForceDelete ? 'bg-red-600 hover:bg-red-700 shadow-red-700/20 focus:ring-red-500' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-700/20 focus:ring-rose-500' }} text-white rounded-lg text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 w-full sm:w-auto" data-loading-text="{{ $isForceDelete ? 'Deleting...' : 'Archiving...' }}">
                        @if($isForceDelete)
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Yes, Permanently Delete
                        @else
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                            </svg>
                            Yes, Archive Team
                        @endif
                    </button>
                </form>
            </div>
            
        </div>
    </div>
@endsection