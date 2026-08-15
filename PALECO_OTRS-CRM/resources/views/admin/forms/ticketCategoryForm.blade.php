@extends('admin.base.base')

@section('title', isset($category) ? 'Edit Category' : 'Create New Category')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($category) ? 'Edit Category' : 'Create New Category' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($category) ? 'Update the details of this category.' : 'Add a new ticket category.' }}</p>
    </div>

    @include('admin.prompts.alert')

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
        <!-- Dynamically switch between Update and Store routes -->
        <form action="{{ isset($category) ? route('admin.ticketCategories.update', ['category' => $category, 'source' => request('source')]) : route('admin.ticketCategories.store') }}" method="POST">
            @csrf

            @isset($category)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $category->updated_at }}">
            @endisset
            
            <div class="p-6 space-y-6">
                <div>
                    <label for="category_name" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Category Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="category_name" name="category_name" value="{{ old('category_name', $category->category_name ?? '') }}" required placeholder="Enter Category Name"
                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-4 focus:ring-emerald-500/10 
                        @error('category_name') border-rose-300 focus:border-rose-500 @else border-slate-200 focus:border-emerald-500 @enderror">
                    
                    @error('category_name')
                        <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="category_desc" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Category Description
                    </label>
                    <textarea id="category_desc" name="category_desc" rows="4" placeholder="Enter Category Description"
                        class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm transition-colors focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">{{ old('category_desc', $category->category_desc ?? '') }}</textarea>
                    <p class="mt-1.5 text-[11px] text-slate-400">Optional. Briefly describe the properties of this category.</p>
                </div>
            </div>

            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <!-- Smart Cancel Button -->
                <a href="{{ request('source') === 'details' && isset($category) ? route('admin.ticketCategories.show', $category) : session('category_list_url', route('admin.ticketCategories')) }}" class="action-link px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-200 rounded-lg transition-colors" data-loading-text="Canceling...">
                    Cancel
                </a>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Saving...">
                    Save Category
                </button>
            </div>
            
        </form>
    </div>
@endsection