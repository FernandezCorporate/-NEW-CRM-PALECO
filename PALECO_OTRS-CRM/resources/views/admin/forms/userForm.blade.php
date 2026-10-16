@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($user) ? 'Edit User Account' : 'Create New User Account')

@section('content')
@php
    $source = old('source', request('source'));
    $deptId = old('dept', request('dept', $user->department_id ?? null));
    $teamId = old('team', request('team'));
    $cancelRoute = match($source) {
        'details' => isset($user) 
            ? route('admin.users.show', array_filter(['user' => $user, 'source' => $teamId ? 'team' : ($deptId ? 'department' : null), 'team' => $teamId, 'dept' => $deptId])) 
            : route('admin.users'),
        'team' => $teamId ? route('admin.teams.show', ['team' => $teamId]) : (isset($user) ? route('admin.users.show', ['user' => $user]) : session('user_list_url', route('admin.users'))),
        'department' => $deptId ? route('admin.departments.show', ['dept' => $deptId]) : session('user_list_url', route('admin.users')),
        default => session('user_list_url', route('admin.users')),
    };
    $backLabel = match($source) {
        'details' => 'Back to User Profile',
        'team' => 'Back to Team',
        'department' => 'Back to Department',
        default => 'Back to Users',
    };
@endphp

<div class="max-w-4xl mx-auto py-4 space-y-6">
    <!-- Back Navigation -->
    <div>
        <x-contextual-back :url="$cancelRoute" :label="$backLabel" variant="link" />
    </div>

    <header class="mb-2">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($user) ? 'Edit User Account' : 'New User Account' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($user) ? 'Update the details of an existing user account' : 'Fill out the information below to register a new user.'}}</p>
    </header>

    <x-alert />

    <article class="card overflow-hidden">
        <form method="POST" action="{{ isset($user) ? route('admin.users.update', ['user' => $user]) : route('admin.users.store') }}">
            @csrf
            @isset($user)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $user->updated_at }}">
            @endisset
            @if(request()->filled('source') || old('source'))
                <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
            @endif
            @if(request()->filled('dept') || old('dept'))
                <input type="hidden" name="dept" value="{{ old('dept', request('dept')) }}">
            @endif
            @if(request()->filled('team') || old('team'))
                <input type="hidden" name="team" value="{{ old('team', request('team')) }}">
            @endif

            <div class="p-6 md:p-8 space-y-8">
                <!-- Section 1: Personal Information -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Personal Information</legend>
                    <div class="border-b border-slate-200 pb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <x-icons.user class="w-4 h-4 text-emerald-600" />
                            <span>Personal Information</span>
                        </h2>
                        <span class="text-xs text-slate-500"><span class="text-rose-500 font-bold">*</span> Required field</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-4">
                            <x-form.label for="user-first_name" :required="true">First Name</x-form.label>
                            <x-form.input name="first_name" id="user-first_name" required maxlength="255" :value="$user->first_name ?? null" placeholder="Enter First Name" />
                            <x-form.error name="first_name" />
                        </div>

                        <div class="md:col-span-3">
                            <x-form.label for="user-middle_name">Middle Name</x-form.label>
                            <x-form.input name="middle_name" id="user-middle_name" maxlength="255" :value="$user->middle_name ?? null" placeholder="Middle Name (Optional)" />
                            <x-form.error name="middle_name" />
                        </div>

                        <div class="md:col-span-3">
                            <x-form.label for="user-last_name" :required="true">Last Name</x-form.label>
                            <x-form.input name="last_name" id="user-last_name" required maxlength="255" :value="$user->last_name ?? null" placeholder="Enter Last Name" />
                            <x-form.error name="last_name" />
                        </div>

                        <div class="md:col-span-2">
                            <x-form.label for="user-name_ext">Ext (e.g., Jr.)</x-form.label>
                            <x-form.input name="name_ext" id="user-name_ext" maxlength="10" :value="$user->name_ext ?? null" placeholder="e.g., Jr., III" />
                            <x-form.error name="name_ext" />
                        </div>
                    </div>
                </fieldset>

                <!-- Section 2: Account & Communication -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Account & Communication</legend>
                    <div class="border-b border-slate-200 pb-2">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span>Account & Communication</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-4">
                            <x-form.label for="user-username" :required="true">Username</x-form.label>
                            <x-form.input name="username" id="user-username" required maxlength="100" :value="$user->username ?? null" placeholder="Enter Username" />
                            <x-form.error name="username" />
                        </div>

                        <div class="md:col-span-4">
                            <x-form.label for="user-contact" :required="true">Contact Number</x-form.label>
                            <x-form.input type="tel" name="contact" id="user-contact" required pattern="^(09|\+639)\d{9}$" :value="$user->contact ?? null" placeholder="09123456789 or +639123456789" />
                            <x-form.error name="contact" />
                        </div>

                        <div class="md:col-span-4">
                            <x-form.label for="user-email">Email Address</x-form.label>
                            <x-form.input type="email" name="email" id="user-email" maxlength="255" :value="$user->email ?? null" placeholder="user@paleco.coop" />
                            <x-form.error name="email" />
                        </div>
                    </div>
                </fieldset>

                <!-- Section 3: Organization & Role Assignment -->
                <fieldset class="space-y-5">
                    <legend class="sr-only">Organization & Security Role</legend>
                    <div class="border-b border-slate-200 pb-2">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span>Organization & Security Role</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-form.label for="user-role-select" :required="true">System Role</x-form.label>
                            <select id="user-role-select" name="role_id" {{ isset($user) ? 'disabled' : 'required' }}
                                class="tom-select-sync {{ $errors->has('role_id') ? 'border-rose-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : '' }}"
                                data-autosubmit="false" autocomplete="off">
                                <option value="" disabled {{ old('role_id', optional($user)->role_id) ? '' : 'selected' }}>Assign a role...</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" data-slug="{{ $role->slug_identifier }}" {{ old('role_id', optional($user)->role_id) == $role->id ? 'selected' : '' }}>
                                        {{ Str::headline($role->role_name) }}
                                    </option>
                                @endforeach
                            </select>

                            @if(isset($user))
                                <p class="mt-1.5 text-xs text-slate-500">System roles are locked after creation to preserve audit history.</p>
                            @endif
                            <x-form.error name="role_id" />
                        </div>

                        @php
                            $userRoleSlug = optional(optional($user ?? null)->role)->slug_identifier;
                            $isSupervisor = $userRoleSlug === 'supervisor';
                        @endphp
                        <div id="department-wrapper">
                            <x-form.label for="department-select">Department <span id="dept-required-marker" class="text-rose-500 font-bold {{ $isSupervisor ? '' : 'hidden' }}">*</span></x-form.label>
                            <select id="department-select" name="department_id" data-user-role-slug="{{ $userRoleSlug }}" data-autosubmit="false" autocomplete="off"
                                class="tom-select-sync {{ $errors->has('department_id') ? 'border-rose-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}"
                                {{ $isSupervisor ? 'required' : '' }}>
                                <option value="" disabled {{ old('department_id', optional($user)->department_id) ? '' : 'selected' }}>Select a department...</option>
                                @foreach ($depts as $id => $dept)
                                    <option value="{{ $id }}" {{ old('department_id', optional($user)->department_id) == $id ? 'selected' : '' }}>
                                        {{ $dept }}
                                    </option>
                                @endforeach
                            </select>

                            <p id="dept-team-message" class="mt-1.5 text-xs text-emerald-600 font-medium hidden">
                                Only supervisors can be directly assigned to a department.
                            </p>
                            <x-form.error name="department_id" />
                        </div>
                    </div>
                </fieldset>

                <!-- Section 4: Security Section (Creation Only) -->
                @if(!isset($user))
                    <fieldset class="space-y-5">
                        <legend class="sr-only">Password Credentials</legend>
                        <div class="border-b border-slate-200 pb-2">
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <span>Password Credentials</span>
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-form.label for="login-password" :required="true">Password</x-form.label>
                                <div class="relative">
                                    <x-form.input type="password" name="password" id="login-password" minlength="8" required placeholder="Minimum 8 characters" class="pr-10" />
                                    <button type="button" id="toggle-password-btn" aria-label="Toggle password visibility" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                        <x-icons.eye-slash id="eye-icon-closed" class="w-5 h-5" />
                                        <x-icons.eye id="eye-icon-open" class="hidden w-5 h-5" />
                                    </button>
                                </div>
                                <x-form.error name="password" />
                            </div>

                            <div>
                                <x-form.label for="confirm-password" :required="true">Confirm Password</x-form.label>
                                <div class="relative">
                                    <x-form.input type="password" name="password_confirmation" id="confirm-password" minlength="8" required placeholder="Must match password" class="pr-10" />
                                    <button type="button" id="toggle-confirm-password-btn" aria-label="Toggle confirmation password visibility" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                        <x-icons.eye-slash id="confirm-eye-closed" class="w-5 h-5" />
                                        <x-icons.eye id="confirm-eye-open" class="hidden w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                @endif
            </div>

            <div class="card-footer px-6 md:px-8 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    <x-icons.check-circle class="w-4 h-4" />
                    <span>{{ isset($user) ? 'Update User' : 'Create User' }}</span>
                </x-btn>
            </div>
        </form>
    </article>
</div>
@endsection
