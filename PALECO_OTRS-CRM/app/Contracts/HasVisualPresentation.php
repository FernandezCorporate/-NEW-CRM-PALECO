<?php

namespace App\Contracts;

/**
 * Defines the standard contract for domain enums providing uniform visual presentation.
 * Guarantees consistent badge classes, hex codes, and chart colors across Web & Mobile UI.
 */
interface HasVisualPresentation
{
    /**
     * Human-readable text label.
     */
    public function label(): string;

    /**
     * Tailwind CSS classes for badge/pill styling.
     */
    public function badgeClasses(): string;

    /**
     * Hexadecimal color code for charts, SVGs, or dynamic canvas components.
     */
    public function colorHex(): string;

    /**
     * Tailwind CSS background color class for chart bars, graphs, and indicators.
     */
    public function chartClass(): string;

    /**
     * Tailwind CSS background color class for status dot indicators.
     */
    public function dotClass(): string;
}

