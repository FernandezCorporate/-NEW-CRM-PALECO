@extends('admin.base.base')

@section('title', 'Category Details')

@section('content')
    <div class="mb-6">
        <a href="{{ session('category_list_url', route('admin.ticketCategories')) }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Categories
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8 max-w-full mb-6">
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
                <a href="{{ route('admin.ticketCategories.editForm', ['category' => $category, 'source' => 'details']) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Edit Category</a>
                <a href="{{ route('admin.ticketCategories.deleteConfirm', ['category' => $category, 'source' => 'details']) }}" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Archive Category</a>
            @endif
        </div>
    </div>

    <!-- Appended Full-Width Tickets Table[cite: 10] -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Tickets Logged Under This Category</h3>
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
                <thead>
                    <tr class="bg-white border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-40">Ticket ID</th>
                        <th class="px-6 py-4 w-40">Source</th>
                        <th class="px-6 py-4 w-56">Full Address</th>
                        <th class="px-6 py-4 w-48">Landmark</th>
                        <th class="px-6 py-4 w-32 text-center">Status</th>
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
                                @php
                                    $statusColor = match($ticket->status->value) {
                                        'open' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'assigned' => 'bg-purple-50 text-purple-700 border-purple-200',
                                        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'closed' => 'bg-slate-100 text-slate-700 border-slate-300',
                                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $statusColor }} text-[11px] font-bold">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
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
    </div>
@endsection