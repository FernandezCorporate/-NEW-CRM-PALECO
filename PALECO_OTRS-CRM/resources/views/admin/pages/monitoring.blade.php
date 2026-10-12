@extends('admin.base.base')

@section('title', 'System Monitoring & Audit Log')

@section('content')
    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">System Monitoring & Audit Log</h1>
            <p class="text-sm text-slate-500 mt-1">Track all actions performed across every user level — Admin, CWD, Supervisor, Field Personnel.</p>
        </div>
        
        <div class="text-right">
            <button type="button" disabled class="unavailable-action" aria-describedby="export-note">Export CSV</button>
            <p id="export-note" class="mt-1 text-xs text-slate-500">Export is not available yet.</p>
        </div>
    </header>

    <p class="mb-5 text-sm text-slate-600">
        <strong class="text-slate-800">{{ number_format($logs->total()) }}</strong> matching events
        <span class="mx-2 text-slate-300" aria-hidden="true">/</span>
        Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }}
    </p>

    <!-- Controls Row -->
    <search class="filter-toolbar mb-6" aria-label="Audit log search and filters">
        <form action="{{ route('admin.monitoring.index') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full">
            
            <!-- Search -->
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search by actor, action, target..." value="{{ request('search') }}" class="form-input w-full md:w-80" aria-label="Search audit logs by actor, action, or target">
                <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                @if(request()->filled('search') || (request()->filled('category') && request('category') !== 'all') || (request()->filled('event_type') && request('event_type') !== 'all'))
                    <x-btn href="{{ route('admin.monitoring.index') }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">Clear</x-btn>
                @endif
            </div>

            <!-- Category & Event Type Filters (Server-Provided) -->
            <div class="flex items-center gap-3 ml-auto flex-wrap">
                <!-- Event Type Filter (CRUD vs Non-CRUD) -->
                <select name="event_type" class="ts-filter-dropdown" aria-label="Filter by event type">
                    @foreach($eventTypes as $value => $label)
                        <option value="{{ $value }}" {{ request('event_type', 'all') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <!-- Category Filter -->
                <select name="category" class="ts-filter-dropdown" aria-label="Filter by category">
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" {{ (request('category', 'all') === (string) $value || (in_array(request('category'), ['auth', 'login_success', 'login', 'authentication']) && $value === 'auth')) ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><x-btn type="submit" variant="secondary" size="md">Apply</x-btn></noscript>
            </div>
        </form>
    </search>

    <!-- Data Grid Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse">
                <caption class="sr-only">System audit activity logs and security events</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4">Timestamp & Source</th>
                        <th scope="col" class="px-6 py-4">Actor</th>
                        <th scope="col" class="px-6 py-4">Module & Event</th>
                        <th scope="col" class="px-6 py-4">Primary Action</th>
                        <th scope="col" class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            
                            <!-- Timestamp & IP Address -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-slate-800">{{ $log->formatted_date }}</div>
                                <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                                    <span class="font-mono text-[11px]">IP: {{ $log->properties['ip_address'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $log->formatted_time }}</div>
                            </td>

                            <!-- Actor Name & Role -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @php
                                        $actorName = $log->causer ? ($log->causer->first_name . ' ' . $log->causer->last_name) : ($log->properties['full_name'] ?? $log->properties['username'] ?? 'System');
                                        $actorRole = $log->causer ? $log->causer->role->role_name : ($log->properties['role'] ?? 'Unknown');
                                        $initials = strtoupper(substr(trim($actorName), 0, 2)) ?: 'SY';
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
                                    <span class="border text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide {{ $log->module_badge_class }}">
                                        {{ $log->module_label }}
                                    </span>
                                    
                                    <span class="text-xs text-slate-400 flex items-center gap-1">
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

                            <!-- View Details Action Button -->
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('admin.monitoring.show', $log) }}" class="action-link inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:text-emerald-700 hover:border-emerald-200 shadow-xs transition-colors" data-loading-text="Opening..." aria-label="View details for event #{{ $log->id }}">
                                    <x-icons.eye class="w-3.5 h-3.5" />
                                    <span>Details</span>
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.monitoring class="w-6 h-6 text-slate-400" />
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
