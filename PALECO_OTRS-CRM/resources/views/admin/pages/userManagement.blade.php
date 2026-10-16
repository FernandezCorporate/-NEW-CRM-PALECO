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
            <a href="{{ route('admin.users', array_merge(request()->except(['filter', 'page']), ['filter' => $role->slug_identifier])) }}" @if(request('filter') === $role->slug_identifier) aria-current="page" @endif>
                {{ Str::headline($role->role_name) }}
                <span>{{ $role->active_count }} active</span>
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
    <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">User directory <span class="record-count ml-2">{{ number_format($users->total()) }}</span></h2>
                <p class="text-xs text-slate-500 mt-0.5">Manage employee access, role permissions, and user accounts</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="system-table w-full text-left border-collapse min-w-[950px]">
                <caption class="sr-only">User accounts directory and management actions</caption>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="px-6 py-4 w-72">User</th>
                        <th scope="col" class="px-6 py-4 w-40">Role</th>
                        <th scope="col" class="px-6 py-4 w-44">Contact</th>
                        <th scope="col" class="px-6 py-4 w-44">Last Login</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Status</th>
                        <th scope="col" class="px-6 py-4 w-36 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/75 transition-colors group">
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center gap-3">
                                    <!-- Initials Avatar -->
                                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-100">
                                        {{ $user->avatar_initials }}
                                    </div>
                                    <!-- User Info -->
                                    <div class="flex flex-col">
                                        <a href="{{ route('admin.users.show', ['user' => $user]) }}" class="font-bold text-emerald-700 text-sm hover:underline">
                                            {{ $user->full_name }}
                                        </a>
                                        <span class="text-xs text-slate-500 mt-0.5">{{ $user->username }}</span>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ Str::headline($user->role->role_name) }}
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 align-top text-slate-700 text-sm font-medium">
                                {{ $user->contact ?? '—' }} 
                            </td>
                            
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-slate-800 font-medium">{{ $user->last_login ? $user->last_login->format('M d, Y') : 'Never' }}</div>
                                @if($user->last_login)
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $user->last_login->format('h:i A') }}</div>
                                @endif
                            </td>
                            
                            <td class="px-6 py-4 align-top text-center">
                                @if($user->is_active)
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 text-[11px] font-bold tracking-wide">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border border-amber-200 bg-amber-50 text-amber-700 text-[11px] font-bold tracking-wide">
                                        Deactivated
                                    </span>
                                @endif
                            </td>
                            
                            <td class="px-6 py-4 align-top text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    
                                    <a href="{{ route('admin.users.show', ['user' => $user]) }}" class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" data-loading-text="" title="View User" aria-label="View user details for {{ $user->full_name }}">
                                        <x-icons.eye class="w-4 h-4" />
                                    </a>

                                    @can('update', $user)
                                        <a href="{{ route('admin.users.editForm', ['user' => $user]) }}" class="action-link p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-slate-100 rounded-lg transition-colors" data-loading-text="" title="Edit User" aria-label="Edit user {{ $user->full_name }}">
                                            <x-icons.pencil class="w-4 h-4" />
                                        </a>
                                    @else
                                        <button type="button" disabled class="p-1.5 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot edit an admin account" aria-label="Cannot edit an admin account">
                                            <x-icons.pencil class="w-4 h-4" />
                                        </button>
                                    @endcan
                                    
                                    @if($user->is_active)
                                        @can('deactivateConfirm', $user)
                                            <a href="{{ route('admin.users.deactivateConfirm', ['user' => $user]) }}" class="action-link p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" data-loading-text="" title="Deactivate User" aria-label="Deactivate user {{ $user->full_name }}">
                                                <x-icons.x-mark class="w-4 h-4" />
                                            </a>
                                        @else
                                            <button type="button" disabled class="p-1.5 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status" aria-label="Cannot modify admin status">
                                                <x-icons.x-mark class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    @else
                                        @can('reactivateConfirm', $user)
                                            <a href="{{ route('admin.users.reactivateConfirm', ['user' => $user]) }}" class="action-link p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" data-loading-text="" title="Reactivate User" aria-label="Reactivate user {{ $user->full_name }}">
                                                <x-icons.arrow-path class="w-4 h-4" />
                                            </a>
                                        @else
                                            <button type="button" disabled class="p-1.5 text-slate-300 cursor-not-allowed rounded-lg" title="Cannot modify an admin account status" aria-label="Cannot modify admin status">
                                                <x-icons.arrow-path class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center border-2 border-dashed border-slate-200 rounded-lg">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-50 mb-3">
                                    <x-icons.users class="w-6 h-6 text-slate-400" />
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">No users found</h3>
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
        <div class="mt-4">
            {{ $users->onEachSide(0)->links() }}
        </div>
    @endif

@endsection
