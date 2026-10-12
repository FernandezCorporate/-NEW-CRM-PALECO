<!DOCTYPE html>
<html lang="en" data-preferences-key="paleco-preferences-cwd-{{ auth()->id() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | PALECO CWD</title>
    <link rel="icon" type="image/png" href="{{ asset('images/paleco-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/paleco-logo.png') }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="app-shell refined-shell flex m-0 font-sans h-screen overflow-hidden">

    <x-mobile-app-bar subtitle="Service Desk" />

    <aside id="app-sidebar" class="app-sidebar sidebar-surface w-72 md:w-64 border-r border-white/10 h-full flex flex-col justify-between shrink-0 box-border text-slate-300">
        
        <div class="flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            
            <header class="px-6 py-6 border-b border-white/10 mb-6">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="w-10 h-10 drop-shadow-md">
                    <div>
                        <h2 class="text-base font-bold text-white tracking-wide leading-tight">PALECO</h2>
                        <p class="text-[10px] text-emerald-400 font-light mt-0.5 uppercase tracking-wide">
                            {{ auth()->user() ? Str::headline(auth()->user()->role->role_name) : 'Portal' }} Console
                        </p>
                    </div>
                </div>
            </header>

            <nav class="flex-1 px-4 space-y-6 pb-6" aria-label="Primary navigation">
                
                <div>
                    <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Operations</h3>
                    <ul class="list-none p-0 m-0 space-y-1">
                        <li>
                            <a href="{{ route('cwd.dashboard') }}" 
                               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('cwd.dashboard') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                <x-icons.dashboard />
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                @if(auth()->check() && auth()->user()->role->slug_identifier === 'cwd_officer')
                    <div>
                        <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Service Desk</h3>
                        <ul class="list-none p-0 m-0 space-y-1">
                            <li>
                                <a href="{{ route('cwd.tickets') }}" 
                                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('cwd.tickets*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.ticket />
                                    <span>Tickets</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ route('cwd.endorsements') }}" 
                                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('cwd.endorsements*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.endorsement />
                                    <span>Endorsements</span>
                                </a>
                            </li>

                            <li>
                                <a href="{{ route('cwd.consumers') }}" 
                                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all {{ request()->routeIs('cwd.consumers*') ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                                    <x-icons.user />
                                    <span>Consumer Profiles</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif

                <x-settings-nav role="cwd" />
            </nav>
        </div>

        <x-sidebar-user-card />
    </aside>

    <main data-workspace-kind="@yield('workspace-kind', 'list')" id="main-content" tabindex="-1" class="workspace-surface refined-workspace app-main flex-1 h-full overflow-y-auto px-5 pb-8 pt-24 md:p-8 lg:p-10">
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>