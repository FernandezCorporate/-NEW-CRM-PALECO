@props(['size' => 'w-5 h-5', 'stroke' => '2'])

<svg {{ $attributes->merge(['class' => "$size shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
</svg>

