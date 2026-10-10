@extends('cwd.base.base')

@section('title', 'Consumer Profile')

@section('content')

    @include('cwd.prompts.alert')

    <!-- Header & Back Button -->
    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('cwd.consumers') }}" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-[#008f5d] focus:ring-offset-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Consumer Profile</h1>
            <p class="text-sm text-gray-500 mt-1">Account and service history</p>
        </div>
    </div>

    <!-- Main Profile Card (Figma Inspired) -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 lg:p-8 mb-8">
        
        <!-- Top Row: Avatar, Name, Badge -->
        <div class="flex justify-between items-start mb-8">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-[#008f5d] flex items-center justify-center text-white text-2xl font-bold shrink-0 shadow-sm">
                    {{ strtoupper(substr($consumer->name, 0, 1)) }}
                </div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $consumer->name }}</h2>
            </div>
            
            @if($consumer->status === 'A')
                <span class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wide">Active</span>
            @else
                <span class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 tracking-wide">Suspended</span>
            @endif
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Left Column: Identifiers -->
            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Meter Number</p>
                        <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $consumer->meter_serial ?? 'N/A' }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Account Identifiers</p>
                        <p class="text-sm font-mono font-medium text-gray-900 mt-0.5">{{ $consumer->acct_code }} <span class="text-gray-300 mx-1">|</span> {{ $consumer->acct_no }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Last Known Contact (from tickets)</p>
                        <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $lastKnownContact ?? 'No contact history' }}</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Location & Summary -->
            <div class="space-y-6 flex flex-col">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-gray-50 rounded-lg text-gray-500 border border-gray-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Service Address</p>
                        <p class="text-sm font-medium text-gray-900 mt-0.5 leading-relaxed">{{ $consumer->address }}</p>
                    </div>
                </div>

                <!-- Green Open Tickets Box (Figma Match) -->
                <div class="mt-auto bg-emerald-50 rounded-lg p-5 border border-emerald-100 flex flex-col justify-center">
                    <p class="text-xs text-emerald-700 font-bold tracking-wide uppercase mb-1">Open Tickets</p>
                    <p class="text-4xl font-bold text-emerald-900">{{ $consumer->open_tickets_count }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Intake Source Metrics -->
    <div class="mb-8">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Complaint Sources</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm text-center">
                <p class="text-xs text-gray-500 font-medium mb-1">Phone Calls</p>
                <p class="text-2xl font-bold text-gray-900">{{ $sourceMetrics['phone_call'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm text-center">
                <p class="text-xs text-gray-500 font-medium mb-1">Walk-in</p>
                <p class="text-2xl font-bold text-gray-900">{{ $sourceMetrics['walk_in'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm text-center">
                <p class="text-xs text-gray-500 font-medium mb-1">Facebook / Online</p>
                <p class="text-2xl font-bold text-gray-900">{{ $sourceMetrics['online_platforms'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm text-center">
                <p class="text-xs text-gray-500 font-medium mb-1">SMS</p>
                <p class="text-2xl font-bold text-gray-900">{{ $sourceMetrics['sms_message'] ?? 0 }}</p>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm text-center">
                <p class="text-xs text-gray-500 font-medium mb-1">Email</p>
                <p class="text-2xl font-bold text-gray-900">{{ $sourceMetrics['email'] ?? 0 }}</p>
            </div>

        </div>
    </div>

    <!-- Historical Tickets Table -->
    <div>
        <div class="flex justify-between items-end mb-4">
            <h3 class="text-lg font-bold text-gray-900">Ticket History</h3>
        </div>
        
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Ticket #</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Category</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Department</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Reported Date</th>
                            <th scope="col" class="px-6 py-4 font-bold tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-4 text-right font-bold tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($historicalTickets as $ticket)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-mono font-medium text-gray-900 whitespace-nowrap">
                                    {{ $ticket->ticket_number }}
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ $ticket->other_category ? ($ticket->other_category_name ?? 'Custom Category') : ($ticket->category->category_name ?? 'Uncategorized') }}
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $ticket->department->dept_name ?? 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($ticket->reported_at)->format('M d, Y h:i A') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <!-- Simplified status styling mapping -->
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase border
                                        @if(in_array($ticket->status->value, ['resolved', 'closed'])) bg-gray-100 text-gray-700 border-gray-200
                                        @elseif(in_array($ticket->status->value, ['open'])) bg-amber-50 text-amber-700 border-amber-200
                                        @else bg-blue-50 text-blue-700 border-blue-200
                                        @endif
                                    ">
                                        {{ $ticket->status->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('cwd.tickets.show', $ticket->id) }}" class="text-[#008f5d] hover:text-[#007049] font-medium text-sm">View details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 border border-gray-200 mb-3">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900">No ticket history</p>
                                    <p class="text-xs text-gray-500 mt-1">This consumer hasn't filed any complaints yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Table Pagination -->
        @if($historicalTickets->hasPages())
            <div class="mt-4">
                {{ $historicalTickets->links() }}
            </div>
        @endif
    </div>

@endsection