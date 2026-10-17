<div>
    <nav class="record-tabs" aria-label="Filter tickets by status">
        <a href="#" wire:click.prevent="$set('status', 'all')" @if($status === 'all') aria-current="page" @endif>
            All tickets
            <span>{{ number_format($totalCount ?? 0) }}</span>
        </a>
        @foreach($statuses as $stat)
            <a href="#" wire:click.prevent="$set('status', '{{ $stat->value }}')" @if($status === $stat->value) aria-current="page" @endif>
                {{ $stat->label() }}
                <span>{{ number_format($statusCounts[$stat->value] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <!-- Search & Filter Controls -->
    <search class="filter-toolbar mb-6" aria-label="Ticket queue search and filters">
        <div class="flex flex-col lg:flex-row gap-4 items-end w-full">
            
            <!-- Wide Search Bar -->
            <div class="relative w-full flex-grow flex flex-col gap-1.5">
                <label for="ticket-search-input" class="text-xs font-semibold text-slate-600">Search</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <x-icons.search class="h-5 w-5 text-slate-400" />
                    </div>
                    <input id="ticket-search-input" type="text" wire:model.live.debounce.300ms="search" placeholder="Search tickets by ID, location, or description..." class="form-input w-full pl-10 pr-3 min-h-[42px]" aria-label="Search tickets by ID, location, or description">
                </div>
            </div>

            <!-- Filter Dropdowns -->
            <div class="flex items-end gap-3 w-full lg:w-auto shrink-0 flex-wrap">
                <div class="flex flex-col gap-1.5">
                    <label for="ticket-filter-category" class="text-xs font-semibold text-slate-600">Category</label>
                    <select id="ticket-filter-category" wire:model.live="filter" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Filter by category">
                        <option value="all">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                        <option value="other">Custom/Other Categories</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="ticket-filter-department" class="text-xs font-semibold text-slate-600">Department</label>
                    <select id="ticket-filter-department" wire:model.live="department" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Filter by assigned department">
                        <option value="all">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                        @endforeach
                        <option value="unassigned">Unassigned</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="ticket-filter-aging" class="text-xs font-semibold text-slate-600">Age</label>
                    <select id="ticket-filter-aging" wire:model.live="aging" class="form-input bg-white text-slate-700 min-h-[42px] w-auto {{ $aging !== 'all' ? 'border-emerald-500 ring-1 ring-emerald-500 font-medium' : '' }}" aria-label="Filter by ticket age">
                        <option value="all">All Ticket Ages</option>
                        <option value="under_24h">Unresolved: &lt; 24 hours</option>
                        <option value="1_to_7_days">Unresolved: 1 to 7 days</option>
                        <option value="7_days_plus">Unresolved: 7+ days</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="ticket-filter-sort" class="text-xs font-semibold text-slate-600">Sort</label>
                    <select id="ticket-filter-sort" wire:model.live="sort" class="form-input bg-white text-slate-700 min-h-[42px] w-auto" aria-label="Sort tickets">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="status">Sort by Status</option>
                    </select>
                </div>

                <!-- Loading Indicator -->
                <div wire:loading class="btn btn-secondary btn-md opacity-75 cursor-wait min-h-[42px] flex items-center">
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
                <h2 class="text-lg font-bold text-slate-900">
                    Ticket queue <span class="record-count ml-2">{{ number_format($tickets->total()) }}</span>
                    @if($aging !== 'all')
                        <span class="inline-flex items-center gap-1 ml-2 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span>Age: {{ $aging === 'under_24h' ? '< 24 hours' : ($aging === '1_to_7_days' ? '1–7 days' : '7+ days') }}</span>
                            <button type="button" wire:click="$set('aging', 'all')" class="hover:text-emerald-900 ml-0.5 font-bold cursor-pointer" title="Clear age filter">&times;</button>
                        </span>
                    @endif
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Service requests matching your current filters</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="ticket-queue system-table w-full text-left border-collapse min-w-[1000px]">
                <caption class="sr-only">Support tickets queue and real-time dispatches</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-40">Ticket ID</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Status</th>
                        <th scope="col" class="px-6 py-4 w-40">Source / Caller</th>
                        <th scope="col" class="px-6 py-4 w-56">Address & Landmark</th>
                        <th scope="col" class="px-6 py-4 w-48">Nature of Complaint</th>
                        <th scope="col" class="px-6 py-4 w-56">Assigned Dept</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $tkt)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <a href="{{ route($isAdmin ? 'admin.tickets.show' : 'cwd.tickets.show', $tkt) }}" class="font-bold text-emerald-700 text-sm hover:underline" aria-label="View ticket #{{ $tkt->ticket_number }}">
                                        {{ $tkt->ticket_number }}
                                    </a>
                                    @if($tkt->parent_ticket_id)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200" title="Child ticket">Child</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $tkt->reported_at->format('M d, Y') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-center">
                                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $tkt->status->badgeClasses() }} text-[11px] font-bold tracking-wide">
                                    {{ $tkt->status->label() }}
                                </span>
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
                                    @if(! $tkt->other_category && $tkt->category?->trashed())
                                        <span class="text-[10px] text-slate-400 font-normal italic">(Archived)</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm font-medium leading-snug {{ $tkt->department ? 'text-slate-800' : 'text-slate-400 italic' }}">
                                    {{ $tkt->department->dept_name ?? 'Unassigned' }}
                                    @if($tkt->department?->trashed())
                                        <span class="text-[10px] text-slate-400 font-normal not-italic ml-1">(Archived)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
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
