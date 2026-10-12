@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Category Details')

@section('content')
    <!-- Back Navigation -->
    <div class="mb-6">
        <a href="{{ session('category_list_url', route('admin.ticketCategories')) }}" class="action-link inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors">
            <x-icons.arrow-left class="w-4 h-4" />
            <span>Back to Categories</span>
        </a>
    </div>

    <!-- Category Profile Card -->
    <article class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 lg:p-8 max-w-full mb-6">
        <header class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ $category->category_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">Created {{ $category->created_at->format('M d, Y') }}</p>
            </div>
            @if($category->trashed())
                <span class="bg-rose-100 text-rose-700 font-bold px-3 py-1 rounded-full text-xs uppercase tracking-wide border border-rose-200">Archived</span>
            @else
                <span class="bg-emerald-100 text-emerald-700 font-bold px-3 py-1 rounded-full text-xs uppercase tracking-wide border border-emerald-200">Active</span>
            @endif
        </header>

        <dl class="mb-8">
            <dt class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Category Description</dt>
            <dd class="text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100 text-sm">
                {{ $category->category_desc ?? 'No description provided for this category.' }}
            </dd>
        </dl>

        <footer class="flex gap-3 border-t border-slate-100 pt-6">
            @if(!$category->trashed())
                <a href="{{ route('admin.ticketCategories.editForm', ['category' => $category, 'source' => 'details']) }}" class="action-link btn btn-primary text-sm shadow-xs" data-loading-text="Loading...">
                    <x-icons.pencil class="w-4 h-4" />
                    <span>Edit Category</span>
                </a>
                <a href="{{ route('admin.ticketCategories.deleteConfirm', ['category' => $category, 'source' => 'details']) }}" class="action-link btn btn-secondary text-rose-700 hover:bg-rose-50 hover:border-rose-300 text-sm shadow-2xs" data-loading-text="Loading...">
                    <x-icons.trash class="w-4 h-4" />
                    <span>Archive Category</span>
                </a>
            @endif
        </footer>
    </article>

    <!-- Appended Full-Width Tickets Table -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6" aria-labelledby="category-tickets-heading">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <div>
                <h2 id="category-tickets-heading" class="text-base font-bold text-slate-800">Tickets Logged Under This Category</h2>
                <p class="text-xs text-slate-500 mt-1">Service requests historically linked to this classification.</p>
            </div>
            
            @if(method_exists($assignedTickets, 'total') && $assignedTickets->total() > 0)
                <span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $assignedTickets->total() }} {{ Str::plural('Ticket', $assignedTickets->total()) }}
                </span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Tickets logged under {{ $category->category_name }}</caption>
                <thead>
                    <tr class="bg-white border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-40">Ticket ID</th>
                        <th scope="col" class="px-6 py-4 w-40">Source</th>
                        <th scope="col" class="px-6 py-4 w-56">Full Address</th>
                        <th scope="col" class="px-6 py-4 w-48">Landmark</th>
                        <th scope="col" class="px-6 py-4 w-32 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignedTickets as $ticket)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <div class="font-bold text-emerald-600 text-sm">{{ $ticket->ticket_number }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $ticket->reported_at->format('M d, Y') }}</div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $ticket->complaint_source->label() }}</div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800">
                                    {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-600">
                                    {{ $ticket->landmark ?? '—' }}
                                </div>
                            </td>

                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $ticket->status->badgeClasses() }} text-[11px] font-bold">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3 text-slate-400">
                                    <x-icons.ticket class="w-6 h-6" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No tickets recorded</h3>
                                <p class="text-xs text-slate-500 mt-1">There are currently no tickets logged under this specific category.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($assignedTickets, 'hasPages') && $assignedTickets->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $assignedTickets->onEachSide(0)->links() }}
            </div>
        @endif
    </section>
@endsection
