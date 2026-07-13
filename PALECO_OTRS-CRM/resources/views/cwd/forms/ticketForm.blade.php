@extends('cwd.base.base')

@section('content')
<div class="max-w-5xl mx-auto my-6 p-6 sm:p-10 bg-white rounded-lg border border-gray-200">
    
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Create New Service Complaint Ticket</h1>
        <p class="text-sm text-gray-500 mt-1">Consumer Welfare Desk Processing Module</p>
    </div>

    <!-- Reused Shared System Prompts Alerts -->
    @include('cwd.prompts.alert')

    <!-- Data Ingestion Form Execution -->
    <form action="{{ route('cwd.tickets.store') }}" method="POST" autocomplete="off" class="space-y-8">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
            
            <!-- Left Column: Classification -->
            <div class="space-y-6">
                
                <div>
                    <label for="complaint_source" class="block text-sm font-semibold text-gray-700 mb-1.5">Complaint Intake Source <span class="text-red-500">*</span></label>
                    <select name="complaint_source" id="complaint_source" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors">
                        <option value="" class="text-gray-400">Select intake channel</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->value }}" {{ old('complaint_source') === $source->value ? 'selected' : '' }}>
                                {{ $source->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Grouping -->
                <div class="space-y-5 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#008f5d] focus:ring-[#008f5d]">
                        <label for="other_category" class="ml-2 block text-sm font-medium text-gray-800 select-none">
                            Request Unlisted / Custom Category
                        </label>
                    </div>

                    <div>
                        <label id="category_id_label" for="category_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Established Ticket Category</label>
                        <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
                            <option value="">Choose standard nature</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label id="other_category_name_label" for="other_category_name" class="block text-sm font-semibold text-gray-400 mb-1.5">Custom Category Name</label>
                        <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name') }}" disabled class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none disabled:bg-gray-50 disabled:text-gray-500 disabled:border-gray-200 transition-colors" placeholder="Enter manual issue specification">
                    </div>
                </div>

                <div class="pt-2">
                    <label for="department_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Initial Departmental Routing <span class="text-red-500">*</span></label>
                    <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
                        <option value="">Select field division</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->dept_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>

            <!-- Right Column: Location & Details -->
            <div class="space-y-6">
                
                <div class="space-y-5">
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-200 pb-2">Geographical Incident Location</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="purok" class="block text-xs font-semibold text-gray-600 mb-1.5">Purok</label>
                            <input type="text" name="purok" id="purok" value="{{ old('purok') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Ipil-Ipil">
                        </div>
                        <div>
                            <label for="street" class="block text-xs font-semibold text-gray-600 mb-1.5">Street</label>
                            <input type="text" name="street" id="street" value="{{ old('street') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., National Highway">
                        </div>
                    </div>

                    <div>
                        <label for="barangay" class="block text-sm font-semibold text-gray-700 mb-1.5">Barangay <span class="text-red-500">*</span></label>
                        <input type="text" name="barangay" id="barangay" value="{{ old('barangay') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Tiniguiban">
                    </div>

                    <div>
                        <label for="landmark" class="block text-sm font-semibold text-gray-700 mb-1.5">Landmark Remarks</label>
                        <input type="text" name="landmark" id="landmark" value="{{ old('landmark') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Near Shell Station">
                    </div>
                </div>

                <div class="pt-2">
                    <label for="complaint_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Complaint Details & Description <span class="text-red-500">*</span></label>
                    <textarea name="complaint_description" id="complaint_description" rows="5" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="Provide details regarding the outage or utility complication..."></textarea>
                </div>

            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6 mt-4">
            <a href="{{ route('cwd.tickets') }}" class="action-link px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors" data-loading-text="Canceling...">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-[#008f5d] rounded-md hover:bg-[#007a4f] shadow-sm transition-colors" data-loading-text="Routing Ticket...">
                Register Ticket
            </button>
        </div>
    </form>
</div>
@endsection