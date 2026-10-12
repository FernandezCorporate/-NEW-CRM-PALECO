@props(['subtitle' => 'Console'])

<a href="#main-content" class="skip-link">Skip to content</a>

<header class="mobile-app-bar md:hidden">
    <button type="button" data-sidebar-toggle class="mobile-menu-button" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">
        <x-icons.bars-3 />
    </button>
    <div class="flex items-center gap-2.5">
        <img src="{{ asset('images/paleco-logo.png') }}" alt="PALECO Logo" class="h-8 w-8">
        <div>
            <strong class="block text-sm leading-none text-slate-900">PALECO</strong>
            <span class="text-[10px] font-semibold uppercase tracking-[.13em] text-emerald-700">{{ $subtitle }}</span>
        </div>
    </div>
</header>

<button type="button" data-sidebar-overlay class="sidebar-overlay md:hidden" aria-label="Close navigation"></button>

