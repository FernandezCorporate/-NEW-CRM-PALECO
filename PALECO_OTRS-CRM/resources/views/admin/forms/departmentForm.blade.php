@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($dept) ? 'Edit Department' : 'Create New Department')

@section('content')
@php
    $source = old('source', request('source'));
    $userId = old('user', request('user'));
    $teamId = old('team', request('team'));
    $cancelRoute = match($source) {
        'details' => isset($dept)
            ? route('admin.departments.show', array_filter(['dept' => $dept, 'source' => $userId ? 'user' : ($teamId ? 'team' : null), 'user' => $userId, 'team' => $teamId]))
            : route('admin.departments'),
        'user' => $userId ? route('admin.users.show', ['user' => $userId]) : (isset($dept) ? route('admin.departments.show', ['dept' => $dept]) : session('department_list_url', route('admin.departments'))),
        'team' => $teamId ? route('admin.teams.show', ['team' => $teamId]) : (isset($dept) ? route('admin.departments.show', ['dept' => $dept]) : session('department_list_url', route('admin.departments'))),
        default => session('department_list_url', route('admin.departments')),
    };
    $backLabel = match($source) {
        'details' => 'Back to Department Details',
        'user' => 'Back to User Profile',
        'team' => 'Back to Team',
        default => 'Back to Departments',
    };
@endphp

<div class="max-w-2xl mx-auto py-4 space-y-6">
    <!-- Back Navigation -->
    <div>
        <x-contextual-back :url="$cancelRoute" :label="$backLabel" variant="link" />
    </div>

    <header class="mb-2">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($dept) ? 'Edit Department' : 'Create New Department' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($dept) ? 'Update the details of this department.' : 'Add a new department to the cooperative structure.' }}</p>
    </header>

    <x-alert />

    <article class="card overflow-hidden">
        <form action="{{ isset($dept) ? route('admin.departments.update', ['dept' => $dept, 'source' => request('source')]) : route('admin.departments.store') }}" method="POST">
            @csrf

            @isset($dept)
                @method('PUT')
                <input type="hidden" name="original_updated_at" value="{{ $dept->updated_at }}">
            @endisset
            @if(request()->filled('source') || old('source'))
                <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
            @endif
            @if(request()->filled('user') || old('user'))
                <input type="hidden" name="user" value="{{ old('user', request('user')) }}">
            @endif
            @if(request()->filled('team') || old('team'))
                <input type="hidden" name="team" value="{{ old('team', request('team')) }}">
            @endif    

            <div class="p-6 md:p-8 space-y-6">
                <fieldset class="space-y-5">
                    <legend class="sr-only">Department Information</legend>
                    <div class="border-b border-slate-200 pb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span>Department Details</span>
                        </h2>
                        <span class="text-xs text-slate-500"><span class="text-rose-500 font-bold">*</span> Required field</span>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <x-form.label for="dept_name" :required="true">Department Name</x-form.label>
                            <x-form.input name="dept_name" id="dept_name" :value="$dept->dept_name ?? null" required placeholder="e.g., Technical Services Department" />
                            <x-form.error name="dept_name" />
                        </div>

                        <div>
                            <x-form.label for="dept_desc">Department Description</x-form.label>
                            <x-form.textarea name="dept_desc" id="dept_desc" :value="$dept->dept_desc ?? null" placeholder="Briefly describe the operational scope and responsibilities of this department..." rows="4" />
                            <p class="mt-1.5 text-[11px] text-slate-400">Optional. Summarize the role of this unit within PALECO operations.</p>
                            <x-form.error name="dept_desc" />
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="card-footer px-6 md:px-8 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    <x-icons.check-circle class="w-4 h-4" />
                    <span>{{ isset($dept) ? 'Update Department' : 'Save Department' }}</span>
                </x-btn>
            </div>
        </form>
    </article>
</div>
@endsection
