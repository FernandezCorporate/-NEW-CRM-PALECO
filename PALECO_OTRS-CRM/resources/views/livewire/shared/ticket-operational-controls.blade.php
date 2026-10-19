<div class="flex items-center gap-2 flex-wrap shrink-0">
    @can('update', $ticket)
        <a href="{{ route('cwd.tickets.editForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')])) }}" class="btn btn-secondary text-xs py-2 px-3.5 shadow-2xs">
            <x-icons.pencil class="w-4 h-4 text-slate-500" />
            <span>Edit Details</span>
        </a>
    @endcan

    @can('createChild', $ticket)
        <a href="{{ route('cwd.tickets.children.createForm', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer')])) }}" class="btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 px-3.5 shadow-2xs">
            <x-icons.plus class="w-4 h-4" />
            <span>Create Child Ticket</span>
        </a>
    @endcan

    @can('cancel', $ticket)
        <a href="{{ route('cwd.tickets.cancelConfirm', $ticket) }}" class="btn bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs py-2 px-3.5 shadow-2xs transition-colors">
            <x-icons.x-mark class="w-4 h-4 text-rose-600" />
            <span>Cancel Ticket</span>
        </a>
    @endcan

    @can('forceClose', $ticket)
        <a href="{{ auth()->user()?->role?->slug_identifier === 'admin' ? route('admin.tickets.forceCloseConfirm', $ticket) : route('cwd.tickets.forceCloseConfirm', $ticket) }}" class="btn bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs py-2 px-3.5 shadow-2xs transition-colors">
            <x-icons.exclamation-triangle class="w-4 h-4 text-amber-600" />
            <span>Force Close</span>
        </a>
    @endcan
</div>
