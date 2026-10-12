@extends('admin.base.base')

@section('title', 'User Management')

@section('content')

    <x-alert />

    <!-- Page Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">User Management</h1>
            <p class="text-sm text-slate-500 mt-1">Create, update, and deactivate accounts across all roles.</p>
        </div>
        
        <x-btn href="{{ route('admin.users.createForm') }}" variant="primary" class="action-link" data-loading-text="Loading...">
            <x-icons.plus class="w-4 h-4 mr-1.5" />
            New User
        </x-btn>
    </header>

    <nav class="record-tabs" aria-label="Filter users by role">
        <a href="{{ route('admin.users', array_merge(request()->except(['filter', 'page']), ['filter' => 'all'])) }}" @if(!request('filter') || request('filter') === 'all') aria-current="page" @endif>All users</a>
        @foreach($roles as $role)
            @php
                $countKey = match($role->slug_identifier) {
                    'admin' => 'admin', 'cwd_officer' => 'cwd',
                    'supervisor' => 'supervisor', 'field_personnel' => 'field_personnel', default => null,
                };
            @endphp
            <a href="{{ route('admin.users', array_merge(request()->except(['filter', 'page']), ['filter' => $role->slug_identifier])) }}" @if(request('filter') === $role->slug_identifier) aria-current="page" @endif>
                {{ Str::headline($role->role_name) }}
                @if($countKey)<span>{{ $activeCounts->{$countKey} }} active</span>@endif
            </a>
        @endforeach
    </nav>

    <!-- Filter & Search Toolbar -->
    <search class="filter-toolbar mb-6" aria-label="Search and filter users">
        <form action="{{ route('admin.users') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
            
            <!-- Search Input -->
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search users..." value="{{ request('search') }}" class="form-input w-full md:w-64" aria-label="Search users by name, username, or contact">
                <x-btn type="submit" variant="primary" size="md" data-loading-text="Searching...">Search</x-btn>
                @if(request()->filled('search'))
                    <x-btn href="{{ route('admin.users', request()->except('search')) }}" variant="secondary" size="md" class="action-link" data-loading-text="Clearing...">Clear</x-btn>
                @endif
            </div>

            <!-- Role and Sort Controls -->
            <div class="flex items-center gap-2">
                <input type="hidden" name="filter" value="{{ request('filter', 'all') }}">

                <select name="sort" class="ts-filter-dropdown" aria-label="Sort users">
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                    <option value="first_nameASC" {{ request('sort') === 'first_nameASC' ? 'selected' : '' }}>First Name (A-Z)</option>
                    <option value="first_nameDESC" {{ request('sort') === 'first_nameDESC' ? 'selected' : '' }}>First Name (Z-A)</option>
                    <option value="last_nameASC" {{ request('sort') === 'last_nameASC' ? 'selected' : '' }}>Last Name (A-Z)</option>
                    <option value="last_nameDESC" {{ request('sort') === 'last_nameDESC' ? 'selected' : '' }}>Last Name (Z-A)</option>
                </select>
                <noscript><x-btn type="submit" variant="secondary" size="md">Apply</x-btn></noscript>
            </div>
            
        </form>
    </search>

    <!-- Table Container -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse">
                <caption class="sr-only">User accounts directory and management actions</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4">User</th>
                        <th scope="col" class="px-6 py-4">Role</th>
                        <th scope="col" class="px-6 py-4">Contact</th>
                        <th scope="col" class="px-6 py-4">Last Login</th>
                        <th scope="col" class="px-6 py-4">Status</th>
                        <th scope="col" class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <!-- Initials Avatar -->
                                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-100">
                                        {{ $user->avatar_initials }}
                                    </div>
                                    <!-- User Info -->
                                    <div class="flex flex-col">
                                        <span class="font-medium text-slate-800">{{ $user->full_name }}</span>
                                        <span class="text-xs text-slate-500 mt-0.5">{{ $user->username }}</span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <x-badge variant="slate">
                                    {{ Str::headline($user->role->role_name) }}
                                </x-badge>
                            </td>
                            
                            <td class="px-6 py-4 text-slate-600 text-sm">
                                {{ $user->contact ?? '—' }} 
                            </td>
                            
                            <td class="px-6 py-4 text-slate-500 text-sm">
                                {{ $user->last_login ? $user->last_login->format('Y-m-d H:i') : 'Never' }}
                            </td>
                            
                            <td class="px-6 py-4">
                                @if($user->is_active)
                                    <x-badge variant="emerald">Active</x-badge>
                                @else
                                    <x-badge variant="amber">Deactivated</x-badge>
                                @endif
                            </td>
                            
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <a href="{{ route('admin.users.show', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-loading-text="" title="View User" aria-label="View user details for {{ $user->full_name }}">
                                        <x-icons.eye class="w-5 h-5" />
                                    </a>

                                    @can('update', $user)
                                        <a href="{{ route('admin.users.editForm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" data-loading-text="" title="Edit User" aria-label="Edit user {{ $user->full_name }}">
                                            <x-icons.pencil class="w-5 h-5" />
                                        </a>
                                    @else
                                        <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot edit an admin account" aria-label="Cannot edit an admin account">
                                            <x-icons.pencil class="w-5 h-5" />
                                        </button>
                                    @endcan
                                    
                                    @if($user->is_active)
                                        @can('deactivateConfirm', $user)
                                            <a href="{{ route('admin.users.deactivateConfirm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" data-loading-text="" title="Deactivate User" aria-label="Deactivate user {{ $user->full_name }}">
                                                <x-icons.x-mark class="w-5 h-5" />
                                            </a>
                                        @else
                                            <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status" aria-label="Cannot modify admin status">
                                                <x-icons.x-mark class="w-5 h-5" />
                                            </button>
                                        @endcan
                                    @else
                                        @can('reactivateConfirm', $user)
                                            <a href="{{ route('admin.users.reactivateConfirm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" data-loading-text="" title="Reactivate User" aria-label="Reactivate user {{ $user->full_name }}">
                                                <x-icons.arrow-path class="w-5 h-5" />
                                            </a>
                                        @else
                                            <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status" aria-label="Cannot modify admin status">
                                                <x-icons.arrow-path class="w-5 h-5" />
                                            </button>
                                        @endcan
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.users class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-semibold text-slate-800">No users found</h3>
                                <p class="text-xs text-slate-500 mt-1">Adjust your search or filter to find what you're looking for.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->onEachSide(0)->links() }}
        </div>
    @endif

@endsection
