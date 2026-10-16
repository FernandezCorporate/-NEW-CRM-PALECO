@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($category) ? 'Edit Category' : 'Create New Category')

@section('content')
@php
    $source = old('source', request('source'));
    $cancelRoute = ($source === 'details' && isset($category))
        ? route('admin.ticketCategories.show', $category)
        : session('category_list_url', route('admin.ticketCategories'));
    $backLabel = ($source === 'details' && isset($category))
        ? 'Back to Category Details'
        : 'Back to Categories';
@endphp

<div class="max-w-2xl mx-auto py-4 space-y-6">
    <!-- Back Navigation -->
    <div>
        <x-contextual-back :url="$cancelRoute" :label="$backLabel" variant="link" />
    </div>

    <header class="mb-2">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($category) ? 'Edit Category' : 'Create New Category' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($category) ? 'Update the details of this category.' : 'Add a new ticket category.' }}</p>
    </header>

    <x-alert />

    <article class="card overflow-hidden">
        <form action="{{ isset($category) ? route('admin.ticketCategories.update', ['category' => $category, 'source' => request('source')]) : route('admin.ticketCategories.store') }}" method="POST">
            @csrf

            @isset($category)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $category->updated_at }}">
            @endisset
            @if(request()->filled('source') || old('source'))
                <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
            @endif
            
            <div class="p-6 md:p-8 space-y-6">
                <fieldset class="space-y-5">
                    <legend class="sr-only">Category Information</legend>
                    <div class="border-b border-slate-200 pb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <x-icons.ticket class="w-4 h-4 text-emerald-600" />
                            <span>Complaint Category Details</span>
                        </h2>
                        <span class="text-xs text-slate-500"><span class="text-rose-500 font-bold">*</span> Required field</span>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <x-form.label for="category_name" :required="true">Category Name</x-form.label>
                            <x-form.input name="category_name" id="category_name" :value="$category->category_name ?? null" required placeholder="e.g., Low Voltage / Power Fluctuation" />
                            <x-form.error name="category_name" />
                        </div>

                        <div>
                            <x-form.label for="category_desc">Category Description</x-form.label>
                            <x-form.textarea name="category_desc" id="category_desc" :value="$category->category_desc ?? null" placeholder="Briefly describe the complaint types classified under this category..." rows="4" />
                            <p class="mt-1.5 text-[11px] text-slate-400">Optional. Guidance notes for CWD operators when triaging tickets under this category.</p>
                            <x-form.error name="category_desc" />
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="card-footer px-6 md:px-8 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    <x-icons.check-circle class="w-4 h-4" />
                    <span>{{ isset($category) ? 'Update Category' : 'Save Category' }}</span>
                </x-btn>
            </div>
        </form>
    </article>
</div>
@endsection
