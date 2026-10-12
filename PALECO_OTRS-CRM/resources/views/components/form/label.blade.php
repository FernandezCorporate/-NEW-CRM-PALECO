@props([
    'for' => null,
    'required' => false,
])

<label {{ $for ? "for={$for}" : '' }} {{ $attributes->merge(['class' => 'form-label']) }}>
    {{ $slot }}
    @if ($required)
        <span class="text-rose-500 font-bold" aria-hidden="true">*</span>
    @endif
</label>

