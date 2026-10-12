@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($team) ? 'Edit Team' : 'Create New Team')

@section('content')
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($team) ? 'Edit Team' : 'New Team' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($team) ? 'Update the details and roster of this existing team.' : 'Fill out the information below to register a new field team and assign members.' }}</p>
    </header>

    <x-alert />

    <article class="card max-w-4xl overflow-hidden">
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

            <!-- Section 1: Team Details -->
            <fieldset class="p-6 md:p-8 space-y-6">
                <legend class="text-base font-bold text-slate-800 border-b border-slate-200 pb-2 mb-4 w-full">Team Details</legend>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-form.label for="team_name" :required="true">Team Name</x-form.label>
                        <x-form.input name="team_name" id="team_name" required :value="$team->team_name ?? null" placeholder="e.g., Line Team Alpha" />
                        <x-form.error name="team_name" />
                    </div>

                    <div class="md:col-span-2">
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

                    <div class="md:col-span-2">
                        <x-form.label for="team_desc">Team Description</x-form.label>
                        <x-form.textarea name="team_desc" id="team_desc" rows="3" :value="$team->team_desc ?? null" placeholder="Briefly describe the responsibilities of this team..." />
                        <x-form.error name="team_desc" />
                    </div>
                </div>
            </fieldset>

            <!-- Section 2: Inline Team Members -->
            <fieldset class="px-6 md:px-8 pb-8">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2 mb-4">
                    <legend class="text-base font-bold text-slate-800">Team Members</legend>
                    <button type="button" id="add-member-btn" class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-md transition-colors">
                        <x-icons.plus class="w-4 h-4" />
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
                        <div class="member-row flex flex-col md:flex-row gap-3 items-start md:items-center bg-white p-3 border border-slate-200 rounded-lg shadow-xs">
                            <div class="w-full md:flex-1">
                                <select name="members[{{ $index }}][user_id]" class="tom-select-dynamic" required autocomplete="off" placeholder="Select personnel...">
                                    <option value=""></option>
                                    @foreach ($personnel as $person)
                                        <option value="{{ $person->id }}" {{ $member['user_id'] == $person->id ? 'selected' : '' }}>{{ $person->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="w-full md:w-48 shrink-0">
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

                            <button type="button" class="remove-member-btn shrink-0 w-full md:w-auto inline-flex justify-center p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Remove member">
                                <x-icons.trash class="w-5 h-5" />
                            </button>
                        </div>
                    @endforeach
                </div>
                
                <!-- Empty State (Shown when 0 rows exist) -->
                <div id="no-members-state" class="text-center py-8 bg-slate-50 border border-dashed border-slate-200 rounded-lg hidden">
                    <p class="text-sm text-slate-500">No members added yet. Click "Add Member" to begin assigning personnel.</p>
                </div>
            </fieldset>

            <!-- HTML Template for dynamically appended rows -->
            <template id="member-row-template">
                <div class="member-row flex flex-col md:flex-row gap-3 items-start md:items-center bg-white p-3 border border-slate-200 rounded-lg shadow-xs">
                    <div class="w-full md:flex-1">
                        <select name="members[__INDEX__][user_id]" class="tom-select-dynamic" required autocomplete="off" placeholder="Select personnel...">
                            <option value=""></option>
                            @foreach ($personnel as $person)
                                <option value="{{ $person->id }}">{{ $person->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full md:w-48 shrink-0">
                        <select name="members[__INDEX__][team_role_id]" required 
                            class="tom-select-sync" data-autosubmit="false" autocomplete="off" placeholder="Select Role...">
                            <option value=""></option>
                            @foreach ($memberRoles as $role)
                                <option value="{{ $role->id }}">{{ Str::headline($role->role_name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" class="remove-member-btn shrink-0 w-full md:w-auto inline-flex justify-center p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Remove member">
                        <x-icons.trash class="w-5 h-5" />
                    </button>
                </div>
            </template>

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
            @endphp

            <div class="card-footer flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    {{ isset($team) ? 'Update Team' : 'Save Team' }}
                </x-btn>
            </div>
        </form>
    </article>
@endsection
