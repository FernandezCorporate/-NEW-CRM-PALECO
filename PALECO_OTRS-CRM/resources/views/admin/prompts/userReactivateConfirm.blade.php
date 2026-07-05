@extends('admin.base.base')

@section('title', 'Reactivate User Account')

@section('content')
    <div class="max-w-2xl mx-auto mt-10">
        
        <!-- Breadcrumb / Back Link -->
        <div class="mb-6">
            <a href="{{ route('admin.users') }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors" data-loading-text="Returning...">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Users
            </a>
        </div>

        <!-- Confirmation Card -->
        <div class="bg-white border border-emerald-200 rounded-xl shadow-sm overflow-hidden">
            
            <div class="p-6 sm:p-10 text-center">
                <!-- Shield Check / Restore Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 mb-6">
                    <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                
                <h2 class="text-2xl font-bold text-slate-900 mb-2">Reactivate Account?</h2>
                
                <p class="text-slate-600 mb-6 leading-relaxed">
                    You are about to restore system access for <span class="font-bold text-slate-900">"{{ $userAccount->username }}"</span>. 
                    They will immediately regain the ability to log into the application.
                    Are you sure you want to proceed?
                </p>

                <!-- Context Box -->
                <div class="bg-emerald-50 border-emerald-100 text-emerald-800 border rounded-lg p-4 mb-8 text-left text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        <li class="font-bold">The user will be able to securely log in normally.</li>
                        <li>Their historical activity logs and records remain intact.</li>
                        <li>You can suspend this account again later if needed.</li>
                    </ul>
                </div>

                <!-- Form & Actions -->
                <form action="{{ route('admin.users.reactivate', $userAccount) }}" method="POST" class="flex flex-col-reverse sm:flex-row justify-center gap-3">
                    @csrf
                    @method('PATCH')
                    
                    <a href="{{ route('admin.users') }}" class="action-link inline-flex justify-center items-center px-5 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 w-full sm:w-auto" data-loading-text="Canceling...">
                        Cancel
                    </a>
                    
                    <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20 focus:ring-emerald-500 text-white rounded-lg text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 w-full sm:w-auto" data-loading-text="Reactivating...">
                        <!-- Uses the undo/restore icon -->
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        Yes, Reactivate Account
                    </button>
                </form>
            </div>
            
        </div>
    </div>
@endsection