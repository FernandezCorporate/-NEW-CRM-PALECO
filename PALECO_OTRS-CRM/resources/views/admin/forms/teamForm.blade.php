@extends('admin.base.base')

@section('title', 'Create New Team')

@section('content')
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">New Team</h1>
        <p class="text-sm text-slate-500 mt-1">Fill out the information below to register a new field team and assign members.</p>
    </div>

    @include('admin.prompts.alert')

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-4xl">
        <form action="{{ route('admin.teams.store') }}" method="POST">
            @csrf
            
            <!-- Section 1: Team Details -->
            <div class="p-6 md:p-8 space-y-6">
                <h2 class="text-base font-bold text-slate-800 border-b border-slate-200 pb-2 mb-4">Team Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Team Name -->
                    <div class="md:col-span-2">
                        <label for="team_name" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Team Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="team_name" name="team_name" value="{{ old('team_name') }}" required placeholder="e.g., Line Team Alpha"
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-1 
                            @error('team_name') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 @enderror">
                        @error('team_name') <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Department -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Department <span class="text-rose-500">*</span></label>
                        <select name="department_id" class="tom-select-sync hidden" data-autosubmit="false" autocomplete="off" required>
                            <option value="" disabled selected>Select a department...</option>
                            @foreach ($depts as $id => $dept)
                                <option value="{{ $id }}" {{ old('department_id') == $id ? 'selected' : '' }}>
                                    {{ $dept }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id') <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Shift Start -->
                    <div>
                        <label for="shift_start" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Shift Start <span class="text-rose-500">*</span>
                        </label>
                        <input type="time" id="shift_start" name="shift_start" value="{{ old('shift_start') }}" required 
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-1 
                            @error('shift_start') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 @enderror">
                        @error('shift_start') <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Shift End -->
                    <div>
                        <label for="shift_end" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Shift End <span class="text-rose-500">*</span>
                        </label>
                        <input type="time" id="shift_end" name="shift_end" value="{{ old('shift_end') }}" required 
                            class="w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-1 
                            @error('shift_end') border-rose-300 focus:border-rose-500 focus:ring-rose-500 @else border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 @enderror">
                        @error('shift_end') <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="team_desc" class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Team Description
                        </label>
                        <textarea id="team_desc" name="team_desc" rows="3" placeholder="Briefly describe the responsibilities of this team..."
                            class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm transition-colors focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">{{ old('team_desc') }}</textarea>
                        @error('team_desc') <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Section 2: Inline Team Members -->
            <div class="px-6 md:px-8 pb-8">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2 mb-4">
                    <h2 class="text-base font-bold text-slate-800">Team Members</h2>
                    <button type="button" id="add-member-btn" class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Member
                    </button>
                </div>

                <!-- Container for dynamic rows -->
                <div id="team-members-container" class="space-y-3">
                    <!-- Dynamic rows will be injected here via JS -->
                </div>
                
                <!-- Empty State (Shown when 0 rows exist) -->
                <div id="no-members-state" class="text-center py-8 bg-slate-50 border border-dashed border-slate-200 rounded-lg">
                    <p class="text-sm text-slate-500">No members added yet. Click "Add Member" to begin assigning personnel.</p>
                </div>
            </div>

            <!-- HTML Template for dynamic rows (Hidden from view) -->
            <template id="member-row-template">
                <div class="member-row flex flex-col md:flex-row gap-3 items-start md:items-center bg-white p-3 border border-slate-200 rounded-lg shadow-sm">
                    
                    <!-- User Selection (Tom Select) -->
                    <div class="w-full md:flex-1">
                        <select name="members[__INDEX__][user_id]" class="tom-select-dynamic" required>
                            <option value="" disabled selected>Select personnel...</option>
                            @foreach ($personnel as $person)
                                <option value="{{ $person->id }}">{{ $person->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Role Selection -->
                    <div class="w-full md:w-48 shrink-0">
                        <select name="members[__INDEX__][team_role]" required class="w-full px-3 py-2.5 border border-slate-200 rounded-lg text-sm bg-slate-50 hover:bg-white focus:bg-white transition-colors focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="" disabled selected>Select Role...</option>
                            @foreach ($memberRoles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Remove Button -->
                    <button type="button" class="remove-member-btn shrink-0 w-full md:w-auto inline-flex justify-center p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Remove member">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </template>

            <!-- Form Actions -->
            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 rounded-b-xl flex items-center justify-end gap-3">
                <a href="{{ route('admin.teams') }}" class="action-link px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-200 rounded-lg transition-colors" data-loading-text="Canceling...">
                    Cancel
                </a>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Saving...">
                    Save Team
                </button>
            </div>
            
        </form>
    </div>
@endsection