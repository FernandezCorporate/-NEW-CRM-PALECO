<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Portal - PALECO CRM-CWD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell h-full flex items-center justify-center p-4 md:p-6 select-none">

    <div class="w-full max-w-5xl bg-white rounded-[1.75rem] shadow-2xl shadow-slate-900/10 flex flex-col md:flex-row overflow-hidden border border-white" style="min-height: 580px;">
        
        <div class="sidebar-surface w-full md:w-[45%] text-white p-8 md:p-10 flex flex-col justify-between relative overflow-hidden">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight">PALECO CRM-CWD</h1>
                </div>
                <p class="text-xs text-emerald-300 font-light mt-0.5">Consumer Welfare Desk Portal</p>

                <div class="mt-12 space-y-3">
                    <h2 class="text-2xl md:text-3xl font-semibold tracking-tight leading-snug">System Access Portal</h2>
                    <p class="text-sm text-slate-300 font-light leading-relaxed max-w-sm">
                        Select your designated department portal below to access the Ticketing & Dispatch System.
                    </p>
                </div>
            </div>

            <div class="my-10 md:my-0 flex justify-center items-center">
                <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="w-56 h-auto drop-shadow-xl transform hover:scale-105 transition duration-300">
            </div>

            <div class="text-xs text-slate-400 font-light tracking-wide mt-auto">
                Hotlines: Globe & Smart
            </div>
        </div>

        <div class="w-full md:w-[55%] p-8 md:p-12 flex flex-col justify-center">
            
            <header class="mb-8">
                <h3 class="text-2xl font-bold text-slate-800">Select Your Portal</h3>
                <p class="text-sm text-slate-500 mt-1">Choose your workspace to proceed to login.</p>
            </header>

            @if (session('error') || $errors->any())
                <div class="mb-5 p-3.5 bg-rose-50 border-l-4 border-rose-500 rounded text-xs text-rose-700 space-y-1">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        <span>{{ session('error') ?? $errors->first() }}</span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4">
                <a href="{{ route('portal.login', ['role' => 'admin']) }}" class="group block border-2 border-slate-200 rounded-xl p-5 hover:border-emerald-600 hover:bg-emerald-50 transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">Administrator</h4>
                            <p class="text-xs text-slate-500 mt-0.5">System configuration and master data management</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('portal.login', ['role' => 'cwd_officer']) }}" class="group block border-2 border-slate-200 rounded-xl p-5 hover:border-emerald-600 hover:bg-emerald-50 transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">Consumer Welfare Desk</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Ticket dispatching and complaint resolution</p>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>
</body>
</html>
