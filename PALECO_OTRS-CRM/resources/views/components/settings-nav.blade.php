@props(['role'])
<div>
    <h3 class="px-2 text-[10px] font-bold text-white/40 uppercase tracking-widest mb-2">Preferences</h3>
    <a href="{{ route($role . '.settings') }}" @if(request()->routeIs($role . '.settings')) aria-current="page" @endif
        class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-colors {{ request()->routeIs($role . '.settings') ? 'bg-emerald-500 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
        <svg aria-hidden="true" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M4 7h16M4 17h16M8 4v6m8 4v6" /></svg>
        Settings
    </a>
</div>
