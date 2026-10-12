@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'tag' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $element = $href ? 'a' : $tag;
    $classes = "btn btn-{$variant} btn-{$size}" . ($disabled ? ' is-disabled' : '');
@endphp

@if($element === 'a')
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) aria-disabled="true" tabindex="-1" @endif>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif

