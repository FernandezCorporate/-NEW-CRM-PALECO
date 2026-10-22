@props(['id', 'label' => 'More information'])

{{-- Encircled "i" that explains a value on hover, keyboard focus or tap; CSS only, so it keeps working after live-refresh swaps --}}
<span class="relative inline-flex group/info">
    <button type="button" class="inline-flex items-center justify-center rounded-full text-slate-400 hover:text-slate-700 focus:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 cursor-help" aria-label="{{ $label }}" aria-describedby="{{ $id }}">
        <x-icons.information-circle size="w-4 h-4" />
    </button>
    <span id="{{ $id }}" role="tooltip" class="pointer-events-none invisible opacity-0 group-hover/info:visible group-hover/info:opacity-100 group-focus-within/info:visible group-focus-within/info:opacity-100 transition-opacity duration-150 absolute left-1/2 top-full mt-2 -translate-x-1/2 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-lg bg-slate-900/95 text-white px-3 py-2.5 text-xs font-normal leading-relaxed text-left shadow-xl border border-slate-700/60 whitespace-normal normal-case tracking-normal">
        {{ $slot }}
    </span>
</span>
