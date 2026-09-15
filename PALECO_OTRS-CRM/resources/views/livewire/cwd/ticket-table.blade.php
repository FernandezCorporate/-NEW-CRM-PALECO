<div>
    <!-- Restored: <a> tags so your CSS (.record-tabs a) applies perfectly -->
    <nav class="record-tabs" aria-label="Filter tickets by status">
        <a href="#" wire:click.prevent="$set('status', 'all')" @if($status === 'all') aria-current="page" @endif>All tickets</a>
        @foreach($statuses as $stat)
            <a href="#" wire:click.prevent="$set('status', '{{ $stat->value }}')" @if($status === $stat->value) aria-current="page" @endif>{{ $stat->label() }}</a>
        @endforeach
    </nav>

    <!-- Restored: Original Search & Filter Layout -->
    <div class="mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-center w-full">
            
            <!-- Wide Search Bar -->
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tickets by ID, location, or description..." class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
            </div>

            <!-- Filter Dropdowns (Styled to match your Tom Select look) -->
            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <select wire:model.live="filter" style="min-height: 42px; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.625rem 0.75rem; font-size: 0.875rem; color: #334155; outline: none; background-color: white;">
                    <option value="all">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                    @endforeach
                    <option value="other">Custom/Other Categories</option>
                </select>

                <select wire:model.live="sort" style="min-height: 42px; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.625rem 0.75rem; font-size: 0.875rem; color: #334155; outline: none; background-color: white;">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="status">Sort by Status</option>
                </select>

                <!-- Loading Indicator styled like your old search button -->
                <div wire:loading class="queue-search opacity-75 cursor-wait">Updating...</div>
            </div>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6 relative">
        <div wire:loading.class="flex" class="hidden absolute inset-0 bg-white/50 z-10 items-center justify-center backdrop-blur-sm"></div>

        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Ticket queue <span class="record-count">{{ number_format($tickets->total()) }}</span></h2>
            <p class="text-xs text-gray-500 mt-1">Service requests matching your current filters</p>
        </div>

        <div class="overflow-x-auto">
            <table class="ticket-queue w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-40">Ticket ID</th>
                        <th class="px-6 py-4 w-40">Source / Caller</th>
                        <th class="px-6 py-4 w-56">Address & Landmark</th>
                        <th class="px-6 py-4 w-48">Nature of Complaint</th>
                        <th class="px-6 py-4 w-40 text-center">Assigned Dept</th>
                        <th class="px-6 py-4 w-32 text-center">Status</th>
                        <th class="px-6 py-4 w-40 text-center">Hierarchy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tickets as $tkt)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <a href="{{ route('cwd.tickets.show', $tkt) }}" class="font-bold text-[#008f5d] text-sm hover:underline hover:text-emerald-700 transition-colors block">
                                    {{ $tkt->ticket_number }}
                                </a>
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $tkt->reported_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800 font-medium">{{ $tkt->complaint_source->label() }}</div>
                                @if($tkt->consumer_id)
                                    <div class="text-xs text-gray-500 mt-0.5">Acc: {{ $tkt->consumer->acct_code }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800">
                                    {{ implode(', ', array_filter([$tkt->purok, $tkt->street, $tkt->barangay])) }}
                                </div>
                                @if($tkt->landmark)
                                    <div class="text-[11px] text-gray-500 mt-1 flex items-start gap-1">
                                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        {{ Str::limit($tkt->landmark, 40) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-800 font-medium">
                                    {{ $tkt->other_category ? $tkt->other_category_name : ($tkt->category->category_name ?? 'Unspecified') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-green-50 text-green-700 border border-green-200 text-[11px] font-bold tracking-wide">
                                    {{ $tkt->department->dept_name ?? 'Unassigned' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                @php
                                    $statusColor = match($tkt->status->value) {
                                        'open' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'assigned' => 'bg-purple-50 text-purple-700 border-purple-200',
                                        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'closed' => 'bg-gray-100 text-gray-700 border-gray-300',
                                        default => 'bg-gray-100 text-gray-600 border-gray-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $statusColor }} text-[11px] font-bold">
                                    {{ $tkt->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                @if(is_null($tkt->parent_ticket_id))
                                    <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Parent</div>
                                    <div class="font-bold text-gray-300 text-xs mt-0.5">—</div>
                                @else
                                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600">Child</div>
                                    <div class="font-bold text-[#008f5d] text-xs mt-0.5">
                                        {{ $tkt->parentTicket->ticket_number ?? 'Unknown' }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center border-2 border-dashed border-gray-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900">No tickets found</h3>
                                <p class="text-xs text-gray-500 mt-1">Adjust your search or filter criteria to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($tickets->hasPages())
        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    @endif
</div>