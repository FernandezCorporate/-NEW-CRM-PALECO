@props([
    'name',
])

@error($name)
    <p {{ $attributes->merge(['class' => 'form-error']) }} id="error-{{ $name }}" role="alert">
        {{ $message }}
    </p>
@enderror

