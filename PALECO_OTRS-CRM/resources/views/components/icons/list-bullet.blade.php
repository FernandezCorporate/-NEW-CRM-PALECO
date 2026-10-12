@props(['size' => 'w-5 h-5', 'stroke' => '2'])

<svg {{ $attributes->merge(['class' => "$size shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
</svg>

