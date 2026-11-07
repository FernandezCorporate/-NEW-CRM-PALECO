@props([
    'type' => null,
    'message' => null,
])

@php
    $alertType = $type;
    $alertMessage = $message ?? (!empty($slot) && !$slot->isEmpty() ? (string) $slot : null);
    $errorList = [];

    if (!$alertType) {
        if (session('success')) {
            $alertType = 'success';
            $alertMessage = session('success');
        } elseif (session('error') || $errors->any()) {
            $alertType = 'error';
            $alertMessage = session('error');
            if ($errors->any()) {
                $errorList = $errors->all();
            }
        } elseif (session('warning')) {
            $alertType = 'warning';
            $alertMessage = session('warning');
        } elseif (session('info')) {
            $alertType = 'info';
            $alertMessage = session('info');
        }
    }

    $config = match($alertType) {
        'success' => [
            'title' => 'Success',
            'iconBg' => 'bg-emerald-100 text-emerald-600',
            'btnClass' => 'btn-primary',
            'btnText' => 'Continue',
        ],
        'error' => [
            'title' => 'Action Could Not Proceed',
            'iconBg' => 'bg-rose-100 text-rose-600',
            'btnClass' => 'btn-danger',
            'btnText' => 'Understood',
        ],
        'warning' => [
            'title' => 'Attention Required',
            'iconBg' => 'bg-amber-100 text-amber-600',
            'btnClass' => 'btn-warning',
            'btnText' => 'Acknowledge',
        ],
        'info' => [
            'title' => 'Notice',
            'iconBg' => 'bg-sky-100 text-sky-600',
            'btnClass' => 'btn-primary',
            'btnText' => 'OK',
        ],
        default => null,
    };
@endphp

@if($config && ($alertMessage || count($errorList) > 0))
    <dialog data-system-alert
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="system-alert-title"
        aria-describedby="system-alert-body"
        class="control-dialog fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/45 backdrop:backdrop-blur-xs overflow-hidden z-50">
        
        <div class="relative p-6 sm:p-7 text-center">
            <!-- Close icon button -->
            <button type="button" data-alert-close aria-label="Close dialog" class="absolute top-4 right-4 p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer">
                <x-icons.x-mark class="w-4 h-4" />
            </button>

            <!-- Circular Icon Badge -->
            <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full {{ $config['iconBg'] }} mb-4">
                @if($alertType === 'success')
                    <x-icons.check-circle class="h-8 w-8 text-emerald-600" />
                @elseif($alertType === 'error' || $alertType === 'warning')
                    <x-icons.exclamation-triangle class="h-8 w-8 {{ $alertType === 'error' ? 'text-rose-600' : 'text-amber-600' }}" />
                @else
                    <x-icons.information-circle class="h-8 w-8 text-sky-600" />
                @endif
            </div>

            <!-- Title -->
            <h2 id="system-alert-title" class="text-xl font-bold text-slate-900 mb-2">
                {{ $config['title'] }}
            </h2>

            <!-- Message Body -->
            <div id="system-alert-body" class="text-sm text-slate-600 leading-relaxed mb-6">
                @if($alertMessage)
                    <p>{{ $alertMessage }}</p>
                @endif

                @if(count($errorList) > 0)
                    <div class="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-lg text-left text-xs text-rose-800 space-y-1">
                        @if(!$alertMessage)
                            <p class="font-semibold mb-1">Please review the following:</p>
                        @endif
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errorList as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Dismiss / Action Button -->
            <div class="flex items-center justify-center">
                <button type="button" data-alert-close class="btn {{ $config['btnClass'] }} w-full py-2.5 text-sm font-semibold shadow-xs">
                    {{ $config['btnText'] }}
                </button>
            </div>
        </div>
    </dialog>

    <script>
        (function () {
            const dialog = document.querySelector('dialog[data-system-alert]');
            if (!dialog) return;

            const openDialog = () => {
                if (typeof dialog.showModal === 'function') {
                    try {
                        dialog.showModal();
                    } catch (e) {
                        dialog.setAttribute('open', '');
                    }
                } else {
                    dialog.setAttribute('open', '');
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openDialog);
            } else {
                openDialog();
            }

            // Close on backdrop click
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            // Close buttons
            dialog.querySelectorAll('[data-alert-close]').forEach((btn) => {
                btn.addEventListener('click', () => dialog.close());
            });
        })();
    </script>
@endif
