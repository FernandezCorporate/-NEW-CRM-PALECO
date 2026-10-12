<!DOCTYPE html>
<html lang="en" data-preferences-key="paleco-preferences-admin-{{ auth()->id() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | PALECO Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('images/paleco-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/paleco-logo.png') }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell refined-shell flex m-0 font-sans h-screen overflow-hidden">

    <a href="#main-content" class="skip-link">Skip to content</a>
    <header class="mobile-app-bar md:hidden">
        <button type="button" data-sidebar-toggle class="mobile-menu-button" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">
            <x-icons.bars-3 />
        </button>
        <div class="flex items-center gap-2.5">
            <img src="{{ asset('images/paleco-logo.png') }}" alt="" class="h-8 w-8">
            <div><strong class="block text-sm leading-none text-slate-900">PALECO</strong><span class="text-[10px] font-semibold uppercase tracking-[.13em] text-emerald-700">Control Center</span></div>
        </div>
    </header>
    <button type="button" data-sidebar-overlay class="sidebar-overlay md:hidden" aria-label="Close navigation"></button>

    <aside id="app-sidebar" class="app-sidebar sidebar-surface w-72 md:w-64 border-r border-white/10 h-full flex flex-col justify-between shrink-0 box-border text-slate-300">
        
        <div class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            
            <div class="px-6 py-6 border-b border-white/10 mb-6">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="w-10 h-10 drop-shadow-md">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-wide leading-tight">PALECO</h2>
                        <p class="text-[10px] text-emerald-400 font-light mt-0.5 uppercase tracking-wide">
                            {{ auth()->user() ? Str::headline(auth()->user()->role->role_name) : 'Portal' }} Console
                        </p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-4 space-y-6 pb-6">
                
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Operations</h3>
                    <ul class="list-none p-0 m-0 space-y-1">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" 
                                class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                <x-icons.dashboard />
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.tickets') }}" 
                                class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.tickets*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                <x-icons.ticket />
                                Tickets
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Update: Evaluating relational slug instead of enum value -->
                @if(auth()->check() && auth()->user()->role->slug_identifier === 'admin')
                    <div>
                        <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Administration</h3>
                        <ul class="list-none p-0 m-0 space-y-1 mb-3">
                            <li>
                                <a href="{{ route('admin.users') }}" 
                                class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.users*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.user />
                                    Users
                                </a>
                            </li>
                        </ul>

                        <ul class="list-none p-0 m-0 space-y-1 mb-3">
                            <li>
                                <a href="{{ route('admin.departments') }}" 
                                class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.departments*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.department />
                                    Departments
                                </a>
                            </li>
                        </ul>

                        <ul class="list-none p-0 m-0 space-y-1 mb-3"> 
                            <li> 
                                <a href="{{ route('admin.teams') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.teams*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.users />
                                    Teams 
                                </a> 
                            </li> 
                        </ul>


                        <ul class="list-none p-0 m-0 space-y-1 mb-3">
                            <li>
                                <a href="{{ route('admin.ticketCategories') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.ticketCategories*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.category />
                                    Ticket Categories
                                </a>
                            </li>
                        </ul>

                        <ul class="list-none p-0 m-0 space-y-1 mb-3">
                            <li>
                                <a href="{{ route('admin.monitoring.index') }}" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('admin.monitoring.*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.monitoring />
                                    System Monitoring
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif
                <x-settings-nav role="admin" />
            </nav>
        </div>

        <div class="border-t border-white/10 px-4 py-4 space-y-2 shrink-0">
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-rose-500/10 hover:text-rose-400 rounded-lg transition-colors cursor-pointer border-none bg-transparent text-left">
                    <x-icons.logout />
                    Logout
                </button>
            </form>

            <div class="mt-4 flex items-center gap-3 p-3 bg-white/5 rounded-xl border border-white/10">
                <div class="w-9 h-9 rounded-full bg-[#00a86b] text-white flex items-center justify-center font-bold text-sm shrink-0">
                    {{ auth()->check() ? auth()->user()->avatar_initials : '??' }}
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">
                        {{ auth()->check() ? (auth()->user()->full_name ?: auth()->user()->username) : 'Guest' }}
                    </p>
                    <p class="text-[10px] text-slate-400 truncate uppercase tracking-wider mt-0.5">
                        {{ auth()->check() ? Str::headline(auth()->user()->role->role_name) : 'No Role' }}
                    </p>
                </div>
            </div>
        </div>
    </aside>

    <main data-workspace-kind="@yield('workspace-kind', 'list')" id="main-content" tabindex="-1" class="workspace-surface refined-workspace app-main flex-1 h-full overflow-y-auto px-5 pb-8 pt-24 md:p-8 lg:p-10">
        @yield('content')
    </main>

</body>
</html>
