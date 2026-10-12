<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Notice') | PALECO CRM</title>
    <link rel="icon" type="image/png" href="{{ asset('images/paleco-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/paleco-logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell auth-portal-lines min-h-screen flex items-center justify-center p-4 md:p-6 bg-slate-100 font-sans antialiased text-slate-800">

    <main class="w-full max-w-xl bg-white rounded-[2rem] shadow-2xl shadow-slate-900/10 border border-slate-100 p-8 md:p-12 text-center relative overflow-hidden">
        
        <!-- Subtle Decorative Background Glow -->
        <div class="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-slate-400/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- PALECO Brand Header -->
        <header class="flex items-center justify-center gap-3 mb-8">
            <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="h-12 w-auto drop-shadow-md">
            <div class="text-left">
                <span class="block text-lg font-bold text-slate-800 leading-tight">PALECO CRM</span>
                <span class="block text-xs font-semibold text-emerald-600 uppercase tracking-wider">Palawan Electric Cooperative</span>
            </div>
        </header>

        <!-- Dynamic Icon/Badge Section -->
        <div class="flex justify-center mb-6">
            @yield('badge')
        </div>

        <!-- Status Code and Heading -->
        <div class="space-y-2 mb-4">
            <span class="inline-block px-3 py-1 text-xs font-bold uppercase tracking-widest text-emerald-700 bg-emerald-50 rounded-full border border-emerald-200/60">
                Error @yield('code')
            </span>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">
                @yield('heading')
            </h1>
        </div>

        <!-- Explanatory Context Message -->
        <p class="text-sm md:text-base text-slate-500 font-normal leading-relaxed max-w-md mx-auto mb-8">
            @yield('message')
        </p>

        <!-- Smart Navigation Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 pt-2">
            <button type="button"
                    data-smart-back
                    data-fallback-url="{{ auth()->check() ? route('dashboard') : route('portal') }}"
                    class="btn btn-primary w-full sm:w-auto px-6 py-3 text-sm font-semibold shadow-md shadow-emerald-700/20 gap-2.5">
                <x-icons.arrow-left class="w-4 h-4 shrink-0" />
                <span>Return to Previous Page</span>
            </button>

            @php
                $dashboardUrl = auth()->check() ? route('dashboard') : route('portal');
                $dashboardLabel = auth()->check() ? 'Go to Dashboard' : 'Back to Portal';
            @endphp
            <a href="{{ $dashboardUrl }}"
               class="btn btn-secondary w-full sm:w-auto px-6 py-3 text-sm font-semibold shadow-xs gap-2.5">
                <x-icons.dashboard class="w-4 h-4 shrink-0 text-slate-400" />
                <span>{{ $dashboardLabel }}</span>
            </a>
        </div>

        <!-- Footer / Support Line -->
        <footer class="mt-10 pt-6 border-t border-slate-100 text-xs text-slate-400 font-light flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>Member Services Division</span>
            <span>24/7 Hotline Support Available</span>
        </footer>
    </main>
</body>
</html>

