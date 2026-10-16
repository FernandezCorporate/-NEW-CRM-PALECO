@php
    $currentConsumer = $ticket->consumer ?? null;
    $hasLinkedConsumer = (bool) old('link_consumer', $currentConsumer ? true : false);
    $initialAccountCode = old('account_code', $currentConsumer?->acct_code ?? '');
    $consumerSelected = !empty($initialAccountCode) && $hasLinkedConsumer;
    $initialFilter = old('consumer_search_filter', $consumerSelected ? 'account_code' : 'none');
@endphp

<!-- CRM Integration: Consumer Link -->
<fieldset class="col-span-1 lg:col-span-2 pt-6 mt-2 border-t border-slate-200">
    <div class="flex items-center justify-between mb-4">
        <div>
            <legend class="text-sm font-bold text-slate-900">CRM Integration</legend>
            <p class="text-xs text-slate-500 mt-0.5">Attach an official PALECO consumer profile to this incident ticket.</p>
        </div>
    </div>
    
    <!-- Consumer Linking Mode Dropdown Selector (Replaces checkbox and tabs) -->
    <div class="max-w-md mb-4">
        <label for="consumer_search_filter" class="block text-xs font-semibold text-slate-700 mb-1">
            Consumer Linking Filter
        </label>
        <div class="relative">
            <select id="consumer_search_filter" name="consumer_search_filter" class="form-input text-sm pr-9 cursor-pointer">
                <option value="none" {{ $initialFilter === 'none' ? 'selected' : '' }}>
                    No consumer linked
                </option>
                <option value="account_code" {{ $initialFilter === 'account_code' ? 'selected' : '' }}>
                    Search by Account Code
                </option>
                <option value="lastname" {{ $initialFilter === 'lastname' ? 'selected' : '' }}>
                    Search by Last Name
                </option>
                <option value="meter_serial" {{ $initialFilter === 'meter_serial' ? 'selected' : '' }}>
                    Search by Meter Serial Number
                </option>
            </select>
        </div>
        <p id="consumer_search_filter_hint" class="mt-1 text-[11px] text-slate-500">
            Choose a search filter to attach a PALECO consumer, or select "No consumer linked" to proceed without linking.
        </p>
    </div>

    <!-- Hidden Form Submission Inputs -->
    <input type="hidden" name="link_consumer" id="link_consumer" value="{{ $hasLinkedConsumer ? '1' : '0' }}">
    <input type="hidden" name="account_code" id="account_code" value="{{ $initialAccountCode }}">

    <!-- Interactive Container (Visible when a filter or account is active) -->
    <div id="crm_integration_container" class="{{ $initialFilter !== 'none' || $consumerSelected ? '' : 'hidden' }} mt-3 p-4 rounded-xl border border-slate-200 bg-slate-50/70 transition-all duration-200">
        
        <!-- Active Linked Consumer Card (Shown when an account is attached / verified / existing) -->
        <div id="selected_consumer_card" class="{{ $consumerSelected ? '' : 'hidden' }} mb-4 p-4 rounded-lg border-2 border-emerald-500 bg-emerald-50/80 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300">
                            Linked Account
                        </span>
                        <span id="selected_consumer_status_badge" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider {{ ($currentConsumer && $currentConsumer->status === 'Active') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (($currentConsumer && $currentConsumer->status === 'Deactivated') ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200') }}">
                            {{ $currentConsumer ? $currentConsumer->status : 'Active' }}
                        </span>
                    </div>
                    <h4 id="selected_consumer_name" class="text-base font-bold text-slate-900 leading-snug">
                        {{ $currentConsumer ? $currentConsumer->name : '' }}
                    </h4>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 font-mono">
                        <span>Acct Code: <strong id="selected_consumer_code" class="text-slate-900">{{ $currentConsumer ? $currentConsumer->acct_code : '' }}</strong></span>
                        <span>Acct #: <strong id="selected_consumer_no" class="text-slate-900">{{ $currentConsumer ? $currentConsumer->acct_no : '' }}</strong></span>
                        <span>Meter: <strong id="selected_consumer_meter" class="text-slate-900">{{ $currentConsumer ? ($currentConsumer->meter_serial ?: 'N/A') : 'N/A' }}</strong></span>
                    </div>
                    <p id="selected_consumer_address" class="text-xs text-slate-600 pt-1 leading-normal">
                        {{ $currentConsumer ? $currentConsumer->address : '' }}
                    </p>
                </div>
                <div class="shrink-0 flex sm:flex-col gap-2 pt-1 sm:pt-0">
                    <button type="button" id="unlink_consumer_btn" class="btn btn-secondary text-xs px-2.5 py-1.5 h-auto inline-flex items-center gap-1.5" title="Change or remove selected account">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        <span>Change</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Input Panels Container (Visible when searching for an account) -->
        <div id="consumer_search_panels_container" class="{{ $consumerSelected ? 'hidden' : '' }}">
            
            <!-- FILTER 1: By Account Code Panel -->
            <div id="panel_search_account_code" class="{{ $initialFilter === 'account_code' ? '' : 'hidden' }} space-y-3">
                <div class="max-w-md">
                    <label for="input_account_code" class="block text-xs font-semibold text-slate-700 mb-1">
                        PALECO Account Code <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="input_account_code" value="{{ $initialAccountCode }}" class="form-input text-sm font-mono" placeholder="e.g., 02-0504-8538" autocomplete="off">
                        <button type="button" id="verify_consumer_btn" class="btn btn-secondary shrink-0 text-xs px-3.5 py-2 inline-flex items-center gap-1.5">
                            <span>Verify Code</span>
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Format: <span class="font-mono">XX-XXXX-XXXX</span>. Fast, exact lookup directly from PALECO billing.</p>
                </div>
                <div id="account_code_feedback" class="hidden text-xs p-2.5 rounded-lg border"></div>
            </div>

            <!-- FILTER 2: Search by Last Name Panel -->
            <div id="panel_search_lastname" class="{{ $initialFilter === 'lastname' ? '' : 'hidden' }} space-y-4">
                <div class="max-w-md">
                    <label for="search_lastname" class="block text-xs font-semibold text-slate-700 mb-1">
                        Consumer Last Name / Prefix <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="search_lastname" class="form-input text-sm" placeholder="e.g., Dela Cruz or Dela Cruz,Juan" autocomplete="off">
                        <button type="button" id="search_lastname_btn" class="btn btn-primary shrink-0 text-xs px-3.5 py-2 inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <span>Find Accounts</span>
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Tip: Use <span class="font-semibold text-slate-600">Lastname,Firstname</span> (e.g. <span class="font-mono">Dela Cruz,Juan</span>) to filter further. Scope: Puerto Princesa City.</p>
                </div>
            </div>

            <!-- FILTER 3: Search by Meter Serial Panel -->
            <div id="panel_search_meter_serial" class="{{ $initialFilter === 'meter_serial' ? '' : 'hidden' }} space-y-4">
                <div class="max-w-md">
                    <label for="search_meter_serial" class="block text-xs font-semibold text-slate-700 mb-1">
                        Meter Serial Number <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="search_meter_serial" class="form-input text-sm font-mono" placeholder="e.g., 80305715" autocomplete="off">
                        <button type="button" id="search_meter_btn" class="btn btn-primary shrink-0 text-xs px-3.5 py-2 inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <span>Find Accounts</span>
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Enter meter serial number to locate the consumer in Puerto Princesa City.</p>
                </div>
            </div>

            <!-- Overflow Warning Banner: has_more = true -->
            <div id="has_more_warning" class="hidden mt-4 p-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 text-xs flex items-start gap-2.5">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div>
                    <strong class="font-semibold">More matching accounts exist beyond this list:</strong>
                    <p class="mt-0.5 text-amber-700">Displaying the first 30 matches. If you do not see the specific account, please enter a <strong>Meter Serial Number</strong> or <strong>Account Code</strong> to locate the exact record.</p>
                </div>
            </div>

            <!-- Search Feedback Message (Loading or Error or No Results) -->
            <div id="search_feedback" class="hidden mt-4 text-xs p-3 rounded-lg border"></div>

            <!-- Search Results Grid / List -->
            <div id="search_results_container" class="hidden mt-4 space-y-2">
                <div class="flex items-center justify-between text-xs text-slate-600 pb-1 border-b border-slate-200">
                    <span id="search_results_count" class="font-medium">Found matching accounts:</span>
                    <span class="text-[11px] text-slate-400">Click a consumer card to attach</span>
                </div>
                <div id="search_results_list" class="max-h-80 overflow-y-auto space-y-2 pr-1"></div>
            </div>

        </div>

    </div>
</fieldset>
