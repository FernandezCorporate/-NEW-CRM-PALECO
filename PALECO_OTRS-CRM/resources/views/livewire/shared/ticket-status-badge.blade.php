<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow-2xs border transition-colors duration-300 {{ $ticket->status->badgeClasses() }}">
    <span class="w-2 h-2 rounded-full {{ $ticket->status->chartClass() }}"></span>
    {{ $ticket->status->label() }}
</span>

