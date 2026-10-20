@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($team) ? 'Edit Team' : 'Create New Team')

@section('content')
@php
    $source = old('source', request('source'));
    $deptId = old('dept', request('dept', $team->department_id ?? null));
    $userId = old('user', request('user'));
    $cancelRoute = match($source) {
        'details' => isset($team) 
            ? route('admin.teams.show', array_filter(['team' => $team, 'source' => $userId ? 'user' : ($deptId ? 'department' : null), 'user' => $userId, 'dept' => $deptId])) 
            : route('admin.teams'),
        'user' => $userId ? route('admin.users.show', ['user' => $userId]) : (isset($team) ? route('admin.teams.show', ['team' => $team]) : session('team_list_url', route('admin.teams'))),
        'department' => $deptId ? route('admin.departments.show', ['dept' => $deptId]) : session('team_list_url', route('admin.teams')),
        default => session('team_list_url', route('admin.teams')),
    };
    $backLabel = match($source) {
        'details' => 'Back to Team Details',
        'department' => 'Back to Department',
        'user' => 'Back to User Profile',
        default => 'Back to Teams',
    };
@endphp

<div class="max-w-4xl mx-auto py-4 space-y-6">
    <!-- Back Navigation -->
    <div>
        <x-contextual-back :url="$cancelRoute" :label="$backLabel" variant="link" />
    </div>

    <header class="mb-2">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($team) ? 'Edit Team' : 'New Team' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($team) ? 'Update the details and roster of this existing team.' : 'Fill out the information below to register a new field team and assign members.' }}</p>
    </header>

    <x-alert />

    <article class="card">
        <form action="{{ isset($team) ? route('admin.teams.update', $team) : route('admin.teams.store') }}" method="POST">
            @csrf
            @isset($team)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $team->updated_at }}">
            @endisset
            @if(request()->filled('source') || old('source'))
                <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
            @endif
            @if(request()->filled('dept') || old('dept'))
                <input type="hidden" name="dept" value="{{ old('dept', request('dept')) }}">
            @endif
            @if(request()->filled('user') || old('user'))
                <input type="hidden" name="user" value="{{ old('user', request('user')) }}">
            @endif

            <!-- Card Content Wrapper -->
            <div class="p-6 md:p-8 space-y-8">
                <!-- Section 1: Team Identity & Department -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Team Identity & Department</legend>
                    <div class="border-b border-slate-200 pb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <x-icons.team class="w-4 h-4 text-emerald-600" />
                            <span>Team Identity & Department</span>
                        </h2>
                        <span class="text-xs text-slate-500"><span class="text-rose-500 font-bold">*</span> Required field</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-form.label for="team_name" :required="true">Team Name</x-form.label>
                            <x-form.input name="team_name" id="team_name" required :value="$team->team_name ?? null" placeholder="e.g., Line Team Alpha" />
                            <x-form.error name="team_name" />
                        </div>

                        <div>
                            <x-form.label for="department_id" :required="true">Department</x-form.label>
                            <select name="department_id" id="department_id" class="tom-select-sync w-full border rounded-lg text-sm transition-colors focus:outline-none focus:ring-1 @error('department_id') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 @enderror" data-autosubmit="false" autocomplete="off" required>
                                <option value="" disabled selected>Select a department...</option>
                                @foreach ($depts as $id => $dept)
                                    <option value="{{ $id }}" {{ old('department_id', $team->department_id ?? '') == $id ? 'selected' : '' }}>
                                        {{ $dept }}
                                    </option>
                                @endforeach
                            </select>
                            <x-form.error name="department_id" />
                        </div>

                        <div class="md:col-span-2">
                            <x-form.label for="team_desc">Team Description</x-form.label>
                            <x-form.textarea name="team_desc" id="team_desc" rows="3" :value="$team->team_desc ?? null" placeholder="Briefly describe the operational responsibilities of this team..." />
                            <x-form.error name="team_desc" />
                        </div>
                    </div>
                </fieldset>

                <!-- Section 2: Operational Shift Hours -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Operational Shift Hours</legend>
                    <div class="border-b border-slate-200 pb-2">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Operational Shift Hours</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-form.label for="shift_start" :required="true">Shift Start</x-form.label>
                            <x-form.input type="time" name="shift_start" id="shift_start" required :value="isset($team) ? $team->shift_start->format('H:i') : null" />
                            <x-form.error name="shift_start" />
                        </div>

                        <div>
                            <x-form.label for="shift_end" :required="true">Shift End</x-form.label>
                            <x-form.input type="time" name="shift_end" id="shift_end" required :value="isset($team) ? $team->shift_end->format('H:i') : null" />
                            <x-form.error name="shift_end" />
                        </div>
                    </div>
                </fieldset>

                <!-- Section 3: Inline Team Members -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Assigned Crew Roster</legend>
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <span>Assigned Crew Roster</span>
                        </h2>
                        <button type="button" id="add-member-btn" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-lg transition-colors">
                            <x-icons.plus class="w-3.5 h-3.5" />
                            <span>Add Member</span>
                        </button>
                    </div>

                    @php
                        $currentMembers = old('members', isset($team) ? $team->members->map(function($m) {
                            return ['user_id' => $m->id, 'team_role_id' => $m->pivot->team_role_id];
                        })->toArray() : []);
                    @endphp

                    <!-- Container for dynamic rows -->
                    <div id="team-members-container" class="space-y-3">
                        @foreach($currentMembers as $index => $member)
                            <div class="member-row flex flex-col md:flex-row gap-3 items-start md:items-end bg-slate-50/80 p-3.5 border border-slate-200 rounded-xl shadow-2xs">
                                <div class="w-full md:flex-1">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Personnel</label>
                                    <select name="members[{{ $index }}][user_id]" class="tom-select-dynamic" required autocomplete="off" placeholder="Select personnel...">
                                        <option value=""></option>
                                        @foreach ($personnel as $person)
                                            <option value="{{ $person->id }}" {{ $member['user_id'] == $person->id ? 'selected' : '' }}>{{ $person->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="w-full md:w-56 shrink-0">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Team Role</label>
                                    <select name="members[{{ $index }}][team_role_id]" required 
                                        class="tom-select-sync" data-autosubmit="false" autocomplete="off" placeholder="Select Role...">
                                        <option value=""></option>
                                        @foreach ($memberRoles as $role)
                                            <option value="{{ $role->id }}" {{ isset($member['team_role_id']) && $member['team_role_id'] == $role->id ? 'selected' : '' }}>
                                                {{ Str::headline($role->role_name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="button" class="remove-member-btn shrink-0 w-full md:w-auto inline-flex items-center justify-center p-2.5 h-[38px] text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Remove member">
                                    <x-icons.trash class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- Empty State (Shown when 0 rows exist) -->
                    <div id="no-members-state" class="text-center py-8 bg-slate-50 border border-dashed border-slate-300 rounded-xl hidden">
                        <p class="text-xs text-slate-500">No members added yet. Click <strong>"Add Member"</strong> to begin assigning personnel.</p>
                    </div>
                </fieldset>

                <!-- HTML Template for dynamically appended rows -->
                <template id="member-row-template">
                    <div class="member-row flex flex-col md:flex-row gap-3 items-start md:items-end bg-slate-50/80 p-3.5 border border-slate-200 rounded-xl shadow-2xs">
                        <div class="w-full md:flex-1">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Personnel</label>
                            <select name="members[__INDEX__][user_id]" class="tom-select-dynamic" required autocomplete="off" placeholder="Select personnel...">
                                <option value=""></option>
                                @foreach ($personnel as $person)
                                    <option value="{{ $person->id }}">{{ $person->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full md:w-56 shrink-0">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Team Role</label>
                            <select name="members[__INDEX__][team_role_id]" required 
                                class="tom-select-sync" data-autosubmit="false" autocomplete="off" placeholder="Select Role...">
                                <option value=""></option>
                                @foreach ($memberRoles as $role)
                                    <option value="{{ $role->id }}">{{ Str::headline($role->role_name) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" class="remove-member-btn shrink-0 w-full md:w-auto inline-flex items-center justify-center p-2.5 h-[38px] text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Remove member">
                            <x-icons.trash class="w-4 h-4" />
                        </button>
                    </div>
                </template>
            </div>

            <div class="card-footer px-6 md:px-8 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    <x-icons.check-circle class="w-4 h-4" />
                    <span>{{ isset($team) ? 'Update Team' : 'Save Team' }}</span>
                </x-btn>
            </div>
        </form>
    </article>
</div>
@endsection
