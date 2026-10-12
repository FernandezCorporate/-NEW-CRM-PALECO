@props([
    'variant' => 'emerald',
    'dot' => false,
])

<span {{ $attributes->merge(['class' => "badge badge-{$variant}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>

