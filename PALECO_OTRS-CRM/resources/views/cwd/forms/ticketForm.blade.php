@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'New Ticket')

@section('content')
<div class="max-w-5xl mx-auto my-6 p-6 sm:p-10 bg-white rounded-lg border border-gray-200">
    
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">New service ticket</h1>
        <p class="text-sm text-gray-500 mt-1">Record the complaint, choose the responsible department, and confirm the incident location.</p>
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
                    
                    <!-- Applied Custom Tom Select Pattern -->
                    <select name="complaint_source" id="complaint_source" class="tom-select-sync" data-autosubmit="false" autocomplete="off" placeholder="Select intake channel">
                        
                        <option value=""></option>
                        
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
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-200 pb-2">Incident location</h3>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

            <!-- CRM Integration: Consumer Link -->
            <div class="col-span-1 lg:col-span-2 pt-6 mt-2 border-t border-gray-200">
                <h3 class="text-sm font-bold text-gray-900 mb-4">CRM Integration</h3>
                
                <div class="flex items-start mb-4">
                    <div class="flex h-5 items-center">
                        <input id="link_consumer" name="link_consumer" type="checkbox" value="1" {{ old('link_consumer') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#008f5d] focus:ring-[#008f5d]">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="link_consumer" class="font-semibold text-gray-700 select-none cursor-pointer">Link to an existing consumer account</label>
                        <p class="text-gray-500">Enable this to query the cooperative's billing system and attach the consumer's official profile to this ticket.</p>
                    </div>
                </div>

                <div id="account_code_container" class="{{ old('link_consumer') ? 'block' : 'hidden' }} max-w-md ml-7 mt-3">
                    <label for="account_code" class="block text-sm font-semibold text-gray-700 mb-1.5">Account Code <span class="text-red-500">*</span></label>
                    <input type="text" name="account_code" id="account_code" value="{{ old('account_code') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., 02-0504-8538">
                    <p class="mt-1.5 text-xs text-gray-500">The system will verify this exact code against the external database before registering the ticket.</p>
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