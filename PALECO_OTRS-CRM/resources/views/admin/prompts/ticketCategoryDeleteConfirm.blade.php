@extends('admin.base.base')

@section('title', $title)

@section('content')
    <div class="max-w-2xl mx-auto mt-10">
        <div class="mb-6">
            <a href="{{ request('source') === 'details' ? route('admin.ticketCategories.show', $category) : session('category_list_url', route('admin.ticketCategories')) }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors" data-loading-text="Returning...">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Categories
            </a>
        </div>

        <div class="bg-white border {{ $isForceDelete ? 'border-red-200' : 'border-rose-200' }} rounded-xl shadow-sm overflow-hidden">
            <div class="p-6 sm:p-10 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full {{ $isForceDelete ? 'bg-red-100' : 'bg-rose-100' }} mb-6">
                    <svg class="h-8 w-8 {{ $isForceDelete ? 'text-red-600' : 'text-rose-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ $title }}?</h2>
                <p class="text-slate-600 mb-6 leading-relaxed">
                    You are about to {{ $isForceDelete ? 'permanently delete' : 'archive' }} the <span class="font-bold text-slate-900">"{{ $category->category_name }}"</span> category. 
                    @if($isForceDelete) This action is irreversible. @else This will hide it from future ticket creation. @endif
                    Are you sure?
                </p>

                <form action="{{ $isForceDelete ? route('admin.ticketCategories.destroy', $category) : route('admin.ticketCategories.archive', $category) }}" method="POST" class="flex flex-col-reverse sm:flex-row justify-center gap-3">
                    @csrf @method('DELETE')
                    <a href="{{ request('source') === 'details' ? route('admin.ticketCategories.show', $category) : session('category_list_url', route('admin.ticketCategories')) }}" class="action-link inline-flex justify-center items-center px-5 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 w-full sm:w-auto">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex justify-center items-center px-5 py-2.5 {{ $isForceDelete ? 'bg-red-600 hover:bg-red-700' : 'bg-rose-600 hover:bg-rose-700' }} text-white rounded-lg text-sm font-medium w-full sm:w-auto">
                        Yes, {{ $isForceDelete ? 'Permanently Delete' : 'Archive Category' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection