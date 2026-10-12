@extends('admin.base.base')

@section('workspace-kind', 'form')

@section('title', isset($dept) ? 'Edit Department' : 'Create New Department')

@section('content')
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($dept) ? 'Edit Department' : 'Create New Department' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($dept) ? 'Update the details of this department.' : 'Add a new department to the cooperative structure.' }}</p>
    </header>

    <x-alert />

    <article class="card max-w-2xl overflow-hidden">
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

            <div class="card-body space-y-6">
                <div>
                    <x-form.label for="dept_name" :required="true">Department Name</x-form.label>
                    <x-form.input name="dept_name" id="dept_name" :value="$dept->dept_name ?? null" required placeholder="Enter Department Name" />
                    <x-form.error name="dept_name" />
                </div>

                <div>
                    <x-form.label for="dept_desc">Department Description</x-form.label>
                    <x-form.textarea name="dept_desc" id="dept_desc" :value="$dept->dept_desc ?? null" placeholder="Enter Department Description" rows="4" />
                    <p class="mt-1.5 text-[11px] text-slate-400">Optional. Briefly describe the responsibilities of this department.</p>
                </div>
            </div>

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
            @endphp

            <div class="card-footer flex items-center justify-end gap-3">
                <x-btn variant="secondary" href="{{ $cancelRoute }}" data-loading-text="Canceling...">
                    Cancel
                </x-btn>
                <x-btn variant="primary" type="submit" data-loading-text="Saving...">
                    Save Department
                </x-btn>
            </div>
        </form>
    </article>
@endsection
