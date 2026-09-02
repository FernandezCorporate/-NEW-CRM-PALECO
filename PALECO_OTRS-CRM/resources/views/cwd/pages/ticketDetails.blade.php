@extends('cwd.base.base')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('cwd.tickets') }}" class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    {{ $ticket->ticket_number }}
                    @if($ticket->parent_ticket_id)
                        <span class="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded uppercase tracking-wide">Child Ticket</span>
                    @endif
                </h1>
                <p class="text-sm text-gray-500 mt-1">{{ $ticket->subject }}</p>
            </div>
        </div>

        <!-- Dynamic Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <!-- Blue: Source -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                {{ $ticket->complaint_source->label() }}
            </span>
            <!-- Red: Category -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 border border-red-200 text-red-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                {{ $ticket->other_category ? $ticket->other_category_name : ($ticket->category->category_name ?? 'Unspecified') }}
            </span>
            <!-- Green: Department -->
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                {{ $ticket->department->dept_name ?? 'Unassigned' }}
            </span>
        </div>
    </div>

    <!-- Top Block: Info & Routing -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 items-stretch">
        
        <!-- Consumer Issue Details -->
        <div class="xl:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-6 h-full">
            <h2 class="text-lg font-bold text-gray-900 mb-6">Complaint & Issue Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                <!-- Location -->
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Address</span>
                    <div class="flex items-start gap-2 text-gray-800 font-medium">
                        <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        {{ implode(', ', array_filter([$ticket->purok, $ticket->street, $ticket->barangay])) }}
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Landmark</span>
                    <div class="text-sm text-gray-700">{{ $ticket->landmark ?? 'No landmark provided' }}</div>
                </div>

                <!-- Complaint -->
                <div class="md:col-span-2">
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Detailed Description</span>
                    <div class="text-sm text-gray-800 bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed whitespace-pre-wrap">
                        {{ $ticket->complaint_description }}
                    </div>
                </div>

                <!-- Timestamps -->
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Date Logged</span>
                    <div class="flex items-center gap-2 text-gray-800 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ $ticket->reported_at->format('M d, Y h:i A') }}
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Created By</span>
                    <div class="flex items-center gap-2 text-gray-800 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        {{ $ticket->creator->fullName ?? 'System' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients & Escalation Tree -->
        <div class="flex flex-col gap-6 h-full">
            
            <!-- Recipients Block -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 flex-grow">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Assigned Personnel</h2>
                
                <div class="space-y-3">
                    <!-- Foremen List -->
                    @if($ticket->department && $ticket->department->foremen->isNotEmpty())
                        @foreach($ticket->department->foremen as $foreman)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-100 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ $foreman->avatar_initials }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-800 text-sm">{{ $foreman->full_name }}</span>
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wide">Foreman • {{ $ticket->department->dept_name }}</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="p-3 bg-gray-50 border border-gray-100 rounded-lg text-sm text-gray-500 italic text-center">No foremen assigned to this department.</div>
                    @endif

                    <!-- Field Team List -->
                    @if($ticket->team)
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between mb-3 border-b border-gray-200 pb-2">
                                <span class="font-bold text-gray-900 text-sm">{{ $ticket->team->team_name }}</span>
                                <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-bold uppercase tracking-wider">Field Team</span>
                            </div>
                            <div class="space-y-2">
                                @forelse($ticket->team->members as $member)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] shrink-0">
                                            {{ $member->avatar_initials }}
                                        </div>
                                        <span class="text-xs text-gray-700 font-medium">{{ $member->full_name }}</span>
                                    </div>
                                @empty
                                    <div class="text-xs text-gray-400 italic">No members in this team.</div>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-300 rounded-lg text-sm text-gray-500 text-center flex flex-col items-center justify-center h-24">
                            <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Team not yet assigned
                        </div>
                    @endif
                </div>
            </div>

            <!-- Escalation Tree -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 shrink-0">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                    Escalation Tree
                </h2>

                <div class="space-y-4">
                    @if(is_null($ticket->parent_ticket_id))
                        <!-- This ticket is the PARENT -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin Ticket</span>
                            <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                                <div class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</div>
                                <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                            </div>
                        </div>

                        @if($ticket->childTickets->isNotEmpty())
                            <div>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Escalated To (Child Tickets)</span>
                                <div class="space-y-2 pl-4 border-l-2 border-gray-200 ml-2">
                                    @foreach($ticket->childTickets as $child)
                                        <a href="{{ route('cwd.tickets.show', $child) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                            <div class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $child->ticket_number }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $child->department->dept_name ?? 'Unassigned' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    @else
                        <!-- This ticket is a CHILD -->
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Origin Ticket</span>
                            <a href="{{ route('cwd.tickets.show', $ticket->parentTicket) }}" class="block p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-sm transition-all group">
                                <div class="font-bold text-indigo-600 text-sm group-hover:text-indigo-700 transition-colors">{{ $ticket->parentTicket->ticket_number }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $ticket->parentTicket->department->dept_name ?? 'Unassigned' }}</div>
                            </a>
                        </div>
                        
                        <div>
                            <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Escalated Ticket</span>
                            <div class="p-3 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg pl-4 ml-2">
                                <div class="font-bold text-emerald-800 text-sm">{{ $ticket->ticket_number }} (Current)</div>
                                <div class="text-xs text-emerald-600 mt-0.5">{{ $ticket->department->dept_name ?? 'Unassigned' }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- Bottom Section: History Mini-Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- 6.1 Status Log -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-bold text-gray-800 text-sm">Status Timeline</h3>
            </div>
            <div class="p-0 max-h-80 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-sm text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Status Change</th>
                            <th class="px-5 py-3 font-semibold">Updated By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($ticket->statusLog as $log)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('M d, y H:i') }}</td>
                                <td class="px-5 py-3 font-medium text-gray-800">
                                    @if($log->old_status)
                                        <span class="text-gray-400 line-through mr-1">{{ $log->old_status->label() }}</span> &rarr;
                                    @endif
                                    <span class="text-[#008f5d] ml-1">{{ $log->new_status->label() }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $log->updater->fullName ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-gray-400 italic text-sm">No status changes recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6.2 Assignment History -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-bold text-gray-800 text-sm">Team Assignments</h3>
            </div>
            <div class="p-0 max-h-80 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-sm text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Date Assigned</th>
                            <th class="px-5 py-3 font-semibold">Team</th>
                            <th class="px-5 py-3 font-semibold">Assigned By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($ticket->assignments as $assignment)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                                    {{ $assignment->created_at->format('M d, y H:i') }}
                                    @if($assignment->unassigned_at)
                                        <div class="text-[10px] text-red-400">Unassigned: {{ $assignment->unassigned_at->format('M d, y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-800">{{ $assignment->team->team_name ?? 'Unknown Team' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $assignment->assigner->fullName ?? 'Unknown User' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-gray-400 italic text-sm">No assignments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6.3 Escalation History -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-bold text-gray-800 text-sm">Escalation Requests</h3>
            </div>
            <div class="p-0 max-h-80 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-sm text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Date Requested</th>
                            <th class="px-5 py-3 font-semibold">Requested By</th>
                            <th class="px-5 py-3 font-semibold text-center">Status</th>
                            <th class="px-5 py-3 font-semibold">Target Dept</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($ticket->escalations as $escalation)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap">{{ $escalation->created_at->format('M d, y H:i') }}</td>
                                <td class="px-5 py-3 font-medium text-gray-800">{{ $escalation->creator->fullName ?? 'Unknown User' }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                        {{ $escalation->status->value === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($escalation->status->value === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $escalation->status->name }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $escalation->suggestedDepartment->dept_name ?? 'Not Specified' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-gray-400 italic text-sm">No escalation requests recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6.4 Accomplishment History (FIXED) -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-bold text-gray-800 text-sm">Field Accomplishments</h3>
            </div>
            <div class="p-0 max-h-80 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-white sticky top-0 border-b border-gray-100 shadow-sm text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Date Submitted</th>
                            <th class="px-5 py-3 font-semibold">Field Worker</th>
                            <th class="px-5 py-3 font-semibold text-center">Status</th>
                            <th class="px-5 py-3 font-semibold">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($ticket->accomplishments as $acc)
                            <!-- Added cursor-pointer and onclick routing -->
                            <tr class="hover:bg-gray-100 transition-colors cursor-pointer group" 
                                onclick="window.location='{{ route('cwd.tickets.accomplishments.show', ['ticket' => $ticket, 'accomplishment' => $acc]) }}'">
                                
                                <td class="px-5 py-3 text-gray-500 whitespace-nowrap align-top group-hover:text-gray-700">{{ $acc->accomplished_at->format('M d, y H:i') }}</td>
                                <td class="px-5 py-3 font-medium text-gray-800 align-top group-hover:text-indigo-600 transition-colors">{{ $acc->accomplishedBy->fullName ?? 'Unknown User' }}</td>
                                <td class="px-5 py-3 text-center align-top">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                        {{ $acc->status->value === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($acc->status->value === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $acc->status->name }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-600 align-top">
                                    <div class="whitespace-normal min-w-[150px]">
                                        {{ $acc->remarks }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-gray-400 italic text-sm">No accomplishments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection