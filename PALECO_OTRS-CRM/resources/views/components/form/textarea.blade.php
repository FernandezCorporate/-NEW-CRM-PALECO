@props([
    'name',
    'id' => null,
    'value' => null,
    'rows' => 4,
])

@php
    $inputId = $id ?? $name;
    $hasError = $errors->has($name);
    $errorClasses = $hasError ? '!border-rose-400 !focus:border-rose-500 !focus:ring-rose-500/20' : '';
@endphp

<textarea
    name="{{ $name }}"
    id="{{ $inputId }}"
    rows="{{ $rows }}"
    {{ $attributes->merge(['class' => "form-input {$errorClasses}"]) }}
    @if($hasError)
        aria-invalid="true"
        aria-describedby="error-{{ $name }}"
    @endif
>{{ $value ?? old($name) }}</textarea>

