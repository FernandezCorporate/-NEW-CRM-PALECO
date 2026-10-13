@extends('cwd.base.base')

@section('workspace-kind', 'dashboard')
@section('title', 'CWD Dashboard')

@section('content')
    <div class="max-w-7xl mx-auto space-y-8">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Welcome back, {{ auth()->user()->first_name ?? 'Officer' }}</p>
                <h1 class="page-title mt-2">PALECO Service Desk</h1>
                <p class="mt-2 text-sm text-slate-500">Receive, organize, and follow through on consumer service requests.</p>
            </div>
            <a href="{{ route('cwd.tickets.createForm', ['source' => 'dashboard']) }}" class="btn btn-primary text-sm py-3 px-4 shadow-sm gap-2">
                <x-icons.plus class="h-4 w-4" />
                <span>Create ticket</span>
            </a>
        </header>

        <!-- Livewire Real-Time Overview Island -->
        <livewire:cwd.dashboard-overview />

        <section class="cwd-action-panels grid gap-6 xl:grid-cols-2">
            <x-dashboard-controls :control-catalog="$overview['cwd_controls'] ?? []" :default-controls="['tickets', 'endorsements']"
                :storage-key="'paleco-cwd-controls-' . auth()->id()" title="Keep requests moving" eyebrow="Quick actions" />

            <aside data-animate class="ui-reveal panel flex flex-col overflow-hidden" style="--delay: 90ms" aria-labelledby="cwd-queue-title">
                <div class="bg-gradient-to-br from-emerald-900 to-emerald-700 px-6 py-5 text-white md:px-7">
                    <p class="text-[11px] font-bold uppercase tracking-[.14em] text-emerald-200">Your next step</p>
                    <h2 id="cwd-queue-title" class="mt-2 text-xl font-bold">Keep an eye on the queue</h2>
                </div>
                <ul class="flex flex-1 flex-col divide-y divide-slate-100">
                    @foreach ($overview['queue_items'] ?? [] as [$queueLabel, $queueCount, $queueHint, $queueUrl])
                        <li class="flex flex-1">
                            <a href="{{ $queueUrl }}" class="group flex w-full items-center gap-4 px-6 py-3 md:px-7 transition-colors hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-600">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-slate-800">{{ $queueLabel }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $queueCount > 0 ? $queueHint : 'No requests in this queue' }}</span>
                                </span>
                                <strong class="text-xl font-bold tabular-nums {{ $queueCount > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    <data value="{{ $queueCount }}">{{ number_format($queueCount) }}</data>
                                </strong>
                                <x-icons.chevron-right class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </section>
    </div>
@endsection