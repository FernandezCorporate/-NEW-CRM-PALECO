@extends('admin.base.base')

@section('title', 'User Management')

@section('content')

    @include('admin.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">User Management</h1>
            <p class="text-sm text-slate-500 mt-1">Create, update, and deactivate accounts across all roles.</p>
        </div>
        
        <a href="{{ route('admin.users.createForm') }}" class="action-link inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]" data-loading-text="Loading...">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            New User
        </a>
    </div>

    <nav class="record-tabs" aria-label="Filter users by role">
        <a href="{{ route('admin.users', array_merge(request()->except(['filter', 'page']), ['filter' => 'all'])) }}" @if(!request('filter') || request('filter') === 'all') aria-current="page" @endif>All users</a>
        @foreach($roles as $role)
            @php
                $countKey = match($role->slug_identifier) {
                    'admin' => 'admin', 'cwd_officer' => 'cwd',
                    'foreman' => 'foreman', 'field_personnel' => 'field_personnel', default => null,
                };
            @endphp
            <a href="{{ route('admin.users', array_merge(request()->except(['filter', 'page']), ['filter' => $role->slug_identifier])) }}" @if(request('filter') === $role->slug_identifier) aria-current="page" @endif>
                {{ Str::headline($role->role_name) }}
                @if($countKey)<span>{{ $activeCounts->{$countKey} }} active</span>@endif
            </a>
        @endforeach
    </nav>

    <!-- Controls Row -->
<div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
    <form action="{{ route('admin.users') }}" method="GET" class="flex flex-col md:flex-row flex-wrap gap-4 w-full lg:w-auto">
        
        <!-- Search -->
        <div class="flex items-center gap-2">
            <input type="text" name="search" placeholder="Search users..." value="{{ request('search') }}" class="border border-slate-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-full md:w-64">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Searching...">Search</button>
            @if(request()->filled('search'))
                <a href="{{ route('admin.users', request()->except('search')) }}" class="action-link bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm transition-colors" data-loading-text="Clearing...">Clear</a>
            @endif
        </div>

        <!-- Filters (Wrapped inside the form!) -->
        <div class="flex items-center gap-2">
            <input type="hidden" name="filter" value="{{ request('filter', 'all') }}">

            <select name="sort" class="ts-filter-dropdown hidden">
                <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                <option value="first_nameASC" {{ request('sort') === 'first_nameASC' ? 'selected' : '' }}>First Name (A-Z)</option>
                <option value="first_nameDESC" {{ request('sort') === 'first_nameDESC' ? 'selected' : '' }}>First Name (Z-A)</option>
                <option value="last_nameASC" {{ request('sort') === 'last_nameASC' ? 'selected' : '' }}>Last Name (A-Z)</option>
                <option value="last_nameDESC" {{ request('sort') === 'last_nameDESC' ? 'selected' : '' }}>Last Name (Z-A)</option>
            </select>
            <noscript><button type="submit" class="bg-slate-500 text-white px-4 py-2 rounded">Apply</button></noscript>
        </div>
        
    </form>
</div>

    <!-- Table Container -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-4">User</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Contact</th>
                    <th class="px-6 py-4">Last Login</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
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
                            <div class="flex flex-col items-start gap-1.5">
                                <span class="bg-slate-100 text-slate-600 text-[11px] font-semibold px-2.5 py-1 rounded-full">
                                    {{ Str::headline($user->role->role_name) }}
                                </span>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 text-slate-600 text-sm">
                            {{ $user->contact ?? '—' }} 
                        </td>
                        
                        <td class="px-6 py-4 text-slate-500 text-sm">
                            {{ $user->last_login ? $user->last_login->format('Y-m-d H:i') : 'Never' }}
                        </td>
                        
                        <td class="px-6 py-4">
                            @if($user->is_active)
                                <span class="bg-emerald-50 border border-emerald-200 text-emerald-600 text-xs font-semibold px-2.5 py-1 rounded-full">Active</span>
                            @else
                                <span class="bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full">Deactivated</span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                
                                <a href="{{ route('admin.users.show', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" data-loading-text="" title="View User">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>

                                @can('update', $user)
                                    <a href="{{ route('admin.users.editForm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" data-loading-text="" title="Edit User">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                @else
                                    <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot edit an admin account">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                @endcan
                                
                                @if($user->is_active)
                                    @can('deactivateConfirm', $user)
                                        <a href="{{ route('admin.users.deactivateConfirm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" data-loading-text="" title="Deactivate User">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </a>
                                    @else
                                        <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </button>
                                    @endcan
                                @else
                                    @can('reactivateConfirm', $user)
                                        <a href="{{ route('admin.users.reactivateConfirm', ['user' => $user]) }}" class="action-link p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" data-loading-text="" title="Reactivate User">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                        </a>
                                    @else
                                        <button type="button" disabled class="p-2 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
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
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <h3 class="text-sm font-semibold text-slate-800">No users found</h3>
                            <p class="text-xs text-slate-500 mt-1">Adjust your search or filter to find what you're looking for.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
        <div class="mt-6">
            {{ $users->onEachSide(0)->links() }}
        </div>
    @endif

@endsection
