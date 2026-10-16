@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'New Ticket')

@section('content')
<article class="card max-w-5xl mx-auto my-6 p-6 sm:p-10">
    
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">New service ticket</h1>
        <p class="text-sm text-slate-500 mt-1">Record the complaint, choose the responsible department, and confirm the incident location.</p>
    </header>

    <x-alert />

    <form action="{{ route('cwd.tickets.store') }}" method="POST" autocomplete="off" class="space-y-8">
        @csrf
        @if(request()->filled('source') || old('source'))
            <input type="hidden" name="source" value="{{ old('source', request('source')) }}">
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
            
            <!-- Left Column: Classification -->
            <fieldset class="space-y-6">
                <legend class="sr-only">Classification and Nature</legend>
                
                <div>
                    <x-form.label for="complaint_source" :required="true">Complaint Intake Source</x-form.label>
                    <select name="complaint_source" id="complaint_source" class="tom-select-sync" data-autosubmit="false" autocomplete="off" placeholder="Select intake channel">
                        <option value=""></option>
                        @foreach($sources as $source)
                            <option value="{{ $source->value }}" {{ old('complaint_source') === $source->value ? 'selected' : '' }}>
                                {{ $source->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-form.label for="consumer_contact" :required="true">Consumer Contact</x-form.label>
                    <x-form.input name="consumer_contact" id="consumer_contact" :value="old('consumer_contact')" placeholder="e.g., 09123456789" />
                </div>

                <!-- Category Grouping -->
                <div class="space-y-5 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category') ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-paleco-600 focus:ring-paleco-600">
                        <label for="other_category" class="ml-2 block text-sm font-medium text-slate-800 select-none cursor-pointer">
                            Request Unlisted / Custom Category
                        </label>
                    </div>

                    <div>
                        <label id="category_id_label" for="category_id" class="form-label mb-1.5">Established Ticket Category</label>
                        <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-paleco-600 focus:ring-1 focus:ring-paleco-600">
                            <option value="">Choose standard nature</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label id="other_category_name_label" for="other_category_name" class="form-label text-slate-400 mb-1.5">Custom Category Name</label>
                        <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name') }}" disabled class="form-input disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200" placeholder="Enter manual issue specification">
                    </div>
                </div>

                <div class="pt-2">
                    <x-form.label for="department_id" :required="true">Initial Departmental Routing</x-form.label>
                    <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-900 focus:border-paleco-600 focus:ring-1 focus:ring-paleco-600">
                        <option value="">Select field division</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->dept_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </fieldset>

            <!-- Right Column: Location & Details -->
            <fieldset class="space-y-6">
                <legend class="sr-only">Incident Location and Details</legend>
                
                <div class="space-y-5">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-200 pb-2">Incident location</h2>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-form.label for="purok" :required="true">Purok</x-form.label>
                            <x-form.input name="purok" id="purok" :value="old('purok')" placeholder="e.g., Ipil-Ipil" required />
                        </div>
                        <div>
                            <x-form.label for="street" :required="true">Street</x-form.label>
                            <x-form.input name="street" id="street" :value="old('street')" placeholder="e.g., National Highway" required />
                        </div>
                    </div>

                    <div>
                        <x-form.label for="barangay" :required="true">Barangay</x-form.label>
                        <x-form.input name="barangay" id="barangay" :value="old('barangay')" placeholder="e.g., Tiniguiban" />
                    </div>

                    <div>
                        <x-form.label for="landmark">Landmark Remarks</x-form.label>
                        <x-form.input name="landmark" id="landmark" :value="old('landmark')" placeholder="e.g., Near Shell Station" />
                    </div>
                </div>

                <div class="pt-2">
                    <x-form.label for="complaint_description" :required="true">Complaint Details & Description</x-form.label>
                    <textarea name="complaint_description" id="complaint_description" rows="5" class="form-input" placeholder="Provide details regarding the outage or utility complication...">{{ old('complaint_description') }}</textarea>
                </div>
            </fieldset>

            <!-- CRM Integration: Consumer Link -->
            @include('cwd.forms.partials.consumerLinkSection')

        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-slate-200 pt-6 mt-4">
            <x-btn variant="secondary" href="{{ old('source', request('source')) === 'dashboard' ? route('cwd.dashboard') : route('cwd.tickets') }}" data-loading-text="Canceling...">
                Cancel
            </x-btn>
            <x-btn variant="primary" type="submit" data-loading-text="Routing Ticket...">
                Register Ticket
            </x-btn>
        </div>
    </form>
</article>
@endsection