@extends('admin.base.base')

@section('title', isset($user) ? 'Edit User Account' : 'Create New User Account')

@section('content')
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($user) ? 'Edit User Account' : 'New User Account' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($user) ? 'Update the details of an existing user account' : 'Fill out the information below to register a new user.'}}</p>
    </div>

    @include('admin.prompts.alert')

    <!-- Form Container -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 lg:p-8 max-w-5xl">
        
        <form method="POST" action="{{ isset($user) ? route('admin.users.update', ['user' => $user]) : route('admin.users.store') }}">
            @csrf
            @isset($user)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $user->updated_at }}">
            @endisset

            <div class="mb-6 pb-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-base font-semibold text-slate-800">Account Details</h2>
                <span class="text-xs text-slate-500"><span class="text-rose-500 font-bold">*</span> Indicates a required field</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

                <div class="md:col-span-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">First Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="first_name" required maxlength="255" value="{{ old('first_name', $user->first_name ?? '') }}" placeholder="Enter First Name"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('first_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" maxlength="255" value="{{ old('middle_name', $user->middle_name ?? '') }}" placeholder="Enter Middle Name (Optional)"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('middle_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Last Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="last_name" required maxlength="255" value="{{ old('last_name', $user->last_name ?? '') }}" placeholder="Enter Last Name"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('last_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ext (e.g., Jr.)</label>
                    <input type="text" name="name_ext" maxlength="10" value="{{ old('name_ext', $user->name_ext ?? '') }}" placeholder="Enter Extension (optional)"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('name_ext') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                    <input type="email" name="email" maxlength="255" value="{{ old('email', $user->email ?? '') }}" placeholder="user@paleco.coop"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Contact Number <span class="text-rose-500">*</span></label>
                    <input type="tel" name="contact" required pattern="^(09|\+639)\d{9}$" value="{{ old('contact', $user->contact ?? '') }}" placeholder="09123456789 or +639123456789" 
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('contact') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-12">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username <span class="text-rose-500">*</span></label>
                    <input type="text" name="username" required maxlength="100" value="{{ old('username', $user->username ?? '') }}" placeholder="Enter Username"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                    @error('username') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <!-- Organization Row (MODIFIED FOR VISUAL LOCK) -->
                <div class="md:col-span-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">System Role <span class="text-rose-500">*</span></label>
                
                <!-- Simplified classes, added tom-select-sync, removed manual SVG chevron -->
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
                    <p class="mt-1 text-xs text-slate-500">System roles are locked after creation to preserve audit history.</p>
                @endif
                @error('role_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

                <div class="md:col-span-6" id="department-wrapper">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Department</label>
                    
                    <!-- Merged classes, removed 'hidden', kept data-autosubmit="false" -->
                    <select id="department-select" name="department_id" data-autosubmit="false" autocomplete="off"
                        class="tom-select-sync {{ $errors->has('department_id') ? 'border-rose-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500' : 'border-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500' }}">
                        
                        <option value="" disabled {{ old('department_id', optional($user)->department_id) ? '' : 'selected' }}>Select a department...</option>
                        @foreach ($depts as $id => $dept)
                            <option value="{{ $id }}" {{ old('department_id', optional($user)->department_id) == $id ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                        @endforeach
                    </select>
                    
                    <p id="dept-team-message" class="mt-1 text-xs text-emerald-600 font-medium hidden">
                        Only foremen can be directly assigned to a department.<br>
                        Field personnel departments are defined by team assignment.
                    </p>
                    @error('department_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <!-- Row 5: Security (Only visible during creation) -->
                @if(!isset($user))
                    <!-- ... Keep password fields exactly the same as provided ... -->
                    <div class="md:col-span-6">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="password" name="password" id="login-password" minlength="8" required placeholder="Minimum 8 characters"
                                class="w-full px-3.5 py-2.5 pr-10 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                            
                            <button type="button" id="toggle-password-btn" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                <svg id="eye-icon-closed" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                <svg id="eye-icon-open" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </button>
                        </div>
                        @error('password') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-6">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="password" name="password_confirmation" id="confirm-password" minlength="8" required placeholder="Must match password"
                                class="w-full px-3.5 py-2.5 pr-10 border border-slate-300 rounded-lg shadow-sm text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white">
                            
                            <button type="button" id="toggle-confirm-password-btn" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                <svg id="confirm-eye-closed" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                <svg id="confirm-eye-open" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Form Actions -->
            <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('admin.users') }}" 
                    class="action-link px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-200 transition-colors"
                    data-loading-text="Canceling...">
                    Cancel
                </a>
                
                <button type="submit" class="action-btn inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500" data-loading-text="Saving...">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ isset($user) ? 'Update User' : 'Create User' }}
                </button>
            </div>
        </form>
    </div>
@endsection