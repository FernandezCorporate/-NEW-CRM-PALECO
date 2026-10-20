@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Endorsement Details')

@section('content')
    
    <div class="max-w-4xl mx-auto">
        
        <!-- Back Navigation -->
        <div class="mb-6">
            <x-contextual-back :default="session('endorsement_list_url', route('cwd.endorsements'))" default-label="Back to Endorsements" variant="link" />
        </div>

        <!-- Validation & Session Prompts Component -->
        @include('cwd.prompts.alert')

        <!-- Main Document Card -->
        <article class="bg-white border border-slate-200 rounded-xl shadow-xs mb-8" aria-labelledby="endorsement-title">
            
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
                    <div class="mt-2.5 flex items-center gap-1.5 text-xs text-slate-500">
                        <x-icons.clock class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        @if($endorsement->created_at)
                            <time datetime="{{ $endorsement->created_at->toIso8601String() }}">
                                Submitted on {{ $endorsement->created_at->format('M d, Y \a\t h:i A') }}
                            </time>
                        @else
                            <span>Submitted: N/A</span>
                        @endif
                    </div>
                </div>
            </section>

            <!-- 4. Action / Form Section -->
            @if($endorsement->status->value === 'pending')
                @php
                    $initialStatus = old('status', $approveValue->value);
                    $isApprovedSelected = ($initialStatus === $approveValue->value);
                @endphp
                <section class="px-6 py-8 border-t border-slate-200 bg-white" aria-labelledby="decision-heading">
                    <div class="ml-11">
                        <h2 id="decision-heading" class="text-sm font-bold text-slate-900 mb-4">Dispatcher Decision</h2>

                        <form method="POST" action="{{ route('cwd.endorsements.decide', $endorsement) }}" data-endorsement-form id="endorsement-decision-form">
                            @csrf

                            <!-- Hidden Status Input -->
                            <input type="hidden" name="status" id="endorsement-status-input" value="{{ $initialStatus }}">

                            <div class="space-y-5">
                                <!-- Decision Action Choice Buttons -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2.5">
                                        Action Choice
                                    </label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Decision Action Choice">
                                        <!-- Reject Choice Button -->
                                        <button type="button" 
                                                id="decision-btn-reject"
                                                role="radio"
                                                aria-checked="{{ ! $isApprovedSelected ? 'true' : 'false' }}"
                                                class="flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer {{ ! $isApprovedSelected ? 'border-rose-600 bg-rose-50/70 text-rose-800 ring-2 ring-rose-500/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300' }}">
                                            <span id="reject-choice-icon" class="{{ ! $isApprovedSelected ? 'text-rose-600' : 'text-slate-400' }}">
                                                <x-icons.x-mark class="w-5 h-5" />
                                            </span>
                                            <span>Reject</span>
                                        </button>

                                        <!-- Approve Choice Button -->
                                        <button type="button" 
                                                id="decision-btn-approve"
                                                role="radio"
                                                aria-checked="{{ $isApprovedSelected ? 'true' : 'false' }}"
                                                class="flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer {{ $isApprovedSelected ? 'border-emerald-600 bg-emerald-50/70 text-emerald-800 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300' }}">
                                            <span id="approve-choice-icon" class="{{ $isApprovedSelected ? 'text-emerald-600' : 'text-slate-400' }}">
                                                <x-icons.check-circle class="w-5 h-5" />
                                            </span>
                                            <span>Approve</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Lower Dynamic Section -->

                                <!-- Target Department Selection (Shown when Approved) -->
                                <div id="department-field-container" class="{{ $isApprovedSelected ? '' : 'hidden' }}">
                                    <label for="department_id" class="block text-sm font-medium text-slate-700 mb-1">
                                        Select Target Department <span class="text-xs text-emerald-600 font-normal ml-1">(Required for Approval)</span>
                                    </label>
                                    
                                    <select name="department_id" id="department_id" class="tom-select-sync w-full border-slate-300 rounded-md shadow-2xs focus:border-emerald-600 focus:ring-emerald-500/20 text-sm @error('department_id') border-red-500 ring-red-500 @enderror" data-autosubmit="false" autocomplete="off" placeholder="Select an official target department..." {{ $isApprovedSelected ? '' : 'disabled' }}>
                                        <option value=""></option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id', $endorsement->suggested_department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->dept_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id')
                                        <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- CWD Note / Reason (Shown when Rejected) -->
                                <div id="reason-field-container" class="{{ ! $isApprovedSelected ? '' : 'hidden' }}">
                                    <label for="rejection_reason" class="block text-sm font-medium text-slate-700 mb-1">
                                        Reason for Rejection <span class="text-xs text-rose-600 font-normal ml-1">(Required for Rejection)</span>
                                    </label>
                                    <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-input w-full min-h-[96px] border border-slate-300 rounded-lg p-3 text-sm text-slate-800 placeholder:text-slate-400 bg-white hover:border-slate-400 focus:border-rose-600 focus:ring-2 focus:ring-rose-500/20 shadow-xs transition-all outline-none @error('rejection_reason') !border-rose-500 !ring-2 !ring-rose-500/20 @enderror" placeholder="Provide resolution notes, coordination instructions, or reason for denial..." {{ ! $isApprovedSelected ? 'required' : 'disabled' }}>{{ old('rejection_reason') }}</textarea>
                                    @error('rejection_reason')
                                        <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Submit Button (Below the Dynamic Lower Section) -->
                                <div class="pt-2">
                                    <button type="submit" 
                                            id="endorsement-submit-btn"
                                            class="btn {{ $isApprovedSelected ? 'btn-primary' : 'btn-danger' }} w-full py-3 text-sm font-bold shadow-xs flex items-center justify-center gap-2">
                                        <span id="submit-icon-check" class="{{ $isApprovedSelected ? '' : 'hidden' }}">
                                            <x-icons.check-circle class="w-5 h-5 text-white" />
                                        </span>
                                        <span id="submit-icon-reject" class="{{ ! $isApprovedSelected ? '' : 'hidden' }}">
                                            <x-icons.x-mark class="w-5 h-5 text-white" />
                                        </span>
                                        <span id="submit-btn-label">
                                            {{ $isApprovedSelected ? 'Approve & Dispatch' : 'Reject Endorsement' }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            @else
                <!-- Read-Only Processed State -->
                <section class="px-6 py-8 border-t border-slate-200 bg-slate-50 rounded-b-xl" aria-labelledby="decision-record-heading">
                    <div class="ml-11">
                        <h2 id="decision-record-heading" class="text-sm font-bold text-slate-900 mb-3">Decision Record</h2>
                        <p class="text-sm text-slate-600">
                            This endorsement was marked as <strong class="text-slate-800">{{ ucfirst($endorsement->status->value) }}</strong> 
                            by <span class="font-medium text-slate-900">{{ $endorsement->reviewer->full_name ?? 'System' }}</span> 
                            on {{ $endorsement->reviewed_at ? $endorsement->reviewed_at->format('M d, Y \a\t h:i A') : 'an unknown date' }}.
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

    @if($endorsement->status->value === 'pending')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const btnApprove = document.getElementById('decision-btn-approve');
                const btnReject = document.getElementById('decision-btn-reject');
                const statusInput = document.getElementById('endorsement-status-input');
                const deptContainer = document.getElementById('department-field-container');
                const reasonContainer = document.getElementById('reason-field-container');
                const deptSelect = document.getElementById('department_id');
                const reasonTextarea = document.getElementById('rejection_reason');
                const submitBtn = document.getElementById('endorsement-submit-btn');
                const submitIconCheck = document.getElementById('submit-icon-check');
                const submitIconReject = document.getElementById('submit-icon-reject');
                const submitBtnLabel = document.getElementById('submit-btn-label');
                const approveIcon = document.getElementById('approve-choice-icon');
                const rejectIcon = document.getElementById('reject-choice-icon');
                const form = document.getElementById('endorsement-decision-form');

                if (!btnApprove || !btnReject || !statusInput) return;

                const approveVal = @json($approveValue->value);
                const rejectVal = @json($rejectValue->value);

                function setDecision(mode) {
                    if (mode === 'approved') {
                        statusInput.value = approveVal;

                        btnApprove.setAttribute('aria-checked', 'true');
                        btnApprove.className = 'flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer border-emerald-600 bg-emerald-50/70 text-emerald-800 ring-2 ring-emerald-500/20';
                        if (approveIcon) approveIcon.className = 'text-emerald-600';

                        btnReject.setAttribute('aria-checked', 'false');
                        btnReject.className = 'flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300';
                        if (rejectIcon) rejectIcon.className = 'text-slate-400';

                        if (deptContainer) deptContainer.classList.remove('hidden');
                        if (reasonContainer) reasonContainer.classList.add('hidden');

                        if (deptSelect) {
                            deptSelect.disabled = false;
                            if (deptSelect.tomselect) {
                                deptSelect.tomselect.enable();
                            }
                        }

                        if (reasonTextarea) {
                            reasonTextarea.disabled = true;
                            reasonTextarea.required = false;
                        }

                        if (submitBtn) {
                            submitBtn.classList.remove('btn-danger');
                            submitBtn.classList.add('btn-primary');
                        }
                        if (submitIconCheck) submitIconCheck.classList.remove('hidden');
                        if (submitIconReject) submitIconReject.classList.add('hidden');
                        if (submitBtnLabel) submitBtnLabel.textContent = 'Approve & Dispatch';

                    } else if (mode === 'rejected') {
                        statusInput.value = rejectVal;

                        btnReject.setAttribute('aria-checked', 'true');
                        btnReject.className = 'flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer border-rose-600 bg-rose-50/70 text-rose-800 ring-2 ring-rose-500/20';
                        if (rejectIcon) rejectIcon.className = 'text-rose-600';

                        btnApprove.setAttribute('aria-checked', 'false');
                        btnApprove.className = 'flex items-center justify-center gap-2.5 p-3.5 rounded-lg border-2 font-semibold text-sm transition-all shadow-2xs cursor-pointer border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:border-slate-300';
                        if (approveIcon) approveIcon.className = 'text-slate-400';

                        if (deptContainer) deptContainer.classList.add('hidden');
                        if (reasonContainer) reasonContainer.classList.remove('hidden');

                        if (deptSelect) {
                            deptSelect.disabled = true;
                            if (deptSelect.tomselect) {
                                deptSelect.tomselect.disable();
                            }
                        }

                        if (reasonTextarea) {
                            reasonTextarea.disabled = false;
                            reasonTextarea.required = true;
                            reasonTextarea.focus();
                        }

                        if (submitBtn) {
                            submitBtn.classList.remove('btn-primary');
                            submitBtn.classList.add('btn-danger');
                        }
                        if (submitIconCheck) submitIconCheck.classList.add('hidden');
                        if (submitIconReject) submitIconReject.classList.remove('hidden');
                        if (submitBtnLabel) submitBtnLabel.textContent = 'Reject Endorsement';
                    }
                }

                btnApprove.addEventListener('click', function () {
                    setDecision('approved');
                });

                btnReject.addEventListener('click', function () {
                    setDecision('rejected');
                });

                if (form) {
                    form.addEventListener('submit', function (e) {
                        if (statusInput.value === approveVal && (!deptSelect || !deptSelect.value)) {
                            e.preventDefault();
                            if (deptSelect && deptSelect.tomselect) {
                                deptSelect.tomselect.focus();
                                deptSelect.tomselect.open();
                            } else if (deptSelect) {
                                deptSelect.focus();
                            }
                            return false;
                        }
                    });
                }
            });
        </script>
    @endif
@endsection