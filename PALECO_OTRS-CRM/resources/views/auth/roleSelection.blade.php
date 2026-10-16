<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Portal | PALECO CRM-CWD</title>
    <link rel="icon" type="image/png" href="{{ asset('images/paleco-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/paleco-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell auth-portal-lines h-full flex items-center justify-center p-4 md:p-6 select-none font-sans text-slate-800 antialiased">

    <main class="w-full max-w-5xl bg-white rounded-[1.75rem] shadow-2xl shadow-slate-900/10 flex flex-col md:flex-row overflow-hidden border border-white min-h-[580px]">
        
        <aside class="sidebar-surface w-full md:w-[45%] text-white p-8 md:p-10 flex flex-col justify-between relative overflow-hidden" aria-label="PALECO Portal Information">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold tracking-tight">PALECO Open-Source Ticket Request System</span>
                </div>

                <div class="mt-12 space-y-3">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight leading-snug">System Access Portal</h1>
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
        </aside>

        <section class="w-full md:w-[55%] p-8 md:p-12 flex flex-col justify-center" aria-label="Portal Selection">
            
            <header class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Select Your Portal</h2>
                <p class="text-sm text-slate-500 mt-1">Choose your workspace to proceed to login.</p>
            </header>

            @if (session('error') || $errors->any())
                <div class="mb-5 p-3.5 bg-rose-50 border-l-4 border-rose-500 rounded-lg text-xs text-rose-700 space-y-1" role="alert">
                    <div class="flex items-center gap-1.5">
                        <x-icons.exclamation-triangle class="w-4 h-4 shrink-0 text-rose-600" />
                        <span>{{ session('error') ?? $errors->first() }}</span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4">
                <a href="{{ route('portal.login', ['role' => 'admin']) }}" class="group block border-2 border-slate-200/90 rounded-2xl p-5 hover:border-emerald-600 hover:bg-emerald-50/50 transition-all cursor-pointer shadow-xs hover:shadow-md">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0">
                            <x-icons.gear class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">Administrator</h3>
                            <p class="text-xs text-slate-500 mt-0.5">System configuration and ticket workflow auditing</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('portal.login', ['role' => 'cwd_officer']) }}" class="group block border-2 border-slate-200/90 rounded-2xl p-5 hover:border-emerald-600 hover:bg-emerald-50/50 transition-all cursor-pointer shadow-xs hover:shadow-md">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0">
                            <x-icons.ticket class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">Consumer Welfare Desk</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Ticket dispatching and complaint resolution</p>
                        </div>
                    </div>
                </a>
            </div>

        </section>
    </main>
</body>
</html>
