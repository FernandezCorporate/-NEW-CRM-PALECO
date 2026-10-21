@props([
    'events',
    'message',
    'match' => null,
    'channel' => 'cwd.operations',
])

{{-- Hidden until resources/js/live-refresh.js hears one of the events; forms show this instead of re-rendering, so typed input is kept --}}
<div hidden role="status" data-live-notice data-live-events="{{ $events }}" data-live-channel="{{ $channel }}" @if($match) data-live-match="{{ $match }}" @endif
    {{ $attributes->merge(['class' => 'mb-6 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-xs']) }}>
    <span class="flex items-center gap-2">
        <x-icons.exclamation-triangle class="w-4 h-4 shrink-0" />
        <span>{{ $message }}</span>
    </span>
    <button type="button" onclick="window.location.reload()" class="shrink-0 font-semibold underline underline-offset-2 hover:text-amber-900">Reload</button>
</div>
