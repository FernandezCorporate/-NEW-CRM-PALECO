@props([
    'type' => null,
    'message' => null,
])

{{-- Explicit Prop-Driven Alert --}}
@if ($type && ($message || !$slot->isEmpty()))
    @php
        $typeStyles = [
            'success' => 'bg-emerald-50 border-emerald-500 text-emerald-800',
            'error'   => 'bg-rose-50 border-rose-500 text-rose-800',
            'info'    => 'bg-sky-50 border-sky-500 text-sky-800',
            'warning' => 'bg-amber-50 border-amber-500 text-amber-800',
        ][$type] ?? 'bg-slate-50 border-slate-500 text-slate-800';

        $iconColors = [
            'success' => 'text-emerald-600',
            'error'   => 'text-rose-600',
            'info'    => 'text-sky-600',
            'warning' => 'text-amber-600',
        ][$type] ?? 'text-slate-600';
    @endphp

    <div role="alert" {{ $attributes->merge(['class' => "mb-6 p-4 border-l-4 rounded-r-lg flex items-start gap-3 shadow-xs $typeStyles"]) }}>
        <div class="{{ $iconColors }} shrink-0 mt-0.5">
            @if($type === 'success')
                <x-icons.check-circle />
            @elseif($type === 'error' || $type === 'warning')
                <x-icons.exclamation-triangle />
            @else
                <x-icons.information-circle />
            @endif
        </div>
        <div class="text-sm font-medium flex-1">
            {{ $message ?? $slot }}
        </div>
    </div>
@else
    {{-- Automatic Session Flash Messages --}}
    @if (session('success'))
        <div role="alert" aria-live="polite" class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg flex items-start gap-3 shadow-xs">
            <x-icons.check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
            <div class="text-sm text-emerald-800 font-medium">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div role="alert" aria-live="assertive" class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg flex items-start gap-3 shadow-xs">
            <x-icons.exclamation-triangle class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
            <div class="text-sm text-rose-800 flex-1">
                @if(session('error'))
                    <span class="font-medium">{{ session('error') }}</span>
                @else
                    <span class="font-medium">Please correct the following errors:</span>
                    <ul class="list-disc list-inside mt-1 ml-1 opacity-90 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    @if (session('info'))
        <div role="alert" aria-live="polite" class="mb-6 p-4 bg-sky-50 border-l-4 border-sky-500 rounded-r-lg flex items-start gap-3 shadow-xs">
            <x-icons.information-circle class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" />
            <div class="text-sm text-sky-800 font-medium">
                {{ session('info') }}
            </div>
        </div>
    @endif
@endif

