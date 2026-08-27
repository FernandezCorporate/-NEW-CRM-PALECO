@extends('admin.base.base')

@section('title', 'System Monitoring & Audit Log')

@section('content')
    @include('admin.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">System Monitoring & Audit Log</h1>
            <p class="text-sm text-slate-500 mt-1">Track all actions performed across every user level — Admin, CWD, Foreman, Field.</p>
        </div>
        
        <a href="#" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Exporting...">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Export Log (CSV)
        </a>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
        <!-- Total Events -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Events (Total)</div>
            <h3 class="text-3xl font-bold text-slate-800">{{ number_format($logs->total()) }}</h3>
        </div>

        <!-- Events Today -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Events Today</div>
            <h3 class="text-3xl font-bold text-slate-800">
                {{ number_format($logs->where('created_at', '>=', today())->count()) }}
            </h3>
        </div>
    </div>

    <!-- Controls Row -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <form action="{{ route('admin.monitoring.index') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full">
            
            <!-- Search -->
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search by actor, action, target..." value="{{ request('search') }}" class="border border-slate-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-full md:w-80">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Searching...">Search</button>
                @if(request()->filled('search'))
                    <a href="{{ route('admin.monitoring.index', request()->except('search')) }}" class="action-link bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Clearing...">Clear</a>
                @endif
            </div>

            <!-- Category Filter -->
            <div class="flex items-center gap-2 ml-auto">
                <select name="category" class="ts-filter-dropdown hidden">
                    <option value="All Categories" {{ request('category') === 'All Categories' || empty(request('category')) ? 'selected' : '' }}>All Categories</option>
                    <option value="login_success" {{ request('category') === 'login_success' ? 'selected' : '' }}>Authentication</option>
                    <option value="Tickets" {{ request('category') === 'Tickets' ? 'selected' : '' }}>Tickets</option>
                    <option value="Users" {{ request('category') === 'Users' ? 'selected' : '' }}>User Mgmt</option>
                    <option value="Teams" {{ request('category') === 'Teams' ? 'selected' : '' }}>Teams</option>
                    <option value="Department" {{ request('category') === 'Department' ? 'selected' : '' }}>Departments</option>
                </select>
                <noscript><button type="submit" class="bg-slate-500 text-white px-4 py-2 rounded">Apply</button></noscript>
            </div>
        </form>
    </div>

    <!-- Data Grid Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Timestamp & Source</th>
                        <th class="px-6 py-4">Actor</th>
                        <th class="px-6 py-4">Module & Event</th>
                        <th class="px-6 py-4">Primary Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            
                            <!-- Timestamp & IP Address -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <!-- Pulled from the newly injected properties on the Service layer -->
                                <div class="font-medium text-slate-800">{{ $log->formatted_date }}</div>
                                <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                                    IP: {{ $log->properties['ip_address'] ?? 'Unknown' }}
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $log->formatted_time }}</div>
                            </td>

                            <!-- Actor Name & Role -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @php
                                        $isAuth = str_starts_with($log->log_name, 'login_');
                                        $actorName = $log->causer ? ($log->causer->first_name . ' ' . $log->causer->last_name) : ($log->properties['username'] ?? 'System');
                                        $actorRole = $log->causer ? $log->causer->role->role_name : ($log->properties['role'] ?? 'Unknown');
                                        $initials = strtoupper(substr($actorName, 0, 2));
                                    @endphp
                                    
                                    <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 border border-slate-200">
                                        {{ $initials }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-800">{{ Str::title($actorName) }}</span>
                                        <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mt-0.5">
                                            {{ Str::headline($actorRole) }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Module & Event Tags -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col items-start gap-1.5">
                                    @if($isAuth)
                                        <span class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Authentication</span>
                                    @elseif($log->log_name === 'Tickets')
                                        <span class="bg-blue-50 border border-blue-200 text-blue-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Tickets</span>
                                    @elseif($log->log_name === 'Users')
                                        <span class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">User Mgmt</span>
                                    @else
                                        <span class="bg-slate-100 border border-slate-200 text-slate-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">{{ $log->log_name }}</span>
                                    @endif
                                    
                                    <span class="text-xs text-slate-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        {{ $log->event }}
                                    </span>
                                </div>
                            </td>

                            <!-- Primary Action Description -->
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-slate-700">
                                    {{ $log->description }}
                                </span>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-800">No activity logs found</h3>
                                <p class="text-xs text-slate-500 mt-1">Adjust your search or filter to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($logs->hasPages())
        <div class="mt-6">
            {{ $logs->onEachSide(0)->links() }}
        </div>
    @endif
@endsection