@extends('admin.base.base')

@section('title', 'Ticket Category Management')

@section('content')
    <x-alert />

    <!-- Top Header Row -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Ticket Category Management</h1>
            <p class="text-sm text-slate-500 mt-1">Manage complaint categories used during ticket creation</p>
        </div>
        
        <x-btn href="{{ route('admin.ticketCategories.createForm') }}" variant="primary" class="action-link" data-loading-text="Loading...">
            <x-icons.plus class="w-4 h-4 mr-1.5" />
            New Category
        </x-btn>
    </header>

    <!-- Controls Row -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <search class="filter-toolbar w-full lg:w-auto" aria-label="Ticket category search and filters">
            <form action="{{ route('admin.ticketCategories') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
                <!-- Search -->
                <div class="flex items-center gap-2">
                    <input type="text" name="search" placeholder="Search categories..." value="{{ request('search') }}" class="form-input w-full md:w-64" aria-label="Search categories by name or description">
                    <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                    @if(request()->filled('search'))
                        <x-btn href="{{ route('admin.ticketCategories', request()->except('search')) }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">Clear</x-btn>
                    @endif
                </div>

                <!-- Filter and Sort Group -->
                <div class="flex items-center gap-2">
                    <select name="filter" class="ts-filter-dropdown" aria-label="Filter active or archived categories">
                        <option value="active" {{ request('filter') === 'active' ? 'selected' : '' }}>Active Categories</option>
                        <option value="archived" {{ request('filter') === 'archived' ? 'selected' : '' }}>Archived Categories</option>
                    </select>

                    <select name="sort" class="ts-filter-dropdown" aria-label="Sort categories">
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                        <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                        <option value="category_nameASC" {{ request('sort') === 'category_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                        <option value="category_nameDESC" {{ request('sort') === 'category_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                    </select>
                    <noscript><x-btn type="submit" variant="secondary" size="md">Apply</x-btn></noscript>
                </div>
            </form>
        </search>

        <!-- View Toggle Buttons -->
        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-xs shrink-0" role="group" aria-label="View format toggle">
            <button id="list-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="List View" aria-label="Switch to list view" aria-pressed="true">
                <x-icons.list-bullet class="w-5 h-5" />
            </button>
            <div class="w-px h-5 bg-slate-200"></div>
            <button id="card-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="Card View" aria-label="Switch to card view" aria-pressed="false">
                <x-icons.squares-2x2 class="w-5 h-5" />
            </button>
        </div>
    </div>

    <!-- List View -->
    <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ticket categories <span class="record-count ml-2">{{ number_format($categories->total()) }}</span></h2>
                <p class="text-xs text-slate-500 mt-0.5">Classification taxonomy, issue tagging, and routing rules</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse min-w-[900px]">
                <caption class="sr-only">Ticket categories directory and usage metrics</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-60">Category Name</th>
                        <th scope="col" class="px-6 py-4 w-80">Description</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Usage Volume</th>
                        <th scope="col" class="px-6 py-4 w-40">Date Created</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <a href="{{ route('admin.ticketCategories.show', $category) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                    {{ $category->category_name }}
                                </a>
                                @if($category->trashed())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200 ml-1.5">Archived</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-slate-600 text-sm max-w-xs" title="{{ $category->category_desc }}">
                                {{ $category->category_desc ?? '—' }}
                            </td>
                            
                            <!-- Dynamic Ticket Count Display -->
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ $category->ticket_count }} {{ Str::plural('Ticket', $category->ticket_count) }}
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $category->created_at?->format('M d, Y') ?? 'N/A' }}</div>
                                @if($category->created_at)
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $category->created_at->format('h:i A') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.ticketCategories.show', $category) }}" class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" title="View Details" aria-label="View details for {{ $category->category_name }}">
                                        <x-icons.eye class="w-4 h-4" />
                                    </a>
                                    @if($category->trashed())
                                        <form action="{{ route('admin.ticketCategories.restore', $category->id) }}" method="POST" class="inline-block">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Restore" aria-label="Restore {{ $category->category_name }}">
                                                <x-icons.arrow-path class="w-4 h-4" />
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.ticketCategories.forceDeleteConfirm', $category) }}" class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Permanently Delete" aria-label="Permanently delete {{ $category->category_name }}">
                                            <x-icons.trash class="w-4 h-4" />
                                        </a>
                                    @else
                                        <a href="{{ route('admin.ticketCategories.editForm', ['category' => $category, 'source' => 'list']) }}" class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" title="Edit" aria-label="Edit {{ $category->category_name }}">
                                            <x-icons.pencil class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('admin.ticketCategories.deleteConfirm', ['category' => $category, 'source' => 'list']) }}" class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Archive" aria-label="Archive {{ $category->category_name }}">
                                            <x-icons.trash class="w-4 h-4" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.category class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No categories found</h3>
                                <p class="text-xs text-slate-500 mt-1">Adjust your search or filter to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Card View -->
    <div id="card-view-container" class="hidden grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-4">
        @foreach ($categories as $category)
            <article class="bg-white border border-slate-200 rounded-xl p-6 shadow-xs flex flex-col h-full relative">
                <div class="flex justify-between items-start mb-4 gap-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-bold text-slate-900 leading-tight break-words">{{ $category->category_name }}</h3>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 bg-slate-50 p-1 rounded-lg border border-slate-100">
                        <a href="{{ route('admin.ticketCategories.show', $category) }}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-100 rounded-md transition-colors" aria-label="View details for {{ $category->category_name }}">
                            <x-icons.eye class="w-4 h-4" />
                        </a>
                        @if($category->trashed())
                            <form action="{{ route('admin.ticketCategories.restore', $category->id) }}" method="POST" class="inline-block">
                                @csrf @method('PATCH')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-100 rounded-md transition-colors" aria-label="Restore {{ $category->category_name }}">
                                    <x-icons.arrow-path class="w-4 h-4" />
                                </button>
                            </form>
                            <a href="{{ route('admin.ticketCategories.forceDeleteConfirm', $category) }}" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" aria-label="Permanently delete {{ $category->category_name }}">
                                <x-icons.trash class="w-4 h-4" />
                            </a>
                        @else
                            <a href="{{ route('admin.ticketCategories.editForm', ['category' => $category, 'source' => 'list']) }}" class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-100 rounded-md transition-colors" aria-label="Edit {{ $category->category_name }}">
                                <x-icons.pencil class="w-4 h-4" />
                            </a>
                            <a href="{{ route('admin.ticketCategories.deleteConfirm', ['category' => $category, 'source' => 'list']) }}" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-md transition-colors" aria-label="Archive {{ $category->category_name }}">
                                <x-icons.trash class="w-4 h-4" />
                            </a>
                        @endif
                    </div>
                </div>
                <p class="text-sm text-slate-500 mb-6 flex-grow line-clamp-3">{{ $category->category_desc ?? 'No description provided.' }}</p>
                <div class="pt-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-500">
                    <div class="flex items-center gap-1.5 font-medium">Added {{ $category->created_at?->format('M d, Y') ?? 'N/A' }}</div>
                    
                    <!-- Dynamic Ticket Count Display -->
                    <div class="flex items-center gap-1.5">
                        <x-badge variant="indigo">
                            {{ $category->ticket_count }} tickets
                        </x-badge>
                        @if($category->trashed())
                            <x-badge variant="slate">Archived</x-badge>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-4">{{ $categories->onEachSide(0)->links() }}</div>
@endsection