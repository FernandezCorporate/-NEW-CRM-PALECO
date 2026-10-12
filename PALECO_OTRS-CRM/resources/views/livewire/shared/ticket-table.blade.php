<div>
    <nav class="record-tabs" aria-label="Filter tickets by status">
        <a href="#" wire:click.prevent="$set('status', 'all')" @if($status === 'all') aria-current="page" @endif>All tickets</a>
        @foreach($statuses as $stat)
            <a href="#" wire:click.prevent="$set('status', '{{ $stat->value }}')" @if($status === $stat->value) aria-current="page" @endif>{{ $stat->label() }}</a>
        @endforeach
    </nav>

    <!-- Search & Filter Controls -->
    <search class="filter-toolbar mb-6" aria-label="Ticket queue search and filters">
        <div class="flex flex-col lg:flex-row gap-4 items-center w-full">
            
            <!-- Wide Search Bar -->
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <x-icons.search class="h-5 w-5 text-slate-400" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tickets by ID, location, or description..." class="form-input w-full pl-10 pr-3" aria-label="Search tickets by ID, location, or description">
            </div>

            <!-- Filter Dropdowns -->
            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0 flex-wrap">
                <select wire:model.live="filter" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Filter by category">
                    <option value="all">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                    @endforeach
                    <option value="other">Custom/Other Categories</option>
                </select>

                <select wire:model.live="department" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Filter by assigned department">
                    <option value="all">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                    @endforeach
                    <option value="unassigned">Unassigned</option>
                </select>

                <select wire:model.live="sort" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Sort tickets">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="status">Sort by Status</option>
                </select>

                <!-- Loading Indicator -->
                <div wire:loading class="btn btn-secondary btn-md opacity-75 cursor-wait">
                    Updating...
                </div>
            </div>
        </div>
    </search>

    <!-- Main Table Container -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6 relative">
        <div wire:loading.class="flex" class="hidden absolute inset-0 bg-white/50 z-10 items-center justify-center backdrop-blur-xs"></div>

        <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ticket queue <span class="record-count ml-2">{{ number_format($tickets->total()) }}</span></h2>
                <p class="text-xs text-slate-500 mt-0.5">Service requests matching your current filters</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="ticket-queue system-table w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Support tickets queue and real-time dispatches</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-40">Ticket ID</th>
                        <th scope="col" class="px-6 py-4 w-40">Source / Caller</th>
                        <th scope="col" class="px-6 py-4 w-56">Address & Landmark</th>
                        <th scope="col" class="px-6 py-4 w-48">Nature of Complaint</th>
                        <th scope="col" class="px-6 py-4 w-40 text-center">Assigned Dept</th>
                        <th scope="col" class="px-6 py-4 w-32 text-center">Status</th>
                        <th scope="col" class="px-6 py-4 w-40 text-center">Hierarchy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $tkt)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="px-6 py-4 align-top">
                                @php
                                    $showRoute = auth()->user()?->role?->slug_identifier === 'admin'
                                        ? route('admin.tickets.show', $tkt)
                                        : route('cwd.tickets.show', $tkt);
                                @endphp
                                <a href="{{ $showRoute }}" class="font-bold text-emerald-700 text-sm hover:underline block" aria-label="View ticket #{{ $tkt->ticket_number }}">
                                    {{ $tkt->ticket_number }}
                                </a>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $tkt->reported_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $tkt->complaint_source->label() }}</div>
                                @if($tkt->consumer_id)
                                    <div class="text-xs text-slate-500 mt-0.5">Acc: {{ $tkt->consumer->acct_code }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800">
                                    {{ implode(', ', array_filter([$tkt->purok, $tkt->street, $tkt->barangay])) }}
                                </div>
                                @if($tkt->landmark)
                                    <div class="text-[11px] text-slate-500 mt-1 flex items-start gap-1">
                                        <x-icons.map-pin class="w-3.5 h-3.5 shrink-0 mt-0.5 text-slate-400" />
                                        <span>{{ Str::limit($tkt->landmark, 40) }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">
                                    {{ $tkt->other_category ? $tkt->other_category_name : ($tkt->category->category_name ?? 'Unspecified') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <x-badge variant="emerald">
                                    {{ $tkt->department->dept_name ?? 'Unassigned' }}
                                </x-badge>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $tkt->status->badgeClasses() }} text-[11px] font-bold">
                                    {{ $tkt->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                @if(is_null($tkt->parent_ticket_id))
                                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Parent</div>
                                    <div class="font-bold text-slate-300 text-xs mt-0.5">—</div>
                                @else
                                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600">Child</div>
                                    <div class="font-bold text-emerald-700 text-xs mt-0.5">
                                        @if($tkt->parentTicket)
                                            @php
                                                $parentRoute = auth()->user()?->role?->slug_identifier === 'admin'
                                                    ? route('admin.tickets.show', $tkt->parentTicket)
                                                    : route('cwd.tickets.show', $tkt->parentTicket);
                                            @endphp
                                            <a href="{{ $parentRoute }}" class="hover:underline">
                                                {{ $tkt->parentTicket->ticket_number }}
                                            </a>
                                        @else
                                            Unknown
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.ticket class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No tickets found</h3>
                                <p class="text-xs text-slate-500 mt-1">Adjust your search or filter criteria to find what you're looking for.</p>
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
