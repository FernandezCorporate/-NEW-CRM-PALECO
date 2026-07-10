@extends('admin.base.base')

@section('title', 'Category Details')

@section('content')
    <div class="mb-6">
        <a href="{{ session('category_list_url', route('admin.ticketCategories')) }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Categories
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8 max-w-3xl">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ $category->category_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">Created {{ $category->created_at->format('M d, Y') }}</p>
            </div>
            @if($category->trashed())
                <span class="bg-rose-100 text-rose-700 font-bold px-3 py-1 rounded text-xs uppercase tracking-wide">Archived</span>
            @else
                <span class="bg-emerald-100 text-emerald-700 font-bold px-3 py-1 rounded text-xs uppercase tracking-wide">Active</span>
            @endif
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-2">Description</h3>
            <p class="text-slate-700">{{ $category->category_desc ?? 'No description provided for this category.' }}</p>
        </div>

        <div class="flex gap-3 border-t border-slate-100 pt-6">
            @if(!$category->trashed())
                <a href="{{ route('admin.ticketCategories.editForm', ['category' => $category, 'source' => 'details']) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Edit Category</a>
                <a href="{{ route('admin.ticketCategories.deleteConfirm', ['category' => $category, 'source' => 'details']) }}" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-4 py-2 rounded-lg text-sm font-medium">Archive Category</a>
            @endif
        </div>
    </div>
@endsection