@props(['size' => 'w-5 h-5', 'stroke' => '1.5'])

<svg {{ $attributes->merge(['class' => "$size shrink-0"]) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="{{ $stroke }}" d="M4 7h16M4 17h16M8 4v6m8 4v6" />
</svg>

