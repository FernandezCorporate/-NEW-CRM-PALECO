@props(['size' => 'w-5 h-5', 'stroke' => '2'])

<svg {{ $attributes->merge(['class' => "$size shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M6 18L18 6M6 6l12 12" />
</svg>

