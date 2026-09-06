@extends($layout)
@section('title', 'Settings')
@section('workspace-kind', 'settings')
@section('content')
<div class="max-w-4xl mx-auto">
    <header class="mb-8">
        <p class="eyebrow">Your workspace</p>
        <h1 class="page-title mt-2">Settings</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Make PALECO easier to read and comfortable to use. Changes apply immediately and are saved for your account on this browser only.</p>
    </header>
    <form data-preferences-form class="panel p-6 md:p-8 space-y-8">
        <fieldset>
            <legend class="text-lg font-bold text-slate-900">Display mode</legend>
            <p class="mt-1 text-sm text-slate-500">Choose a light or dark PALECO workspace.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach (['light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                    <label class="preference-choice"><input type="radio" name="mode" value="{{ $value }}" @checked($value === 'light')><span>{{ $label }}</span></label>
                @endforeach
            </div>
        </fieldset>
        <fieldset class="border-t border-slate-200 pt-6">
            <legend class="text-lg font-bold text-slate-900">Text size</legend>
            <p class="mt-1 text-sm text-slate-500">Adjust interface text. Browser zoom is still available for further enlargement.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach (['standard' => 'Standard', 'large' => 'Large', 'larger' => 'Extra large'] as $value => $label)
                    <label class="preference-choice"><input type="radio" name="textSize" value="{{ $value }}" @checked($value === 'standard')><span>{{ $label }}</span></label>
                @endforeach
            </div>
        </fieldset>
        <fieldset class="border-t border-slate-200 pt-6">
            <legend class="text-lg font-bold text-slate-900">Comfort & display</legend>
            <div class="mt-3 divide-y divide-slate-100">
                @foreach ([
                    ['reduceMotion', 'Reduce motion', 'Minimize animations and transitions. Your device’s reduced-motion preference is always respected.', false],
                    ['compact', 'Compact tables', 'Use tighter table rows to show more records. Buttons and form fields remain easy to use.', false],
                    ['pattern', 'Subtle background lines', 'Keep the diagonal line pattern behind your workspace.', true],
                ] as [$name, $label, $description, $checked])
                    <label class="flex cursor-pointer items-start gap-4 py-4">
                        <input class="mt-1 h-5 w-5 shrink-0" type="checkbox" name="{{ $name }}" @checked($checked)>
                        <span><strong class="block text-sm text-slate-800">{{ $label }}</strong><span class="mt-1 block text-sm leading-6 text-slate-500">{{ $description }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <footer class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
            <p data-preferences-status role="status" class="text-sm text-slate-600">Changes save automatically.</p>
            <button type="button" data-preferences-reset class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset appearance</button>
        </footer>
        <noscript><p>Enable JavaScript to change and save your preferences.</p></noscript>
    </form>
    <p class="mt-4 text-xs leading-5 text-slate-500">These settings do not change other users’ workspaces or your dashboard shortcuts. Clearing browser data removes saved preferences.</p>
</div>
@endsection
