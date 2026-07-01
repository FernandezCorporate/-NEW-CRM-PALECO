<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard') - {{ config('app.name') }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex m-0 font-sans bg-slate-50 min-h-screen">

    <aside class="w-64 bg-[#063321] border-r border-[#0a4d32] h-screen flex flex-col justify-between box-border sticky top-0 text-slate-300">
        
        <div class="flex flex-col h-full overflow-y-auto overflow-x-hidden">
            
            <div class="px-6 py-6 border-b border-white/10 mb-6">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="w-8 h-8 drop-shadow-md">
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wider leading-tight">PALECO CWD</h2>
                        <p class="text-[10px] text-emerald-400 font-light mt-0.5 uppercase tracking-wide">
                            {{ auth()->user() ? auth()->user()->role->value : 'Portal' }} Console
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
                               class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#00a86b] text-white shadow-md' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                    </ul>
                </div>

                @if(auth()->check() && auth()->user()->role->value === 'admin')
                    <div>
                        <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Administration</h3>
                        <ul class="list-none p-0 m-0 space-y-1">
                            <li>
                                <a href="{{ route('admin.departments') }}" 
                                   class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.departments') ? 'bg-[#00a86b] text-white shadow-md' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path>
                                    </svg>
                                    Departments
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif
            </nav>
        </div>

        <div class="border-t border-white/10 px-4 py-4 space-y-2">
            
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-rose-500/10 hover:text-rose-400 rounded-lg transition-colors cursor-pointer border-none bg-transparent text-left">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"></path>
                    </svg>
                    Logout
                </button>
            </form>

            <div class="mt-4 flex items-center gap-3 p-3 bg-white/5 rounded-xl border border-white/10">
                <div class="w-9 h-9 rounded-full bg-[#00a86b] text-white flex items-center justify-center font-bold text-sm shrink-0">
                    {{ auth()->check() ? strtoupper(substr(auth()->user()->username, 0, 2)) : '??' }}
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-white truncate">
                        {{ auth()->check() && auth()->user()->first_name ? auth()->user()->first_name . ' ' . auth()->user()->last_name : (auth()->check() ? auth()->user()->username : 'Guest') }}
                    </p>
                    <p class="text-[10px] text-slate-400 truncate uppercase tracking-wider mt-0.5">
                        {{ auth()->check() ? auth()->user()->role->value : 'No Role' }} • IT
                    </p>
                </div>
            </div>

        </div>
    </aside>

    <main class="flex-1 p-8 overflow-y-auto bg-slate-50">
        @yield('content')
    </main>

</body>
</html>