@extends('cwd.base.base')

@section('workspace-kind', 'form')
@section('title', 'Create Child Ticket - ' . $parentTicket->ticket_number)

@section('content')
<div class="max-w-5xl mx-auto my-6 p-6 sm:p-10 bg-white rounded-lg border border-gray-200">
    
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('cwd.tickets.show', $parentTicket) }}" class="p-2 text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors" title="Back to parent ticket">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    Create Child Ticket
                    <span class="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2.5 py-0.5 rounded uppercase tracking-wide">Sub-Task Order</span>
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Spawn a subordinate service ticket linked to parent ticket <span class="font-bold text-gray-700">{{ $parentTicket->ticket_number }}</span>.</p>
            </div>
        </div>
    </div>

    <!-- Parent Ticket Reference Card -->
    <div class="mb-8 p-5 bg-slate-50 border border-slate-200 rounded-xl">
        <div class="flex items-center justify-between mb-3 border-b border-slate-200 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Parent Ticket Reference</span>
                <span class="text-sm font-bold text-slate-900 font-mono">{{ $parentTicket->ticket_number }}</span>
            </div>
            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
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
    </div>

    @include('cwd.prompts.alert')

    <form action="{{ route('cwd.tickets.children.store', $parentTicket) }}" method="POST" autocomplete="off" class="space-y-8">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8">
            
            <!-- Left Column: Classification & Department -->
            <div class="space-y-6">
                
                <div>
                    <label for="department_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Assignee Department <span class="text-red-500">*</span></label>
                    <select name="department_id" id="department_id" class="tom-select-sync w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
                        <option value="">Select target department</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id', $parentTicket->department_id) == $dept->id ? 'selected' : '' }}>
                                {{ $dept->dept_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="consumer_contact" class="block text-sm font-semibold text-gray-700 mb-1.5">Consumer Contact</label>
                    <input type="text" name="consumer_contact" id="consumer_contact" value="{{ old('consumer_contact', $parentTicket->consumer_contact) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., 09123456789">
                </div>

                <!-- Category Grouping -->
                <div class="space-y-5 pt-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="other_category" id="other_category" value="1" {{ old('other_category', $parentTicket->other_category) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-[#008f5d] focus:ring-[#008f5d]">
                        <label for="other_category" class="ml-2 block text-sm font-medium text-gray-800 select-none">
                            Request Unlisted / Custom Category
                        </label>
                    </div>

                    <div>
                        <label id="category_id_label" for="category_id" class="block text-sm font-semibold text-gray-700 mb-1.5">Ticket Category</label>
                        <select name="category_id" id="category_id" class="tom-select-sync w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d]">
                            <option value="">Choose standard category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $parentTicket->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->category_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label id="other_category_name_label" for="other_category_name" class="block text-sm font-semibold text-gray-400 mb-1.5">Custom Category Name</label>
                        <input type="text" name="other_category_name" id="other_category_name" value="{{ old('other_category_name', $parentTicket->other_category_name) }}" disabled class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none disabled:bg-gray-50 disabled:text-gray-500 disabled:border-gray-200 transition-colors" placeholder="Enter manual category name">
                    </div>
                </div>

            </div>

            <!-- Right Column: Location & Child Task Specification -->
            <div class="space-y-6">
                
                <div class="space-y-5">
                    <h3 class="text-sm font-bold text-gray-900 border-b border-gray-200 pb-2">Incident Location</h3>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="purok" class="block text-xs font-semibold text-gray-600 mb-1.5">Purok</label>
                            <input type="text" name="purok" id="purok" value="{{ old('purok', $parentTicket->purok) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Ipil-Ipil">
                        </div>
                        <div>
                            <label for="street" class="block text-xs font-semibold text-gray-600 mb-1.5">Street</label>
                            <input type="text" name="street" id="street" value="{{ old('street', $parentTicket->street) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., National Highway">
                        </div>
                    </div>

                    <div>
                        <label for="barangay" class="block text-sm font-semibold text-gray-700 mb-1.5">Barangay <span class="text-red-500">*</span></label>
                        <input type="text" name="barangay" id="barangay" value="{{ old('barangay', $parentTicket->barangay) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Tiniguiban">
                    </div>

                    <div>
                        <label for="landmark" class="block text-sm font-semibold text-gray-700 mb-1.5">Landmark</label>
                        <input type="text" name="landmark" id="landmark" value="{{ old('landmark', $parentTicket->landmark) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="e.g., Near Shell Station">
                    </div>
                </div>

                <div class="pt-2">
                    <label for="complaint_description" class="block text-sm font-semibold text-gray-700 mb-1.5">Child Task Description & Reason <span class="text-red-500">*</span></label>
                    <textarea name="complaint_description" id="complaint_description" rows="5" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] outline-none transition-colors" placeholder="Specify why this child ticket is required (e.g. secondary defect, vegetation clearing, material requisition, required follow-up)...">{{ old('complaint_description') }}</textarea>
                </div>

            </div>

        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-3 border-t border-gray-200 pt-6 mt-4">
            <a href="{{ route('cwd.tickets.show', $parentTicket) }}" class="action-link px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md shadow-sm transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create Child Ticket
            </button>
        </div>
    </form>
</div>
@endsection
