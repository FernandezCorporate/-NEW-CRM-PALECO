@extends('admin.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Activity Log #' . $activity->id . ' Details')

@section('content')
    @php
        $backRoute = session('monitoring_list_url', route('admin.monitoring.index'));
        $isAuth = str_starts_with($activity->log_name, 'login_');
        $authAction = \App\Enums\NonModelActions::tryFrom($activity->log_name);
    @endphp

    <!-- Breadcrumb / Back Navigation -->
    <div class="mb-6">
        <a href="{{ $backRoute }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors" data-loading-text="Returning...">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to System Monitoring
        </a>
    </div>

    <!-- Header Section -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="font-mono text-xs font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md border border-slate-200">
                        LOG #{{ $activity->id }}
                    </span>

                    <!-- Module / Category Badge -->
                    @if($authAction)
                        <span class="border text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide {{ $authAction->badgeClasses() }}">{{ $authAction->label() }}</span>
                    @elseif($isAuth)
                        <span class="bg-indigo-50 border border-indigo-200 text-indigo-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Authentication</span>
                    @elseif($activity->log_name === 'Tickets')
                        <span class="bg-blue-50 border border-blue-200 text-blue-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Tickets</span>
                    @elseif($activity->log_name === 'Users')
                        <span class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Users</span>
                    @elseif($activity->log_name === 'Teams')
                        <span class="bg-purple-50 border border-purple-200 text-purple-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Teams</span>
                    @elseif($activity->log_name === 'Department')
                        <span class="bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Departments</span>
                    @elseif($activity->log_name === 'TicketCategory')
                        <span class="bg-teal-50 border border-teal-200 text-teal-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Categories</span>
                    @else
                        <span class="bg-slate-100 border border-slate-200 text-slate-700 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">{{ $activity->log_name }}</span>
                    @endif

                    <!-- Event Verb Badge -->
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="uppercase tracking-wider font-bold text-[10px]">{{ $activity->event ?? 'logged' }}</span>
                    </span>

                    <span class="text-xs text-slate-400">
                        {{ $client['relative_time'] }} ({{ $client['formatted_datetime'] }})
                    </span>
                </div>

                <h1 class="text-xl font-bold text-slate-900 mt-2.5 leading-snug">
                    {{ $activity->description }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Top Summary Cards (Actor, Subject, Client) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        
        <!-- 1. Actor Card -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div class="p-5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center justify-between">
                    <span>Actor (Causer)</span>
                    <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">{{ $actor['is_causer_model'] ? 'Registered User' : 'System / Identity' }}</span>
                </div>
                
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-sm shrink-0">
                        {{ $actor['initials'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-bold text-slate-800 truncate">{{ $actor['name'] }}</h3>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mt-0.5">{{ $actor['role'] }}</p>
                        @if($actor['username'])
                            <p class="text-xs text-slate-400 mt-1 font-mono">{{ '@' . $actor['username'] }}</p>
                        @endif

                    </div>
                </div>

                @if($actor['email'] || $actor['contact'])
                    <div class="mt-4 pt-3 border-t border-slate-100 space-y-1 text-xs text-slate-600">
                        @if($actor['email'])
                            <div class="flex items-center gap-1.5 truncate">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="truncate">{{ $actor['email'] }}</span>
                            </div>
                        @endif
                        @if($actor['contact'])
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>{{ $actor['contact'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if($actor['url'])
                <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-100 text-xs">
                    <a href="{{ $actor['url'] }}" class="text-emerald-700 hover:text-emerald-800 font-medium inline-flex items-center gap-1">
                        View User Profile
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @endif
        </div>

        <!-- 2. Target / Subject Card -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div class="p-5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center justify-between">
                    <span>Target Subject</span>
                    @if($subject)
                        <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">{{ $subject['type'] }}</span>
                    @else
                        <span class="text-[10px] bg-slate-100 text-slate-400 px-2 py-0.5 rounded">N/A</span>
                    @endif
                </div>

                @if($subject)
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-800">{{ $subject['title'] }}</h3>
                            @if($subject['is_deleted'])
                                <span class="bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Archived</span>
                            @elseif(!$subject['exists'])
                                <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Deleted</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500">{{ $subject['type'] }} Entity</p>
                        @if($subject['id'])
                            <p class="text-[11px] text-slate-400 font-mono mt-1 break-all">ID: {{ $subject['id'] }}</p>
                        @endif
                    </div>
                @else
                    <div class="py-2 text-center text-slate-400">
                        <svg class="w-8 h-8 mx-auto text-slate-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-xs font-medium text-slate-500">No Target Subject</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Direct system or authentication event</p>
                    </div>
                @endif
            </div>

            @if($subject && $subject['url'] && !$subject['is_deleted'])
                <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-100 text-xs">
                    <a href="{{ $subject['url'] }}" class="text-emerald-700 hover:text-emerald-800 font-medium inline-flex items-center gap-1">
                        View {{ $subject['type'] }} Details
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @endif
        </div>

        <!-- 3. Client & Origin Card -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div class="p-5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center justify-between">
                    <span>Client & Network Origin</span>
                    <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">Forensic</span>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="text-xs text-slate-400 font-medium">IP Address</div>
                        <div class="text-sm font-semibold text-slate-800 font-mono mt-0.5 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            <span>{{ $client['ip_address'] }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs text-slate-400 font-medium">Client Environment</div>
                        <div class="text-xs font-semibold text-slate-700 mt-0.5">{{ $client['client_hint'] }}</div>
                    </div>

                    @if($client['user_agent'])
                        <div class="pt-2 border-t border-slate-100">
                            <div class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">User-Agent Header</div>
                            <p class="text-[11px] font-mono text-slate-500 break-all line-clamp-2 mt-0.5" title="{{ $client['user_agent'] }}">
                                {{ $client['user_agent'] }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="px-5 py-2.5 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-400 font-mono">
                Timestamp: {{ $client['formatted_datetime'] }}
            </div>
        </div>

    </div>

    <!-- Main Section: Changes & Diff Inspector -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    @if($diff['has_model_diff'])
                        Attribute Mutation & State Diff
                    @else
                        Event Properties & Identity Metadata
                    @endif
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    @if($diff['type'] === 'updated')
                        Comparing state before and after this event was executed.
                    @elseif($diff['type'] === 'created')
                        Initial attributes set upon creation of this record.
                    @elseif($diff['type'] === 'deleted')
                        Attribute state captured at time of deletion or archive.
                    @else
                        Custom properties and context recorded for this event.
                    @endif
                </p>
            </div>

            <span class="text-xs font-semibold bg-white border border-slate-200 text-slate-700 px-3 py-1 rounded-full shadow-2xs">
                {{ $diff['count'] }} {{ Str::plural('field', $diff['count']) }} recorded
            </span>
        </div>

        @if($diff['count'] > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-3.5 w-1/4">Field / Attribute</th>
                            @if($diff['type'] === 'updated' || $diff['has_model_diff'])
                                <th class="px-6 py-3.5 w-3/8">Previous Value (Old)</th>
                                <th class="px-6 py-3.5 w-3/8">New Value (Updated)</th>
                            @else
                                <th class="px-6 py-3.5 w-3/4">Recorded Value</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach($diff['rows'] as $row)
                            <tr class="hover:bg-slate-50/75 transition-colors">
                                <!-- Attribute Name -->
                                <td class="px-6 py-4 font-medium text-slate-800 align-top">
                                    <div class="font-semibold text-slate-900">{{ $row['label'] }}</div>
                                    <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $row['key'] }}</div>
                                </td>

                                @if($diff['type'] === 'updated' || $diff['has_model_diff'])
                                    <!-- Old Value -->
                                    <td class="px-6 py-4 align-top">
                                        @if($row['old_display'] !== null)
                                            <div class="inline-block px-2.5 py-1 rounded-md text-xs font-mono {{ $row['is_changed'] ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-50 text-slate-600' }}">
                                                {{ $row['old_display'] }}
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400 font-mono italic">None</span>
                                        @endif
                                    </td>

                                    <!-- New Value -->
                                    <td class="px-6 py-4 align-top">
                                        @if($row['new_display'] !== null)
                                            <div class="inline-block px-2.5 py-1 rounded-md text-xs font-mono {{ $row['is_changed'] ? 'bg-emerald-50 text-emerald-800 border border-emerald-200 font-semibold' : 'bg-slate-50 text-slate-700' }}">
                                                {{ $row['new_display'] }}
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400 font-mono italic">None</span>
                                        @endif
                                    </td>
                                @else
                                    <!-- Single Value (Metadata/Created/Deleted) -->
                                    <td class="px-6 py-4 align-top">
                                        <div class="inline-block px-2.5 py-1 rounded-md text-xs font-mono bg-slate-50 text-slate-800 border border-slate-200">
                                            {{ $row['new_display'] }}
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center text-slate-400">
                <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-semibold text-slate-700">No Attribute Mutations Recorded</p>
                <p class="text-xs text-slate-500 mt-0.5">This event logged an action description without registering field changes.</p>
            </div>
        @endif
    </div>

    <!-- Forensic Raw JSON Payload Accordion -->
    <details class="group bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mb-8">
        <summary class="px-6 py-4 cursor-pointer font-semibold text-sm text-slate-700 bg-slate-50 hover:bg-slate-100/75 transition-colors flex items-center justify-between select-none">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                Raw Audit Forensics (JSON Payloads)
            </span>
            <svg class="w-4 h-4 text-slate-400 transform group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>

        <div class="p-6 bg-slate-900 text-slate-200 text-xs font-mono space-y-6 overflow-x-auto">
            <div>
                <div class="text-emerald-400 font-bold mb-1.5 uppercase text-[10px] tracking-wider">// 1. Spatie v5 attribute_changes</div>
                <pre class="bg-slate-950 p-4 rounded-lg border border-slate-800 text-slate-300 overflow-x-auto">{{ $rawAttributeChanges }}</pre>
            </div>

            <div>
                <div class="text-emerald-400 font-bold mb-1.5 uppercase text-[10px] tracking-wider">// 2. Spatie properties (Metadata & Context)</div>
                <pre class="bg-slate-950 p-4 rounded-lg border border-slate-800 text-slate-300 overflow-x-auto">{{ $rawProperties }}</pre>
            </div>

            <div>
                <div class="text-slate-400 font-bold mb-1.5 uppercase text-[10px] tracking-wider">// 3. Complete Activity Record</div>
                <pre class="bg-slate-950 p-4 rounded-lg border border-slate-800 text-slate-300 overflow-x-auto">{{ $rawPayload }}</pre>
            </div>
        </div>
    </details>
@endsection
