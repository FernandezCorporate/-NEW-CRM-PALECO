@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'Edit Ticket - ' . $ticket->ticket_number)

@section('content')
<article class="card max-w-5xl mx-auto my-6 p-6 sm:p-10">
    
    <header class="mb-8">
        <div class="flex items-center gap-3">
            <span class="font-mono text-xs font-bold bg-slate-100 text-slate-700 px-2.5 py-1 rounded border border-slate-200">
                {{ $ticket->ticket_number }}
            </span>
            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border {{ $ticket->status->badgeClasses() }}">
                {{ $ticket->status->label() }}
            </span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mt-2">Edit service ticket</h1>
        <p class="text-sm text-slate-500 mt-1">Correct clerical typos in contact information, location, complaint description, or category assignment.</p>
    </header>

    <x-alert />

    <form action="{{ route('cwd.tickets.update', $ticket) }}" method="POST" autocomplete="off" class="space-y-8">
        @csrf
        @method('PUT')

        @if(request()->filled('source') || old('source'))
            <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
        @endif
        @if(request()->filled('consumer') || old('consumer'))
            <input type="hidden" name="consumer" value="{{ old('consumer', request('consumer')) }}">
        @endif
        @if(request()->filled('parent') || old('parent'))
            <input type="hidden" name="parent" value="{{ old('parent', request('parent')) }}">
        @endif

        <!-- Section 1: Complaint Classification & Intake Channel -->
        <fieldset class="space-y-6">
            <legend class="sr-only">Complaint Classification & Intake Channel</legend>

            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <x-icons.ticket class="w-4 h-4 text-emerald-600" />
                    <span>Complaint Classification & Intake</span>
                </h2>
            </div>

            <!-- Row 1: Nature of Complaint & Custom Category Input -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div class="relative">
                    <div class="flex items-center justify-between mb-1.5">
                        <x-form.label id="category_id_label" for="category_id" :required="true">Nature of Complaint</x-form.label>
                        
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-slate-600 hover:text-emerald-700 select-none">
                            <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category', $ticket->other_category) ? 'checked' : '' }} class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Unlisted / Custom</span>
                        </label>
                    </div>
                    
                    <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600" {{ old('other_category', $ticket->other_category) ? '' : 'required' }}>
                        <option value="">Choose standard nature</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $ticket->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="mb-1.5">
                        <label id="other_category_name_label" for="other_category_name" class="form-label {{ old('other_category', $ticket->other_category) ? 'text-slate-700 font-bold' : 'text-slate-400' }}">
                            Custom Category Specification <span id="other_category_required_marker" class="text-rose-500 {{ old('other_category', $ticket->other_category) ? '' : 'hidden' }}">*</span>
                        </label>
                    </div>
                    <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name', $ticket->other_category_name) }}" {{ old('other_category', $ticket->other_category) ? 'required' : 'disabled' }} class="form-input disabled:bg-slate-50 disabled:text-slate-400 disabled:border-slate-200 transition-colors" placeholder="Enter manual issue specification if unlisted">
                </div>
            </div>

            <!-- Row 2: Intake Source & Consumer Contact -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="relative">
                    <x-form.label for="complaint_source" :required="true">Complaint Intake Source</x-form.label>
                    <select name="complaint_source" id="complaint_source" class="tom-select-sync w-full" data-autosubmit="false" autocomplete="off" placeholder="Select intake channel" required>
                        <option value=""></option>
                        @foreach($sources as $source)
                            <option value="{{ $source->value }}" {{ old('complaint_source', $ticket->complaint_source->value) === $source->value ? 'selected' : '' }}>
                                {{ $source->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-form.label for="consumer_contact" :required="true">Consumer Contact Number</x-form.label>
                    <x-form.input name="consumer_contact" id="consumer_contact" :value="old('consumer_contact', $ticket->consumer_contact)" placeholder="e.g., 09123456789" required pattern="^(09|\+639)\d{9}$" />
                </div>
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
                <p class="text-xs text-slate-500 mt-0.5">Specify the exact geographic site where field crews should respond in Puerto Princesa.</p>
            </div>

            <!-- Row 3: Purok, Street, Barangay (3-column grid) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <x-form.label for="purok" :required="true">Purok</x-form.label>
                    <x-form.input name="purok" id="purok" :value="old('purok', $ticket->purok)" placeholder="e.g., Ipil-Ipil" required />
                </div>
                <div>
                    <x-form.label for="street" :required="true">Street</x-form.label>
                    <x-form.input name="street" id="street" :value="old('street', $ticket->street)" placeholder="e.g., National Highway" required />
                </div>
                <div>
                    <x-form.label for="barangay" :required="true">Barangay</x-form.label>
                    <x-form.input name="barangay" id="barangay" :value="old('barangay', $ticket->barangay)" placeholder="e.g., Tiniguiban" required />
                </div>
            </div>

            <!-- Row 4: Landmark -->
            <div>
                <x-form.label for="landmark">Landmark Remarks</x-form.label>
                <x-form.input name="landmark" id="landmark" :value="old('landmark', $ticket->landmark)" placeholder="e.g., Near Shell Station, opposite barangay hall, beside yellow gate" />
                <p class="mt-1 text-[11px] text-slate-500">Optional vicinity clues to assist field linemen in quickly identifying the exact incident site.</p>
            </div>
        </fieldset>

        <!-- Section 3: Complaint Narrative & Routing -->
        <fieldset class="space-y-6 pt-2">
            <legend class="sr-only">Complaint Narrative & Routing</legend>

            <div class="border-b border-slate-200 pb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <x-icons.pencil class="w-4 h-4 text-emerald-600" />
                    <span>Complaint Narrative & Department Routing</span>
                </h2>
            </div>

            <!-- Row 5: Description (Textarea) -->
            <div>
                <x-form.label for="complaint_description" :required="true">Complaint Details & Description</x-form.label>
                <textarea name="complaint_description" id="complaint_description" rows="4" class="form-input w-full min-h-[110px]" placeholder="Provide comprehensive details regarding the outage, hazards, sparking transformer, affected premises, or utility complication..." required>{{ old('complaint_description', $ticket->complaint_description) }}</textarea>
                <p class="mt-1 text-[11px] text-slate-500">Minimum 5 characters. Record caller observations, physical damages, or specific instructions for the crew.</p>
            </div>

            <!-- Row 6: Department -->
            <div class="relative">
                <x-form.label for="department_id" :required="true">Responsible Department / Initial Routing</x-form.label>
                <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600" required>
                    <option value="">Select field division to route this ticket</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', $ticket->department_id) == $dept->id ? 'selected' : '' }}>
                            {{ $dept->dept_name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Assigns operational ownership for investigation and dispatch scheduling.</p>
            </div>
        </fieldset>

        <!-- Section 4: Consumer Link -->
        @include('cwd.forms.partials.consumerLinkSection')

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-6 mt-4">
            <x-contextual-back :default="route('cwd.tickets.show', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')]))" label="Cancel" variant="button" />
            <x-btn variant="primary" type="submit" data-loading-text="Updating Ticket...">
                Save Changes
            </x-btn>
        </div>
    </form>
</article>
@endsection

