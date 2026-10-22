<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $role === 'admin' ? 'Administrator' : 'CWD Officer' }} Sign In | PALECO</title>
    <link rel="icon" type="image/png" href="{{ asset('images/paleco-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/paleco-logo.png') }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell auth-portal-lines h-full flex items-center justify-center p-4 md:p-6 font-sans text-slate-800 antialiased">

    <main class="w-full max-w-5xl bg-white rounded-[1.75rem] shadow-2xl shadow-slate-900/10 flex flex-col md:flex-row overflow-hidden border border-white min-h-[580px]">
        
        <!-- Left Brand Section -->
        <aside class="sidebar-surface w-full md:w-[45%] text-white p-8 md:p-10 flex flex-col justify-between relative overflow-hidden" aria-label="PALECO Portal Information">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold tracking-tight">PALECO Open Ticket Request System</span>
                </div>

                <div class="mt-12 space-y-3">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight leading-snug">Ticketing & Dispatch System</h1>
                    <p class="text-sm text-slate-300 font-light leading-relaxed max-w-sm">
                        24/7 complaint intake, team assignment, and cooperative-level reporting for PALECO's Member Services Division.
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

        <!-- Right Login Form Section -->
        <section class="w-full md:w-[55%] p-8 md:p-12 flex flex-col justify-center" aria-label="Sign In Credentials">
            
            <header class="mb-6">
                <a href="{{ route('portal') }}" class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-emerald-700 transition-colors mb-4 gap-1.5 group">
                    <x-icons.arrow-left class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" />
                    <span>Back to Portal Selection</span>
                </a>
                
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ $role === 'admin' ? 'Administrator' : 'CWD Officer' }} Sign in
                </h2>
                <p class="text-sm text-slate-500 mt-1">Provide your credentials to continue</p>
            </header>

            @if ($errors->any())
                <div class="mb-5 p-3.5 bg-rose-50 border-l-4 border-rose-500 rounded-lg text-xs text-rose-700 space-y-1" role="alert">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-center gap-1.5">
                            <x-icons.exclamation-triangle class="w-4 h-4 shrink-0 text-rose-600" />
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('attemptLogin', ['role' => $role]) }}" method="POST" class="space-y-5" autocomplete="off">
                @csrf

                <div class="space-y-1.5">
                    <div class="flex justify-between items-end">
                        <label for="username" class="text-xs font-semibold text-slate-700 tracking-wide">
                            Username <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] text-slate-400">Required</span>
                    </div>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Enter your username" required autocomplete="off" class="form-input w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-500/10 placeholder-slate-400 transition-all shadow-2xs">
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-end">
                        <label for="login-password" class="text-xs font-semibold text-slate-700 tracking-wide">
                            Password <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] text-slate-400">Required</span>
                    </div>
                    <div class="relative">
                        <input type="password" id="login-password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password" class="form-input w-full pl-3.5 pr-11 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-500/10 placeholder-slate-400 transition-all shadow-2xs">
                        
                        <button type="button" id="toggle-password-btn" data-password-toggle="login-password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-emerald-700 transition-colors focus:outline-none cursor-pointer" title="Show password" aria-label="Show password" aria-pressed="false">
                            <span id="eye-icon-open" class="hidden flex items-center">
                                <x-icons.eye class="w-5 h-5" />
                            </span>
                            <span id="eye-icon-closed" class="flex items-center">
                                <x-icons.eye-slash class="w-5 h-5" />
                            </span>
                        </button>
                    </div>
                </div>

                <button type="submit" data-loading-text="Signing in..." class="btn btn-primary w-full py-3 text-sm font-semibold shadow-md shadow-emerald-700/15 gap-2 mt-2">
                    <x-icons.arrow-path class="w-4 h-4" />
                    <span>Sign in</span>
                </button>
            </form>
        </section>

    </main>
</body>
</html>
