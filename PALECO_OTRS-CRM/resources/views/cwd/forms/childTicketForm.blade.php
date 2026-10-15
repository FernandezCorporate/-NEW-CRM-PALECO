@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'Create Child Ticket - ' . $parentTicket->ticket_number)

@section('content')
<article class="card max-w-5xl mx-auto my-6 p-6 sm:p-10">
    
    <!-- Header -->
    <header class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('cwd.tickets.show', $parentTicket) }}" class="p-2 text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" title="Back to parent ticket">
                <x-icons.arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    Create Child Ticket
                    <x-badge variant="indigo">Sub-Task Order</x-badge>
                </h1>
                <p class="text-sm text-slate-500 mt-0.5">Spawn a subordinate service ticket linked to parent ticket <span class="font-bold text-slate-700">{{ $parentTicket->ticket_number }}</span>.</p>
            </div>
        </div>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
            
            <!-- Left Column: Classification & Department -->
            <fieldset class="space-y-6">
                <legend class="sr-only">Classification and Routing</legend>
                
                <div>
                    <x-form.label for="department_id" :required="true">Assignee Department</x-form.label>
                    <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-paleco-600 focus:ring-1 focus:ring-paleco-600">
                        <option value="">Select target department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $parentTicket->department_id) == $dept->id ? 'selected' : '' }}>
                                {{ $dept->dept_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-form.label for="consumer_contact">Consumer Contact</x-form.label>
                    <x-form.input name="consumer_contact" id="consumer_contact" :value="old('consumer_contact', $parentTicket->consumer_contact)" placeholder="e.g., 09123456789" />
                </div>

                <!-- Category Grouping -->
                <div class="space-y-5 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category', $parentTicket->other_category) ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-paleco-600 focus:ring-paleco-600">
                        <label for="other_category" class="ml-2 block text-sm font-medium text-slate-800 select-none cursor-pointer">
                            Request Unlisted / Custom Category
                        </label>
                    </div>

                    <div>
                        <label id="category_id_label" for="category_id" class="form-label mb-1.5">Ticket Category</label>
                        <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-paleco-600 focus:ring-1 focus:ring-paleco-600">
                            <option value="">Choose standard category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $parentTicket->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label id="other_category_name_label" for="other_category_name" class="form-label text-slate-400 mb-1.5">Custom Category Name</label>
                        <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name', $parentTicket->other_category_name) }}" disabled class="form-input disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200" placeholder="Enter manual category name">
                    </div>
                </div>
            </fieldset>

            <!-- Right Column: Location & Child Task Specification -->
            <fieldset class="space-y-6">
                <legend class="sr-only">Incident Location and Task Specification</legend>
                
                <div class="space-y-5">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-200 pb-2">Incident Location</h2>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-form.label for="purok" :required="true">Purok</x-form.label>
                            <x-form.input name="purok" id="purok" :value="old('purok', $parentTicket->purok)" placeholder="e.g., Ipil-Ipil" required />
                        </div>
                        <div>
                            <x-form.label for="street" :required="true">Street</x-form.label>
                            <x-form.input name="street" id="street" :value="old('street', $parentTicket->street)" placeholder="e.g., National Highway" required />
                        </div>
                    </div>

                    <div>
                        <x-form.label for="barangay" :required="true">Barangay</x-form.label>
                        <x-form.input name="barangay" id="barangay" :value="old('barangay', $parentTicket->barangay)" placeholder="e.g., Tiniguiban" />
                    </div>

                    <div>
                        <x-form.label for="landmark">Landmark</x-form.label>
                        <x-form.input name="landmark" id="landmark" :value="old('landmark', $parentTicket->landmark)" placeholder="e.g., Near Shell Station" />
                    </div>
                </div>

                <div class="pt-2">
                    <x-form.label for="complaint_description" :required="true">Child Task Description & Reason</x-form.label>
                    <textarea name="complaint_description" id="complaint_description" rows="5" class="form-input" placeholder="Specify why this child ticket is required (e.g. secondary defect, vegetation clearing, material requisition, required follow-up)...">{{ old('complaint_description') }}</textarea>
                </div>
            </fieldset>

        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-6 mt-4">
            <x-btn variant="secondary" href="{{ route('cwd.tickets.show', $parentTicket) }}" class="action-link">
                Cancel
            </x-btn>
            <x-btn variant="primary" type="submit">
                <x-icons.plus class="w-4 h-4" />
                <span>Create Child Ticket</span>
            </x-btn>
        </div>
    </form>
</article>
@endsection
