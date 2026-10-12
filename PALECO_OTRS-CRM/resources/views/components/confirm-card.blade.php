@props([
    'title',
    'action',
    'method' => 'POST',
    'cancelRoute',
    'backLabel' => 'Back',
    'confirmText' => 'Confirm',
    'loadingText' => 'Processing...',
    'variant' => 'danger', // danger, warning, success
    'consequences' => [],
    'hiddenInputs' => [],
])

@php
    $variantConfig = [
        'danger' => [
            'iconBg' => 'bg-rose-100',
            'iconColor' => 'text-rose-600',
            'borderColor' => 'border-rose-200',
            'contextBg' => 'bg-rose-50 border-rose-100 text-rose-800',
            'btnVariant' => 'danger',
            'defaultIcon' => 'trash',
        ],
        'warning' => [
            'iconBg' => 'bg-amber-100',
            'iconColor' => 'text-amber-600',
            'borderColor' => 'border-amber-200',
            'contextBg' => 'bg-amber-50 border-amber-100 text-amber-800',
            'btnVariant' => 'warning',
            'defaultIcon' => 'exclamation-triangle',
        ],
        'success' => [
            'iconBg' => 'bg-emerald-100',
            'iconColor' => 'text-emerald-600',
            'borderColor' => 'border-emerald-200',
            'contextBg' => 'bg-emerald-50 border-emerald-100 text-emerald-800',
            'btnVariant' => 'primary',
            'defaultIcon' => 'check-circle',
        ],
    ][$variant] ?? [
        'iconBg' => 'bg-slate-100',
        'iconColor' => 'text-slate-600',
        'borderColor' => 'border-slate-200',
        'contextBg' => 'bg-slate-50 border-slate-100 text-slate-800',
        'btnVariant' => 'primary',
        'defaultIcon' => 'exclamation-triangle',
    ];
@endphp

<div class="max-w-2xl mx-auto mt-10">
    <!-- Back / Cancel Link -->
    <div class="mb-6">
        <a href="{{ $cancelRoute }}" class="action-link inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors" data-loading-text="Returning...">
            <x-icons.arrow-left class="w-4 h-4 mr-1.5" />
            <span>{{ $backLabel }}</span>
        </a>
    </div>

    <!-- Alert Dialog Card -->
    <section role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" class="card {{ $variantConfig['borderColor'] }} overflow-hidden">
        <div class="p-6 sm:p-10 text-center">
            
            <!-- Warning / Action Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full {{ $variantConfig['iconBg'] }} mb-6">
                @if(isset($icon))
                    {{ $icon }}
                @elseif($variantConfig['defaultIcon'] === 'trash')
                    <x-icons.trash class="h-8 w-8 {{ $variantConfig['iconColor'] }}" />
                @elseif($variantConfig['defaultIcon'] === 'check-circle')
                    <x-icons.check-circle class="h-8 w-8 {{ $variantConfig['iconColor'] }}" />
                @else
                    <x-icons.exclamation-triangle class="h-8 w-8 {{ $variantConfig['iconColor'] }}" />
                @endif
            </div>

            <!-- Dialog Title -->
            <h2 id="confirm-dialog-title" class="text-2xl font-bold text-slate-900 mb-2">
                {{ $title }}
            </h2>

            <!-- Dialog Body Description -->
            <div class="text-slate-600 mb-6 leading-relaxed text-sm sm:text-base">
                {{ $slot }}
            </div>

            <!-- Optional Consequences Box -->
            @if(count($consequences) > 0)
                <div class="{{ $variantConfig['contextBg'] }} border rounded-lg p-4 mb-8 text-left text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($consequences as $item)
                            <li>{!! $item !!}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Confirmation Form -->
            <form action="{{ $action }}" method="POST" class="flex flex-col-reverse sm:flex-row justify-center gap-3">
                @csrf
                @if(strtoupper($method) !== 'POST')
                    @method($method)
                @endif

                @foreach($hiddenInputs as $key => $val)
                    @if($val !== null && $val !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                    @endif
                @endforeach

                <x-btn variant="secondary" href="{{ $cancelRoute }}" class="action-link w-full sm:w-auto" data-loading-text="Canceling...">
                    Cancel
                </x-btn>

                <x-btn :variant="$variantConfig['btnVariant']" type="submit" class="w-full sm:w-auto" :data-loading-text="$loadingText">
                    @if(isset($confirmIcon))
                        {{ $confirmIcon }}
                    @endif
                    <span>{{ $confirmText }}</span>
                </x-btn>
            </form>
        </div>
    </section>
</div>

