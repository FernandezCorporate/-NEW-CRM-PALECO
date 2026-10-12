@props(['controlCatalog', 'defaultControls', 'storageKey', 'title', 'eyebrow' => 'Workspace'])

<div data-animate data-dashboard-controls data-storage-key="{{ $storageKey }}" data-default-controls='@json($defaultControls)' class="ui-reveal panel p-6 md:p-7">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $title }}</h2>
        </div>
        <button type="button" data-controls-open class="btn btn-secondary text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-300 border-emerald-200 text-xs font-bold gap-2">
            <x-icons.plus class="h-4 w-4" />
            <span>Add controls</span>
        </button>
    </div>
    <div data-controls-grid class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $icon])
            <a href="{{ $url }}" data-control-item="{{ $id }}" class="quick-link {{ in_array($id, $defaultControls, true) ? '' : 'hidden' }}">
                <span class="metric-icon !h-10 !w-10 {{ $tone }}">
                    @if (str_starts_with($icon, 'M'))
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $icon }}" /></svg>
                    @else
                        <x-dynamic-component :component="'icons.' . $icon" class="h-5 w-5" />
                    @endif
                </span>
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
            @foreach ($controlCatalog as [$id, $label, $description, $url, $tone, $icon])
                <label class="control-choice flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                    <input type="checkbox" value="{{ $id }}" data-control-choice class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 accent-emerald-600" {{ in_array($id, $defaultControls, true) ? 'checked' : '' }}>
                    <span><strong class="block text-sm text-slate-800">{{ $label }}</strong><small class="mt-0.5 block leading-5 text-slate-500">{{ $description }}</small></span>
                </label>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p data-controls-count class="text-xs font-semibold text-slate-500">{{ count($defaultControls) }} of 4 selected</p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-controls-reset class="btn btn-ghost text-xs py-2 px-3">Reset defaults</button>
                <button type="button" data-controls-cancel class="btn btn-secondary text-xs py-2 px-4">Cancel</button>
                <button type="button" data-controls-save class="btn btn-primary text-xs py-2 px-4 shadow-xs">Save controls</button>
            </div>
        </div>
    </dialog>
</div>
