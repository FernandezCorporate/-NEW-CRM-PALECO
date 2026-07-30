@extends('admin.base.base')

@section('title', 'Deactivate User Account')

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
        <div class="bg-white border border-amber-200 rounded-xl shadow-sm overflow-hidden">
            
            <div class="p-6 sm:p-10 text-center">
                <!-- Warning Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-6">
                    <svg class="h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                
                <h2 class="text-2xl font-bold text-slate-900 mb-2">Deactivate Account?</h2>
                
                <p class="text-slate-600 mb-6 leading-relaxed">
                    You are about to suspend system access for <span class="font-bold text-slate-900">"{{ $userAccount->username }}"</span>. 
                    They will immediately lose the ability to log into the application.
                    Are you sure you want to proceed?
                </p>

                <!-- Context Box -->
                <div class="bg-amber-50 border-amber-100 text-amber-800 border rounded-lg p-4 mb-8 text-left text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        <li class="font-bold">The user will be blocked from logging in.</li>
                        <li>Their historical activity logs will remain intact.</li>
                        <li>If the user is a field personnel account, their team memberships will be removed.</li>
                        <li>You can reactivate this account later from the user management dashboard.</li>
                    </ul>
                </div>

                <!-- Form & Actions -->
                <form action="{{ route('admin.users.deactivate', $userAccount) }}" method="POST" class="flex flex-col-reverse sm:flex-row justify-center gap-3">
                    @csrf
                    @method('PATCH')
                    
                    <a href="{{ route('admin.users') }}" class="action-link inline-flex justify-center items-center px-5 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 w-full sm:w-auto" data-loading-text="Canceling...">
                        Cancel
                    </a>
                    
                    <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 bg-amber-500 hover:bg-amber-600 shadow-amber-600/20 focus:ring-amber-500 text-white rounded-lg text-sm font-medium shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 w-full sm:w-auto" data-loading-text="Deactivating...">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        Yes, Deactivate Account
                    </button>
                </form>
            </div>
            
        </div>
    </div>
@endsection