@extends('cwd.base.base')

@section('title', 'Escalation Details')

@section('content')
    
    <div class="max-w-4xl mx-auto">
        
        <!-- Back Navigation -->
        <div class="mb-6">
            <a href="{{ route('cwd.escalations') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-[#008f5d] transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Escalations
            </a>
        </div>

        <!-- Validation & Session Prompts Component -->
        @include('cwd.prompts.alert')

        <!-- Main Document Card -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-8">
            
            <!-- 1. Header Section -->
            <div class="px-6 py-6 border-b border-gray-200">
                <div class="flex items-center gap-4 mb-2">
                    <!-- Escalate Icon -->
                    <div class="text-amber-500">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Escalate</h1>
                    
                    <!-- Dynamic Status Badge -->
                    @php
                        $statusColor = match($escalation->status->value ?? 'pending') {
                            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default => 'bg-gray-100 text-gray-600 border-gray-200'
                        };
                        $statusLabel = method_exists($escalation->status, 'label') 
                            ? $escalation->status->label() 
                            : ucfirst($escalation->status->value ?? 'Pending');
                    @endphp
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded border {{ $statusColor }} text-[11px] font-bold tracking-wide">
                        {{ $statusLabel }}
                    </span>
                </div>
                
                <!-- Ticket Context Subtitle -->
                <div class="text-sm text-gray-600 ml-11">
                    Ticket {{ $escalation->ticket->ticket_number ?? 'N/A' }} &mdash; {{ $escalation->ticket->subject ?? 'No Subject Provided' }} 
                </div>
            </div>

            <!-- 2. Information Grid Section -->
            <div class="px-6 py-6 border-b border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-y-6 gap-x-8 ml-11">
                    
                    <!-- Foreman -->
                    <div>
                        <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Foreman</h3>
                        <div class="flex items-center gap-2 text-sm text-gray-900">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            {{ $escalation->creator->full_name ?? 'Unknown Foreman' }}
                        </div>
                    </div>

                    <!-- Area -->
                    <div>
                        <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Area</h3>
                        <div class="flex items-center gap-2 text-sm text-gray-900">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            {{ implode(', ', array_filter([$escalation->ticket->purok, $escalation->ticket->street, $escalation->ticket->barangay])) ?: 'Not specified' }}
                        </div>
                    </div>

                    <!-- Target Department -->
                    <div>
                        <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Target Department</h3>
                        <div class="flex items-center gap-2 text-sm text-gray-900">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            {{ $escalation->suggestedDepartment?->dept_name ?? 'Unassigned' }}
                        </div>
                    </div>

                </div>
            </div>

            <!-- 3. Justification Section -->
            <div class="px-6 py-6">
                <div class="ml-11">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">Foreman's Justification</h3>
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-800 leading-relaxed shadow-inner">
                        {{ $escalation->reason ?? 'No detailed justification provided.' }}
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400">
                        Submitted {{ $escalation->created_at ? $escalation->created_at->format('Y-m-d H:i') : 'N/A' }}
                    </div>
                </div>
            </div>

            <!-- 4. Action / Form Section -->
            @if($escalation->status->value === 'pending')
                <div class="px-6 py-8 border-t border-gray-200 bg-white">
                    <div class="ml-11">
                        <h3 class="text-sm font-bold text-gray-900 mb-4">Dispatcher Decision</h3>

                        <form method="POST">
                            @csrf

                            <div class="space-y-5">
                                <!-- Target Department Selection (Updated to Tom Select) -->
                                <div>
                                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Select Target Department <span class="text-xs text-emerald-600 font-normal ml-1">(Required for Approval)</span></label>
                                    
                                    <select name="department_id" id="department_id" class="tom-select-sync w-full border-gray-300 rounded-md shadow-sm focus:border-[#008f5d] focus:ring-[#008f5d] text-sm @error('department_id') border-red-500 ring-red-500 @enderror" data-autosubmit="false" autocomplete="off" placeholder="Select an official target department...">
                                        <!-- Empty option for native placeholder -->
                                        <option value=""></option>
                                        
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id', $escalation->suggested_department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->dept_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- CWD Note -->
                                <div>
                                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-1">CWD Note / Remarks <span class="text-xs text-rose-600 font-normal ml-1">(Required for Rejection)</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:border-[#008f5d] focus:ring-[#008f5d] text-sm @error('rejection_reason') border-red-500 ring-red-500 @enderror" placeholder="Provide resolution notes, coordination instructions, or reason for denial...">{{ old('rejection_reason') }}</textarea>
                                </div>

                                <!-- Action Buttons (Zero-JS Route Parameter Bypass) -->
                                <div class="flex gap-4 pt-2">
                                    <button type="submit" 
                                            formmethod="POST"
                                            formaction="{{ route('cwd.escalations.decide', ['escalation' => $escalation, 'status' => $rejectValue->value]) }}"
                                            class="flex-1 inline-flex justify-center items-center px-4 py-3 border border-rose-300 text-sm font-bold rounded-md text-rose-700 bg-white hover:bg-rose-50 hover:border-rose-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-colors shadow-sm">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Reject Escalation
                                    </button>
                                    
                                    <button type="submit" 
                                            formmethod="POST"
                                            formaction="{{ route('cwd.escalations.decide', ['escalation' => $escalation, 'status' => $approveValue->value]) }}"
                                            class="flex-1 inline-flex justify-center items-center px-4 py-3 border border-transparent text-sm font-bold rounded-md text-white bg-[#008f5d] hover:bg-[#007049] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#008f5d] transition-colors shadow-sm">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Approve & Dispatch
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <!-- Read-Only Processed State -->
                <div class="px-6 py-8 border-t border-gray-200 bg-gray-50">
                    <div class="ml-11">
                        <h3 class="text-sm font-bold text-gray-900 mb-3">Decision Record</h3>
                        <p class="text-sm text-gray-600">
                            This escalation was marked as <strong>{{ ucfirst($escalation->status->value) }}</strong> 
                            by <span class="font-medium text-gray-900">{{ $escalation->reviewer->full_name ?? 'System' }}</span> 
                            on {{ $escalation->reviewed_at ? $escalation->reviewed_at->format('F j, Y - H:i') : 'an unknown date' }}.
                        </p>
                        
                        @if($escalation->rejection_reason)
                            <div class="mt-4 p-4 bg-white border border-gray-200 rounded-md text-sm text-gray-800 leading-relaxed shadow-sm">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">CWD Note</span>
                                {{ $escalation->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection