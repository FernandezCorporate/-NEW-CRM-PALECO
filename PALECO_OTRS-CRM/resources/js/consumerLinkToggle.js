/**
 * Consumer Link & Multi-Criteria Search Integration Engine.
 * Supports:
 * - 4-option dropdown selector: "No consumer linked", "Search by Account Code", "Search by Last Name", "Search by Meter Serial Number".
 * - Dynamic input requirement according to selected search filter.
 * - Exact Account Code verification and separate multi-criteria Last Name / Meter Serial search.
 * - Puerto Princesa scope with overflow warning handling and status normalization.
 */

/**
 * Escapes HTML special characters so billing API, database, and server message values
 * render as plain text inside HTML templates instead of executable markup.
 */
const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const initConsumerLinkToggle = () => {
    const filterSelect = document.getElementById('consumer_search_filter');
    const linkConsumerInput = document.getElementById('link_consumer');
    const accountCodeInput = document.getElementById('account_code');
    const container = document.getElementById('crm_integration_container');
    const panelsContainer = document.getElementById('consumer_search_panels_container');
    const selectedCard = document.getElementById('selected_consumer_card');
    const unlinkBtn = document.getElementById('unlink_consumer_btn');

    // Panels
    const panelAccountCode = document.getElementById('panel_search_account_code');
    const panelLastname = document.getElementById('panel_search_lastname');
    const panelMeterSerial = document.getElementById('panel_search_meter_serial');

    // Inputs & action buttons
    const inputAccountCode = document.getElementById('input_account_code');
    const verifyBtn = document.getElementById('verify_consumer_btn');
    const accountCodeFeedback = document.getElementById('account_code_feedback');

    const searchLastnameInput = document.getElementById('search_lastname');
    const searchLastnameBtn = document.getElementById('search_lastname_btn');

    const searchMeterSerialInput = document.getElementById('search_meter_serial');
    const searchMeterBtn = document.getElementById('search_meter_btn');

    // Feedback & results
    const hasMoreWarning = document.getElementById('has_more_warning');
    const searchFeedback = document.getElementById('search_feedback');
    const searchResultsContainer = document.getElementById('search_results_container');
    const searchResultsCount = document.getElementById('search_results_count');
    const searchResultsList = document.getElementById('search_results_list');

    // Selected consumer card fields
    const selectedName = document.getElementById('selected_consumer_name');
    const selectedCode = document.getElementById('selected_consumer_code');
    const selectedNo = document.getElementById('selected_consumer_no');
    const selectedMeter = document.getElementById('selected_consumer_meter');
    const selectedAddress = document.getElementById('selected_consumer_address');
    const selectedStatusBadge = document.getElementById('selected_consumer_status_badge');

    // Hidden fallback data fields
    const hiddenName = document.getElementById('hidden_consumer_name');
    const hiddenNo = document.getElementById('hidden_consumer_no');
    const hiddenMeter = document.getElementById('hidden_consumer_meter');
    const hiddenAddress = document.getElementById('hidden_consumer_address');
    const hiddenStatus = document.getElementById('hidden_consumer_status');

    if (!filterSelect || !linkConsumerInput || !accountCodeInput || !container) {
        return;
    }

    const parentForm = container.closest('form');
    let activeConsumer = null;

    const formatAccountCode = (val) => {
        let clean = (val || '').trim().replace(/\s+/g, '-');
        if (/^\d{10}$/.test(clean)) {
            clean = clean.replace(/^(\d{2})(\d{4})(\d{4})$/, '$1-$2-$3');
        }
        return clean;
    };

    const notifyFormChanged = () => {
        if (parentForm) {
            parentForm.dispatchEvent(new CustomEvent('ticket-form:revalidate', { bubbles: true }));
            parentForm.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    // Helper: Normalize status badge classes
    const getStatusBadgeClasses = (status) => {
        const s = (status || '').toUpperCase();
        if (s === 'ACTIVE' || s === 'A') {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        if (s === 'DEACTIVATED' || s === 'D') {
            return 'bg-amber-50 text-amber-700 border-amber-200';
        }
        if (s === 'INACTIVE' || s === 'I') {
            return 'bg-slate-100 text-slate-700 border-slate-200';
        }
        return 'bg-zinc-100 text-zinc-700 border-zinc-200';
    };

    // Helper: Clear all feedback & result elements
    const clearFeedbackAndResults = () => {
        if (accountCodeFeedback) {
            accountCodeFeedback.classList.add('hidden');
            accountCodeFeedback.innerHTML = '';
        }
        if (searchFeedback) {
            searchFeedback.classList.add('hidden');
            searchFeedback.innerHTML = '';
        }
        if (hasMoreWarning) {
            hasMoreWarning.classList.add('hidden');
        }
        if (searchResultsContainer) {
            searchResultsContainer.classList.add('hidden');
        }
        if (searchResultsList) {
            searchResultsList.innerHTML = '';
        }
    };

    // Helper: Clear required attributes from all search inputs
    const clearInputRequirements = () => {
        if (inputAccountCode) inputAccountCode.required = false;
        if (searchLastnameInput) searchLastnameInput.required = false;
        if (searchMeterSerialInput) searchMeterSerialInput.required = false;
    };

    // Helper: Display selected consumer card
    const selectConsumer = (consumer) => {
        activeConsumer = consumer;
        accountCodeInput.value = consumer.acct_code;
        linkConsumerInput.value = '1';

        if (hiddenName) hiddenName.value = consumer.name || '';
        if (hiddenNo) hiddenNo.value = consumer.acct_no || '';
        if (hiddenMeter) hiddenMeter.value = consumer.meter_serial || '';
        if (hiddenAddress) hiddenAddress.value = consumer.address || '';
        if (hiddenStatus) hiddenStatus.value = consumer.status || '';

        if (inputAccountCode) inputAccountCode.value = consumer.acct_code;
        if (selectedName) selectedName.textContent = consumer.name;
        if (selectedCode) selectedCode.textContent = consumer.acct_code;
        if (selectedNo) selectedNo.textContent = consumer.acct_no || 'N/A';
        if (selectedMeter) selectedMeter.textContent = consumer.meter_serial || 'N/A';
        if (selectedAddress) selectedAddress.textContent = consumer.address || 'Address not specified';

        if (selectedStatusBadge) {
            selectedStatusBadge.textContent = consumer.status || 'Active';
            selectedStatusBadge.className = `inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border ${getStatusBadgeClasses(consumer.status)}`;
        }

        clearInputRequirements();
        clearFeedbackAndResults();

        if (panelsContainer) panelsContainer.classList.add('hidden');
        if (selectedCard) selectedCard.classList.remove('hidden');
        container.classList.remove('hidden');
        notifyFormChanged();
    };

    // Helper: Unlink / remove currently selected consumer
    const unlinkConsumer = () => {
        activeConsumer = null;
        accountCodeInput.value = '';
        if (inputAccountCode) inputAccountCode.value = '';

        if (hiddenName) hiddenName.value = '';
        if (hiddenNo) hiddenNo.value = '';
        if (hiddenMeter) hiddenMeter.value = '';
        if (hiddenAddress) hiddenAddress.value = '';
        if (hiddenStatus) hiddenStatus.value = '';

        if (selectedCard) selectedCard.classList.add('hidden');

        if (filterSelect.value === 'none') {
            linkConsumerInput.value = '0';
            container.classList.add('hidden');
            clearInputRequirements();
            clearFeedbackAndResults();
        } else {
            linkConsumerInput.value = '1';
            container.classList.remove('hidden');
            if (panelsContainer) panelsContainer.classList.remove('hidden');
            applyFilterMode(filterSelect.value);
        }
        notifyFormChanged();
    };

    // Helper: Apply active filter mode from dropdown
    const applyFilterMode = (mode) => {
        clearFeedbackAndResults();

        if (mode === 'none') {
            linkConsumerInput.value = '0';
            accountCodeInput.value = '';
            activeConsumer = null;

            clearInputRequirements();
            container.classList.add('hidden');
            if (selectedCard) selectedCard.classList.add('hidden');
            if (panelsContainer) panelsContainer.classList.add('hidden');
            notifyFormChanged();
            return;
        }

        linkConsumerInput.value = '1';
        container.classList.remove('hidden');

        // If a consumer is already attached, show the card and hide the input panels
        if (accountCodeInput.value && selectedCard && !selectedCard.classList.contains('hidden')) {
            clearInputRequirements();
            if (panelsContainer) panelsContainer.classList.add('hidden');
            notifyFormChanged();
            return;
        }

        // Otherwise, show panels container and switch to the selected panel
        if (selectedCard) selectedCard.classList.add('hidden');
        if (panelsContainer) panelsContainer.classList.remove('hidden');
        clearInputRequirements();

        if (mode === 'account_code') {
            if (panelAccountCode) panelAccountCode.classList.remove('hidden');
            if (panelLastname) panelLastname.classList.add('hidden');
            if (panelMeterSerial) panelMeterSerial.classList.add('hidden');

            const val = inputAccountCode ? formatAccountCode(inputAccountCode.value) : '';
            accountCodeInput.value = val;
            linkConsumerInput.value = val ? '1' : '0';

            if (inputAccountCode) {
                inputAccountCode.required = true;
                inputAccountCode.focus();
            }
        } else if (mode === 'lastname') {
            if (panelLastname) panelLastname.classList.remove('hidden');
            if (panelAccountCode) panelAccountCode.classList.add('hidden');
            if (panelMeterSerial) panelMeterSerial.classList.add('hidden');

            accountCodeInput.value = activeConsumer ? activeConsumer.acct_code : '';
            linkConsumerInput.value = activeConsumer ? '1' : '0';

            if (searchLastnameInput) {
                searchLastnameInput.required = true;
                searchLastnameInput.focus();
            }
        } else if (mode === 'meter_serial') {
            if (panelMeterSerial) panelMeterSerial.classList.remove('hidden');
            if (panelAccountCode) panelAccountCode.classList.add('hidden');
            if (panelLastname) panelLastname.classList.add('hidden');

            accountCodeInput.value = activeConsumer ? activeConsumer.acct_code : '';
            linkConsumerInput.value = activeConsumer ? '1' : '0';

            if (searchMeterSerialInput) {
                searchMeterSerialInput.required = true;
                searchMeterSerialInput.focus();
            }
        }

        notifyFormChanged();
    };

    // Feedback Helpers
    const showAccountCodeFeedback = (type, message) => {
        if (!accountCodeFeedback) return;
        accountCodeFeedback.className = 'text-xs p-2.5 rounded-lg border';
        accountCodeFeedback.classList.remove('hidden');

        if (type === 'loading') {
            accountCodeFeedback.classList.add('bg-slate-100', 'border-slate-200', 'text-slate-700');
            accountCodeFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-paleco-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        } else if (type === 'error') {
            accountCodeFeedback.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-700');
            accountCodeFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        } else {
            accountCodeFeedback.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
            accountCodeFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        }
    };

    const showSearchFeedback = (type, message) => {
        if (!searchFeedback) return;
        searchFeedback.className = 'mt-4 text-xs p-3 rounded-lg border';
        searchFeedback.classList.remove('hidden');

        if (type === 'loading') {
            searchFeedback.classList.add('bg-slate-100', 'border-slate-200', 'text-slate-700');
            searchFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-paleco-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        } else if (type === 'error') {
            searchFeedback.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-700');
            searchFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        } else {
            searchFeedback.classList.add('bg-amber-50', 'border-amber-200', 'text-amber-800');
            searchFeedback.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        }
    };

    // Render search results cards
    const renderSearchResults = (accounts) => {
        if (!searchResultsList || !searchResultsContainer) return;
        searchResultsList.innerHTML = '';

        if (!accounts || accounts.length === 0) {
            searchResultsContainer.classList.add('hidden');
            showSearchFeedback('info', 'No consumer accounts found matching your search in Puerto Princesa.');
            return;
        }

        if (searchResultsCount) {
            searchResultsCount.textContent = `Found ${accounts.length} matching account${accounts.length > 1 ? 's' : ''}:`;
        }

        accounts.forEach((account) => {
            const card = document.createElement('div');
            card.className = 'group p-3 rounded-lg border border-slate-200 bg-white hover:border-paleco-500 hover:bg-paleco-50/40 transition-all cursor-pointer flex items-start justify-between gap-3 shadow-2xs';
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');

            const badgeClasses = getStatusBadgeClasses(account.status);

            card.innerHTML = `
                <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${badgeClasses}">
                            ${escapeHtml(account.status || 'Active')}
                        </span>
                        <h5 class="text-xs font-bold text-slate-900 group-hover:text-paleco-800 truncate">${escapeHtml(account.name)}</h5>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-slate-500 font-mono">
                        <span>Code: <strong class="text-slate-800">${escapeHtml(account.acct_code)}</strong></span>
                        <span>Acct #: <strong class="text-slate-800">${escapeHtml(account.acct_no || 'N/A')}</strong></span>
                        <span>Meter: <strong class="text-slate-800">${escapeHtml(account.meter_serial || 'N/A')}</strong></span>
                    </div>
                    <p class="text-[11px] text-slate-500 truncate">${escapeHtml(account.address || '')}</p>
                </div>
                <div class="shrink-0 self-center">
                    <span class="btn btn-secondary text-xs px-2.5 py-1.5 group-hover:border-paleco-600 group-hover:text-paleco-700 inline-flex items-center gap-1">
                        <span>Select</span>
                        <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-paleco-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </span>
                </div>
            `;

            const onSelect = () => {
                selectConsumer(account);
            };

            card.addEventListener('click', onSelect);
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onSelect();
                }
            });

            searchResultsList.appendChild(card);
        });

        searchResultsContainer.classList.remove('hidden');
    };

    // --- VERIFICATION (Filter 1: Account Code) ---
    const runVerification = async () => {
        if (!inputAccountCode) return;
        let code = inputAccountCode.value.trim().replace(/\s+/g, '-');
        inputAccountCode.value = code;

        const formatRegex = /^\d{2}-\d{4}-\d{4}$/;
        if (!code) {
            showAccountCodeFeedback('error', 'Please enter an account code to verify.');
            inputAccountCode.focus();
            return;
        }

        if (!formatRegex.test(code)) {
            showAccountCodeFeedback('error', 'Invalid format. Please use standard XX-XXXX-XXXX notation.');
            inputAccountCode.focus();
            return;
        }

        if (verifyBtn) verifyBtn.disabled = true;
        const originalText = verifyBtn ? verifyBtn.innerHTML : '';
        if (verifyBtn) {
            verifyBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Verifying...</span>
            `;
        }

        showAccountCodeFeedback('loading', 'Connecting to PALECO billing service...');

        try {
            const response = await fetch(`/cwd/consumers/verify/${encodeURIComponent(code)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (response.ok && data.success && data.consumer) {
                showAccountCodeFeedback('success', 'Account verified successfully!');
                setTimeout(() => {
                    selectConsumer(data.consumer);
                }, 400);
            } else {
                const errorMsg = data.message || 'Consumer account not found in PALECO database.';
                showAccountCodeFeedback('error', errorMsg);
            }
        } catch (error) {
            showAccountCodeFeedback('error', 'Network error while contacting PALECO billing service.');
        } finally {
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalText;
            }
        }
    };

    // --- MULTI-CRITERIA SEARCH (Filter 2: Lastname & Filter 3: Meter Serial) ---
    const runSearch = async (params, triggerButton) => {
        clearFeedbackAndResults();

        if (triggerButton) triggerButton.disabled = true;
        const originalBtnHtml = triggerButton ? triggerButton.innerHTML : '';
        if (triggerButton) {
            triggerButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Searching...</span>
            `;
        }

        showSearchFeedback('loading', 'Querying PALECO Puerto Princesa database...');

        const queryParams = new URLSearchParams();
        if (params.lastname) queryParams.set('lastname', params.lastname);
        if (params.meter_serial) queryParams.set('meter_serial', params.meter_serial);

        try {
            const response = await fetch(`/cwd/consumers/search?${queryParams.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (response.ok && data.success) {
                if (searchFeedback) searchFeedback.classList.add('hidden');

                if (data.has_more && hasMoreWarning) {
                    hasMoreWarning.classList.remove('hidden');
                } else if (hasMoreWarning) {
                    hasMoreWarning.classList.add('hidden');
                }

                renderSearchResults(data.data || []);
            } else {
                const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Search failed.');
                showSearchFeedback('error', errorMsg);
            }
        } catch (error) {
            showSearchFeedback('error', 'Network or server error while querying PALECO accounts.');
        } finally {
            if (triggerButton) {
                triggerButton.disabled = false;
                triggerButton.innerHTML = originalBtnHtml;
            }
        }
    };

    // Search by Lastname handler
    const handleLastnameSearch = () => {
        if (!searchLastnameInput) return;
        const lastname = searchLastnameInput.value.trim();
        if (!lastname || lastname.length < 2) {
            showSearchFeedback('error', 'Please enter at least 2 characters for the consumer last name.');
            searchLastnameInput.focus();
            return;
        }
        runSearch({ lastname }, searchLastnameBtn);
    };

    // Search by Meter Serial handler
    const handleMeterSearch = () => {
        if (!searchMeterSerialInput) return;
        const meterSerial = searchMeterSerialInput.value.trim();
        if (!meterSerial || meterSerial.length < 2) {
            showSearchFeedback('error', 'Please enter at least 2 characters for the meter serial number.');
            searchMeterSerialInput.focus();
            return;
        }
        runSearch({ meter_serial: meterSerial }, searchMeterBtn);
    };

    // --- FORM SUBMIT GUARDRAIL ---
    if (parentForm) {
        parentForm.addEventListener('submit', (e) => {
            if (filterSelect.value !== 'none') {
                if (filterSelect.value === 'account_code') {
                    const code = inputAccountCode ? formatAccountCode(inputAccountCode.value) : '';
                    if (!code) {
                        e.preventDefault();
                        showAccountCodeFeedback('error', 'Please enter a PALECO account code.');
                        if (inputAccountCode) inputAccountCode.focus();
                    } else {
                        accountCodeInput.value = code;
                        linkConsumerInput.value = '1';
                    }
                } else if (!accountCodeInput.value) {
                    e.preventDefault();
                    showSearchFeedback('error', 'Please search and select a matching consumer account to attach.');
                    if (filterSelect.value === 'lastname' && searchLastnameInput) searchLastnameInput.focus();
                    if (filterSelect.value === 'meter_serial' && searchMeterSerialInput) searchMeterSerialInput.focus();
                }
            }
        });
    }

    // --- EVENT BINDINGS ---

    filterSelect.addEventListener('change', () => {
        applyFilterMode(filterSelect.value);
    });

    if (unlinkBtn) {
        unlinkBtn.addEventListener('click', unlinkConsumer);
    }

    // Account code events
    if (verifyBtn && !verifyBtn.dataset.bound) {
        verifyBtn.dataset.bound = 'true';
        verifyBtn.addEventListener('click', runVerification);
    }

    if (inputAccountCode) {
        inputAccountCode.addEventListener('input', () => {
            const val = formatAccountCode(inputAccountCode.value);
            accountCodeInput.value = val;
            linkConsumerInput.value = val ? '1' : '0';
            clearFeedbackAndResults();
            notifyFormChanged();
        });

        inputAccountCode.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                runVerification();
            }
        });
    }

    // Lastname search events
    if (searchLastnameBtn && !searchLastnameBtn.dataset.bound) {
        searchLastnameBtn.dataset.bound = 'true';
        searchLastnameBtn.addEventListener('click', handleLastnameSearch);
    }

    if (searchLastnameInput) {
        searchLastnameInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleLastnameSearch();
            }
        });
    }

    // Meter search events
    if (searchMeterBtn && !searchMeterBtn.dataset.bound) {
        searchMeterBtn.dataset.bound = 'true';
        searchMeterBtn.addEventListener('click', handleMeterSearch);
    }

    if (searchMeterSerialInput) {
        searchMeterSerialInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleMeterSearch();
            }
        });
    }

    // Initial state setup
    applyFilterMode(filterSelect.value);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConsumerLinkToggle);
} else {
    initConsumerLinkToggle();
}