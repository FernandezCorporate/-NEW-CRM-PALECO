@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Endorsement Details')

@section('content')
    
    <div class="max-w-4xl mx-auto">
        
        <!-- Back Navigation -->
        <div class="mb-6">
            @php
                $isFromTicket = request('source') === 'ticket' && request('ticket');
                $backRoute = $isFromTicket ? route('cwd.tickets.show', ['ticket' => request('ticket')]) : session('endorsement_list_url', route('cwd.endorsements'));
                $backLabel = $isFromTicket ? 'Back to Ticket' : 'Back to Endorsements';
            @endphp
            <a href="{{ $backRoute }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-700 transition-colors">
                <x-icons.arrow-left class="w-4 h-4" />
                <span>{{ $backLabel }}</span>
            </a>
        </div>

        <!-- Validation & Session Prompts Component -->
        @include('cwd.prompts.alert')

        <!-- Main Document Card -->
        <article class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden mb-8" aria-labelledby="endorsement-title">
            
            <!-- 1. Header Section -->
            <header class="px-6 py-6 border-b border-slate-200">
                <div class="flex items-center gap-4 mb-2">
                    <!-- Endorse Icon -->
                    <div class="text-amber-500">
                        <x-icons.endorsement class="w-7 h-7" />
                    </div>
                    <h1 id="endorsement-title" class="text-2xl font-bold text-slate-900">Endorsement Review</h1>
                    
                    <!-- Dynamic Status Badge -->
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border {{ $endorsement->status->badgeClasses() }} text-[11px] font-bold tracking-wide">
                        {{ $endorsement->status->label() }}
                    </span>
                </div>
                
                <!-- Ticket Context Subtitle -->
                <div class="text-sm text-slate-600 ml-11">
                    @if($endorsement->ticket)
                        <a href="{{ route('cwd.tickets.show', ['ticket' => $endorsement->ticket, 'source' => 'endorsement', 'endorsement' => $endorsement->id]) }}" class="text-emerald-700 hover:underline font-bold">
                            Ticket {{ $endorsement->ticket->ticket_number }}
                        </a>
                        &mdash; {{ $endorsement->ticket->subject ?? 'No Subject Provided' }}
                    @else
                        Ticket N/A &mdash; No Subject Provided
                    @endif
                </div>
            </header>

            <!-- 2. Information Grid Section (Semantic Definition List) -->
            <div class="px-6 py-6 border-b border-slate-100">
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-y-6 gap-x-8 ml-11">
                    
                    <!-- Supervisor -->
                    <div>
                        <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Supervisor</dt>
                        <dd class="flex items-center gap-2 text-sm text-slate-900 font-medium">
                            <x-icons.user class="w-4 h-4 text-slate-400 shrink-0" />
                            <span>{{ $endorsement->creator->full_name ?? 'Unknown Supervisor' }}</span>
                        </dd>
                    </div>

                    <!-- Area -->
                    <div>
                        <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Incident Area</dt>
                        <dd class="flex items-center gap-2 text-sm text-slate-900 font-medium">
                            <x-icons.map-pin class="w-4 h-4 text-slate-400 shrink-0" />
                            <span>{{ implode(', ', array_filter([$endorsement->ticket->purok, $endorsement->ticket->street, $endorsement->ticket->barangay])) ?: 'Not specified' }}</span>
                        </dd>
                    </div>

                    <!-- Target Department -->
                    <div>
                        <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Target Department</dt>
                        <dd class="flex items-center gap-2 text-sm text-slate-900 font-medium">
                            <x-icons.department class="w-4 h-4 text-slate-400 shrink-0" />
                            <span>{{ $endorsement->suggestedDepartment?->dept_name ?? 'Unassigned' }}</span>
                        </dd>
                    </div>

                </dl>
            </div>

            <!-- 3. Justification Section -->
            <section class="px-6 py-6" aria-labelledby="justification-heading">
                <div class="ml-11">
                    <h2 id="justification-heading" class="text-sm font-bold text-slate-900 mb-3">Supervisor's Justification</h2>
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 leading-relaxed shadow-2xs">
                        {{ $endorsement->reason ?? 'No detailed justification provided.' }}
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">
                        Submitted {{ $endorsement->created_at ? $endorsement->created_at->format('Y-m-d H:i') : 'N/A' }}
                    </div>
                </div>
            </section>

            <!-- 4. Action / Form Section -->
            @if($endorsement->status->value === 'pending')
                <section class="px-6 py-8 border-t border-slate-200 bg-white" aria-labelledby="decision-heading">
                    <div class="ml-11">
                        <h2 id="decision-heading" class="text-sm font-bold text-slate-900 mb-4">Dispatcher Decision</h2>

                        <form method="POST">
                            @csrf

                            <div class="space-y-5">
                                <!-- Target Department Selection (Updated to Tom Select) -->
                                <div>
                                    <label for="department_id" class="block text-sm font-medium text-slate-700 mb-1">Select Target Department <span class="text-xs text-emerald-600 font-normal ml-1">(Required for Approval)</span></label>
                                    
                                    <select name="department_id" id="department_id" class="tom-select-sync w-full border-slate-300 rounded-md shadow-2xs focus:border-emerald-600 focus:ring-emerald-500/20 text-sm @error('department_id') border-red-500 ring-red-500 @enderror" data-autosubmit="false" autocomplete="off" placeholder="Select an official target department...">
                                        <option value=""></option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id', $endorsement->suggested_department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->dept_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- CWD Note -->
                                <div>
                                    <label for="rejection_reason" class="block text-sm font-medium text-slate-700 mb-1">CWD Note / Remarks <span class="text-xs text-rose-600 font-normal ml-1">(Required for Rejection)</span></label>
                                    <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-input w-full min-h-[96px] border border-slate-300 rounded-lg p-3 text-sm text-slate-800 placeholder:text-slate-400 bg-white hover:border-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 shadow-xs transition-all outline-none @error('rejection_reason') !border-rose-500 !ring-2 !ring-rose-500/20 @enderror" placeholder="Provide resolution notes, coordination instructions, or reason for denial...">{{ old('rejection_reason') }}</textarea>
                                </div>

                                <!-- Action Buttons (Zero-JS Route Parameter Bypass) -->
                                <div class="flex flex-col sm:flex-row gap-4 pt-2">
                                    <button type="submit" 
                                            formmethod="POST"
                                            formaction="{{ route('cwd.endorsements.decide', ['endorsement' => $endorsement, 'status' => $rejectValue->value]) }}"
                                            class="btn btn-secondary text-rose-700 hover:bg-rose-50 hover:border-rose-300 border-rose-200 flex-1 py-3 text-sm font-bold shadow-xs">
                                        <x-icons.x-mark class="w-5 h-5 mr-1 text-rose-600" />
                                        Reject Endorsement
                                    </button>
                                    
                                    <button type="submit" 
                                            formmethod="POST"
                                            formaction="{{ route('cwd.endorsements.decide', ['endorsement' => $endorsement, 'status' => $approveValue->value]) }}"
                                            class="btn btn-primary flex-1 py-3 text-sm font-bold shadow-xs">
                                        <x-icons.check-circle class="w-5 h-5 mr-1" />
                                        Approve & Dispatch
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            @else
                <!-- Read-Only Processed State -->
                <section class="px-6 py-8 border-t border-slate-200 bg-slate-50" aria-labelledby="decision-record-heading">
                    <div class="ml-11">
                        <h2 id="decision-record-heading" class="text-sm font-bold text-slate-900 mb-3">Decision Record</h2>
                        <p class="text-sm text-slate-600">
                            This endorsement was marked as <strong class="text-slate-800">{{ ucfirst($endorsement->status->value) }}</strong> 
                            by <span class="font-medium text-slate-900">{{ $endorsement->reviewer->full_name ?? 'System' }}</span> 
                            on {{ $endorsement->reviewed_at ? $endorsement->reviewed_at->format('F j, Y - H:i') : 'an unknown date' }}.
                        </p>
                        
                        @if($endorsement->rejection_reason)
                            <div class="mt-4 p-4 bg-white border border-slate-200 rounded-md text-sm text-slate-800 leading-relaxed shadow-2xs">
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">CWD Note</span>
                                {{ $endorsement->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </article>
    </div>

@endsection