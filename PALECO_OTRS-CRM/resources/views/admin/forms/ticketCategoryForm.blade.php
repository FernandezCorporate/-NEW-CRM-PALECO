@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($category) ? 'Edit Category' : 'Create New Category')

@section('content')
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($category) ? 'Edit Category' : 'Create New Category' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($category) ? 'Update the details of this category.' : 'Add a new ticket category.' }}</p>
    </header>

    <x-alert />

    <article class="card max-w-2xl overflow-hidden">
        <form action="{{ isset($category) ? route('admin.ticketCategories.update', ['category' => $category, 'source' => request('source')]) : route('admin.ticketCategories.store') }}" method="POST">
            @csrf

            @isset($category)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $category->updated_at }}">
            @endisset
            @if(request()->filled('source') || old('source'))
                <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
            @endif
            
            <div class="card-body space-y-6">
                <div>
                    <x-form.label for="category_name" :required="true">Category Name</x-form.label>
                    <x-form.input name="category_name" id="category_name" :value="$category->category_name ?? null" required placeholder="Enter Category Name" />
                    <x-form.error name="category_name" />
                </div>

                <div>
                    <x-form.label for="category_desc">Category Description</x-form.label>
                    <x-form.textarea name="category_desc" id="category_desc" :value="$category->category_desc ?? null" placeholder="Enter Category Description" rows="4" />
                    <p class="mt-1.5 text-[11px] text-slate-400">Optional. Briefly describe the properties of this category.</p>
                </div>
            </div>

            @php
                $cancelRoute = (old('source', request('source')) === 'details' && isset($category))
                    ? route('admin.ticketCategories.show', $category)
                    : session('category_list_url', route('admin.ticketCategories'));
            @endphp

            <div class="card-footer flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    Save Category
                </x-btn>
            </div>
        </form>
    </article>
@endsection
