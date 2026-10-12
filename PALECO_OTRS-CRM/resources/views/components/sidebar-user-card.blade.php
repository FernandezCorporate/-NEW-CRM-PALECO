<div class="border-t border-white/10 px-4 py-4 space-y-2 shrink-0">
    <form action="{{ route('logout') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-rose-500/10 hover:text-rose-400 rounded-lg transition-colors cursor-pointer border-none bg-transparent text-left">
            <x-icons.logout />
            <span>Logout</span>
        </button>
    </form>

    <div class="mt-4 flex items-center gap-3 p-3 bg-white/5 rounded-xl border border-white/10">
        <div class="w-9 h-9 rounded-full bg-paleco-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-xs">
            {{ auth()->check() ? auth()->user()->avatar_initials : '??' }}
        </div>
        <div class="overflow-hidden">
            <p class="text-sm font-bold text-white truncate">
                {{ auth()->check() ? (auth()->user()->full_name ?: auth()->user()->username) : 'Guest' }}
            </p>
            <p class="text-[10px] text-slate-400 truncate uppercase tracking-wider mt-0.5">
                {{ auth()->check() && auth()->user()->role ? Str::headline(auth()->user()->role->role_name) : 'No Role' }}{{ auth()->check() && auth()->user()->department ? ' • ' . auth()->user()->department->dept_name : '' }}
            </p>
        </div>
    </div>
</div>

