@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'Create Child Ticket - ' . $parentTicket->ticket_number)

@section('content')
@php
    $source = old('source', request('source'));
    $consumerId = old('consumer', request('consumer'));
    $cancelRoute = route('cwd.tickets.show', array_filter([
        'ticket' => $parentTicket,
        'source' => $source,
        'consumer' => $consumerId,
    ]));
    $backLabel = 'Back to Parent Ticket';
@endphp

<div class="max-w-5xl mx-auto my-6 space-y-4">
    <!-- Back Navigation -->
    <div>
        <x-contextual-back :url="$cancelRoute" :label="$backLabel" variant="link" />
    </div>

    <article class="card p-6 sm:p-10">
        
        <!-- Header -->
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                Create Child Ticket
                <x-badge variant="indigo">Sub-Task Order</x-badge>
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Spawn a subordinate service ticket linked to parent ticket <span class="font-bold text-slate-700">{{ $parentTicket->ticket_number }}</span>.</p>
        </header>

    <!-- Parent Ticket Reference Card -->
    <section class="mb-8 p-5 bg-slate-50 border border-slate-200 rounded-xl" aria-label="Parent ticket reference summary">
        <div class="flex items-center justify-between mb-3 border-b border-slate-200 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Parent Ticket Reference</span>
                <span class="text-sm font-bold text-slate-900 font-mono">{{ $parentTicket->ticket_number }}</span>
            </div>
            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full border {{ $parentTicket->status->badgeClasses() }}">
                {{ $parentTicket->status->label() }}
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <span class="block text-slate-400 font-medium uppercase tracking-wider text-[10px]">Origin Department</span>
                <span class="font-bold text-slate-800">{{ $parentTicket->department->dept_name ?? 'Unassigned' }}</span>
            </div>
            <div>
                <span class="block text-slate-400 font-medium uppercase tracking-wider text-[10px]">Category</span>
                <span class="font-bold text-slate-800">{{ $parentTicket->other_category ? $parentTicket->other_category_name : ($parentTicket->category->category_name ?? 'Unspecified') }}</span>
            </div>
            <div>
                <span class="block text-slate-400 font-medium uppercase tracking-wider text-[10px]">Linked Consumer</span>
                <span class="font-bold text-slate-800">{{ $parentTicket->consumer ? $parentTicket->consumer->name : 'No consumer profile attached' }}</span>
            </div>
            <div class="md:col-span-3">
                <span class="block text-slate-400 font-medium uppercase tracking-wider text-[10px]">Original Complaint Summary</span>
                <p class="text-slate-700 mt-0.5 leading-relaxed bg-white p-2.5 rounded border border-slate-200">{{ $parentTicket->complaint_description }}</p>
            </div>
        </div>
    </section>

    <x-alert />

    <form action="{{ route('cwd.tickets.children.store', $parentTicket) }}" method="POST" autocomplete="off" class="space-y-8">
        @csrf
        @if($source)
            <input type="hidden" name="source" value="{{ $source }}">
        @endif
        @if($consumerId)
            <input type="hidden" name="consumer" value="{{ $consumerId }}">
        @endif

        <!-- Section 1: Classification & Contact -->
        <fieldset class="space-y-6">
            <legend class="sr-only">Classification and Nature</legend>

            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <x-icons.ticket class="w-4 h-4 text-emerald-600" />
                    <span>Task Classification & Category</span>
                </h2>
            </div>

            <!-- Row 1: Nature of Complaint & Custom Category Input -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div class="relative">
                    <div class="flex items-center justify-between mb-1.5">
                        <x-form.label id="category_id_label" for="category_id" :required="true">Ticket Category</x-form.label>
                        
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-slate-600 hover:text-emerald-700 select-none">
                            <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category', $parentTicket->other_category) ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Unlisted / Custom</span>
                        </label>
                    </div>
                    
                    <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600" {{ old('other_category', $parentTicket->other_category) ? '' : 'required' }}>
                        <option value="">Choose standard category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $parentTicket->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="mb-1.5">
                        <label id="other_category_name_label" for="other_category_name" class="form-label {{ old('other_category', $parentTicket->other_category) ? 'text-slate-700 font-bold' : 'text-slate-400' }}">
                            Custom Category Specification <span id="other_category_required_marker" class="text-rose-500 {{ old('other_category', $parentTicket->other_category) ? '' : 'hidden' }}">*</span>
                        </label>
                    </div>
                    <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name', $parentTicket->other_category_name) }}" {{ old('other_category', $parentTicket->other_category) ? 'required' : 'disabled' }} class="form-input disabled:bg-slate-50 disabled:text-slate-400 disabled:border-slate-200 transition-colors" placeholder="Enter manual category name">
                </div>
            </div>

            <!-- Row 2: Consumer Contact (Inherited/Override) -->
            <div>
                <x-form.label for="consumer_contact">Consumer Contact Number (Optional override)</x-form.label>
                <x-form.input type="tel" name="consumer_contact" id="consumer_contact" :value="old('consumer_contact', $parentTicket->consumer_contact)" placeholder="09123456789" maxlength="11" inputmode="numeric" pattern="^09\d{9}$" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" />
                <p class="mt-1 text-[11px] text-slate-500">Pre-populated from parent ticket. Edit only if another on-site contact is coordinating for this sub-task.</p>
            </div>
        </fieldset>

        <!-- Section 2: Incident Location -->
        <fieldset class="space-y-6 pt-2">
            <legend class="sr-only">Incident Location</legend>

            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <x-icons.map-pin class="w-4 h-4 text-emerald-600" />
                    <span>Incident Location</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Pre-filled from parent ticket. Adjust if the sub-task is localized to a different specific pole or spot.</p>
            </div>

            <!-- Row 3: Purok, Street, Barangay (3-column grid) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <x-form.label for="purok" :required="true">Purok</x-form.label>
                    <x-form.input name="purok" id="purok" :value="old('purok', $parentTicket->purok)" placeholder="e.g., Ipil-Ipil" required />
                </div>
                <div>
                    <x-form.label for="street" :required="true">Street</x-form.label>
                    <x-form.input name="street" id="street" :value="old('street', $parentTicket->street)" placeholder="e.g., National Highway" required />
                </div>
                <div>
                    <x-form.label for="barangay" :required="true">Barangay</x-form.label>
                    <x-form.input name="barangay" id="barangay" :value="old('barangay', $parentTicket->barangay)" placeholder="e.g., Tiniguiban" required />
                </div>
            </div>

            <!-- Row 4: Landmark -->
            <div>
                <x-form.label for="landmark">Landmark</x-form.label>
                <x-form.input name="landmark" id="landmark" :value="old('landmark', $parentTicket->landmark)" placeholder="e.g., Near Shell Station" />
            </div>
        </fieldset>

        <!-- Section 3: Child Task Specification & Department Routing -->
        <fieldset class="space-y-6 pt-2">
            <legend class="sr-only">Child Task Specification & Department</legend>

            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <x-icons.pencil class="w-4 h-4 text-emerald-600" />
                    <span>Child Task Details & Division Assignment</span>
                </h2>
            </div>

            <!-- Row 5: Task Description -->
            <div>
                <x-form.label for="complaint_description" :required="true">Child Task Description & Reason</x-form.label>
                <textarea name="complaint_description" id="complaint_description" rows="4" class="form-input w-full min-h-[110px]" placeholder="Specify why this child ticket is required (e.g. secondary defect, vegetation clearing, material requisition, required follow-up)..." required>{{ old('complaint_description') }}</textarea>
                <p class="mt-1 text-[11px] text-slate-500">Minimum 5 characters. Clarify the operational scope for the assigned team.</p>
            </div>

            <!-- Row 6: Assignee Department -->
            <div class="relative">
                <x-form.label for="department_id" :required="true">Assignee Department</x-form.label>
                <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600" required>
                    <option value="">Select target department</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', $parentTicket->department_id) == $dept->id ? 'selected' : '' }}>
                            {{ $dept->dept_name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Specifies which department receives and verifies completion of this sub-task.</p>
            </div>
        </fieldset>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-6 mt-4">
            <x-btn variant="secondary" href="{{ $cancelRoute }}" class="action-link">
                Cancel
            </x-btn>
            <x-btn variant="primary" type="submit">
                <x-icons.plus class="w-4 h-4" />
                <span>Create Child Ticket</span>
            </x-btn>
        </div>
    </form>
    </article>
</div>
@endsection
