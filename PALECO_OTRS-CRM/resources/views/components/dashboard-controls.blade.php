@props(['controlCatalog', 'defaultControls', 'storageKey', 'title', 'eyebrow' => 'Workspace'])

<div data-animate data-dashboard-controls data-storage-key="{{ $storageKey }}" data-default-controls='@json($defaultControls)' class="ui-reveal panel p-6 md:p-7">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $title }}</h2>
        </div>
        <button type="button" data-controls-open class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" /></svg>
            Add controls
        </button>
    </div>
    <div data-controls-grid class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $iconPath])
            <a href="{{ $url }}" data-control-item="{{ $id }}" class="quick-link {{ in_array($id, $defaultControls, true) ? '' : 'hidden' }}">
                <span class="metric-icon !h-10 !w-10 {{ $tone }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $iconPath }}" /></svg></span>
                <span class="min-w-0"><strong class="block truncate text-sm text-slate-800">{{ $label }}</strong><small class="block truncate text-slate-500">{{ $description }}</small></span>
            </a>
        @endforeach
    </div>
    <p data-controls-empty class="mt-6 hidden rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">No controls selected. Use “Add controls” to choose shortcuts.</p>
    <p data-controls-feedback class="sr-only" aria-live="polite"></p>

    <dialog aria-labelledby="controls-title" data-controls-dialog class="control-dialog w-[calc(100%-2rem)] max-w-xl rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/45 backdrop:backdrop-blur-sm">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="eyebrow">Personalize workspace</p>
            <h3 id="controls-title" class="mt-1 text-xl font-bold text-slate-900">Choose dashboard controls</h3>
            <p class="mt-1 text-sm text-slate-500">Select up to four shortcuts. Your choices are saved for this account on this device.</p>
        </div>
        <div class="grid gap-2 px-6 py-5 sm:grid-cols-2">
            @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $iconPath])
                <label class="control-choice flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                    <input type="checkbox" value="{{ $id }}" data-control-choice class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 accent-emerald-600" {{ in_array($id, $defaultControls, true) ? 'checked' : '' }}>
                    <span><strong class="block text-sm text-slate-800">{{ $label }}</strong><small class="mt-0.5 block leading-5 text-slate-500">{{ $description }}</small></span>
                </label>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p data-controls-count class="text-xs font-semibold text-slate-500">{{ count($defaultControls) }} of 4 selected</p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-controls-reset class="min-h-10 rounded-lg px-3 text-xs font-semibold text-slate-500 transition hover:bg-slate-200/70 hover:text-slate-800">Reset defaults</button>
                <button type="button" data-controls-cancel class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                <button type="button" data-controls-save class="min-h-10 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white transition hover:bg-emerald-700">Save controls</button>
            </div>
        </div>
    </dialog>
</div>
