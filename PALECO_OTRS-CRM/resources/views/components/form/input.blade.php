@props([
    'name',
    'id' => null,
    'type' => 'text',
    'value' => null,
])

@php
    $inputId = $id ?? $name;
    $hasError = $errors->has($name);
    $errorClasses = $hasError ? '!border-rose-400 !focus:border-rose-500 !focus:ring-rose-500/20' : '';
@endphp

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $inputId }}"
    value="{{ $value ?? old($name) }}"
    {{ $attributes->merge(['class' => "form-input {$errorClasses}"]) }}
    @if($hasError)
        aria-invalid="true"
        aria-describedby="error-{{ $name }}"
    @endif
>

