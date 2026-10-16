/**
 * Universal Form Submit Lock Engine
 * Enforces client-side submit button locking across all web forms
 * until all validation requirements defined in their FormRequests are satisfied.
 */

const setButtonState = (button, isEnabled) => {
    if (!button) return;
    button.disabled = !isEnabled;
    if (isEnabled) {
        button.classList.remove('is-disabled', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
        button.removeAttribute('aria-disabled');
    } else {
        button.classList.add('is-disabled', 'opacity-50', 'cursor-not-allowed', 'pointer-events-none');
        button.setAttribute('aria-disabled', 'true');
    }
};

const isElementCompliant = (el) => {
    // If element is disabled or inside a disabled fieldset, it is exempt from validation
    if (el.disabled || el.closest('fieldset:disabled')) {
        return true;
    }

    // Required check
    if (el.hasAttribute('required')) {
        if (el.type === 'checkbox') {
            if (!el.checked) return false;
        } else if (el.type === 'radio') {
            const name = el.name;
            const checkedRadio = el.form?.querySelector(`input[type="radio"][name="${name}"]:checked`);
            if (!checkedRadio) return false;
        } else {
            const val = el.value !== undefined && el.value !== null ? String(el.value).trim() : '';
            if (val === '') return false;
        }
    }

    // Minlength check (if element has a value)
    const minLength = el.getAttribute('minlength');
    if (minLength && el.value) {
        if (el.value.length < parseInt(minLength, 10)) {
            return false;
        }
    }

    // Pattern constraint (if element has a value)
    const pattern = el.getAttribute('pattern');
    if (pattern && el.value) {
        try {
            const regex = new RegExp(`^(${pattern})$`);
            if (!regex.test(el.value.trim())) {
                return false;
            }
        } catch (e) {
            if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                return false;
            }
        }
    }

    // Native browser validity check (for email, time, number, etc.)
    if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
        return false;
    }

    return true;
};

const checkFormCompliance = (form) => {
    // 1. Required elements check
    const requiredControls = form.querySelectorAll('input[required], select[required], textarea[required]');
    for (const el of requiredControls) {
        if (!isElementCompliant(el)) {
            return false;
        }
    }

    // 2. Non-required elements with values (for pattern/minlength/email format checks)
    const nonRequiredControls = form.querySelectorAll('input:not([required]), textarea:not([required])');
    for (const el of nonRequiredControls) {
        if (el.value && !isElementCompliant(el)) {
            return false;
        }
    }

    // 3. User Form: Password confirmation matching
    const passwordInput = form.querySelector('#login-password');
    const confirmPasswordInput = form.querySelector('#confirm-password');
    if (passwordInput && confirmPasswordInput && passwordInput.hasAttribute('required')) {
        if (passwordInput.value !== confirmPasswordInput.value) {
            return false;
        }
    }

    // 4. Team Form: Shift times difference (different:shift_start)
    const shiftStart = form.querySelector('#shift_start');
    const shiftEnd = form.querySelector('#shift_end');
    if (shiftStart && shiftEnd && shiftStart.value && shiftEnd.value) {
        if (shiftStart.value === shiftEnd.value) {
            return false;
        }
    }

    // 5. Ticket Category Toggle: Standard vs Custom category
    const otherCat = form.querySelector('#other_category');
    if (otherCat && otherCat.checked) {
        const otherCatName = form.querySelector('#other_category_name');
        if (!otherCatName || !otherCatName.value.trim()) {
            return false;
        }
    }

    // 6. Consumer Link Dropdown Filter (Ticket forms)
    const filterSelect = form.querySelector('#consumer_search_filter');
    if (filterSelect) {
        const mode = filterSelect.value;
        if (mode === 'account_code') {
            const inputAcct = form.querySelector('#input_account_code');
            const hiddenAcct = form.querySelector('#account_code');
            const code = ((inputAcct ? inputAcct.value : '') || (hiddenAcct ? hiddenAcct.value : '')).trim().replace(/\s+/g, '-');
            const acctRegex = /^\d{2}-\d{4}-\d{4}$/;
            if (!acctRegex.test(code)) {
                return false;
            }
        } else if (mode === 'lastname' || mode === 'meter_serial') {
            const hiddenAcct = form.querySelector('#account_code');
            if (!hiddenAcct || !hiddenAcct.value.trim()) {
                return false;
            }
        }
    }

    // 7. Complaint description minimum length (min:5 in backend)
    const complaintDesc = form.querySelector('[name="complaint_description"]');
    if (complaintDesc && complaintDesc.hasAttribute('required')) {
        if (complaintDesc.value.trim().length < 5) {
            return false;
        }
    }

    // 8. Cancellation Reason minimum length (min:3 in CancelTicketRequest)
    const cancelReason = form.querySelector('[name="cancellation_reason"]');
    if (cancelReason && cancelReason.hasAttribute('required')) {
        if (cancelReason.value.trim().length < 3) {
            return false;
        }
    }

    // 9. Closure Reason minimum length (min:3 in ForceCloseTicketRequest)
    const closureReason = form.querySelector('[name="closure_reason"]');
    if (closureReason && closureReason.hasAttribute('required')) {
        if (closureReason.value.trim().length < 3) {
            return false;
        }
    }

    // 10. Remarks body minimum length (min:2 in StoreTicketRemarkRequest)
    const remarkBody = form.querySelector('textarea[name="body"]');
    if (remarkBody && remarkBody.hasAttribute('required')) {
        if (remarkBody.value.trim().length < 2) {
            return false;
        }
    }

    return true;
};

const initEndorsementDecisionForm = (form) => {
    const rejectBtn = form.querySelector('#endorsement-reject-btn');
    const approveBtn = form.querySelector('#endorsement-approve-btn');
    const deptSelect = form.querySelector('#department_id');
    const rejectionReason = form.querySelector('#rejection_reason');

    const updateButtons = () => {
        // Rejection requires non-empty remarks
        const hasRejection = Boolean(rejectionReason && rejectionReason.value.trim().length > 0);
        setButtonState(rejectBtn, hasRejection);

        // Approval requires selected department
        const hasDept = Boolean(deptSelect && deptSelect.value.trim().length > 0);
        setButtonState(approveBtn, hasDept);
    };

    form.addEventListener('input', updateButtons);
    form.addEventListener('change', updateButtons);

    if (deptSelect) {
        deptSelect.addEventListener('change', updateButtons);
        if (deptSelect.tomselect) {
            deptSelect.tomselect.on('change', updateButtons);
        } else {
            deptSelect.addEventListener('tomselect:initialized', (e) => {
                if (e.detail) e.detail.on('change', updateButtons);
            });
        }
    }

    updateButtons();
};

const initFormSubmitLock = () => {
    const forms = document.querySelectorAll('form');
    if (!forms.length) return;

    forms.forEach((form) => {
        // Exclude GET query forms (search/filter toolbars)
        if (form.method && form.method.toUpperCase() === 'GET') return;
        // Exclude settings preferences form
        if (form.hasAttribute('data-preferences-form')) return;
        // Exclude explicit opt-out
        if (form.hasAttribute('data-no-lock')) return;
        // Exclude authentication forms
        if (form.action.includes('/login') || form.action.includes('attemptLogin')) return;

        if (form.dataset.formLockBound) return;
        form.dataset.formLockBound = 'true';

        // Custom dual-button endorsement form
        if (form.hasAttribute('data-endorsement-form') || form.id === 'endorsement-decision-form') {
            initEndorsementDecisionForm(form);
            return;
        }

        const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        if (!submitBtns.length) return;

        const updateSubmitState = () => {
            const compliant = checkFormCompliance(form);
            submitBtns.forEach((btn) => setButtonState(btn, compliant));
        };

        // Listen for input, change, and custom revalidation events
        form.addEventListener('input', updateSubmitState);
        form.addEventListener('change', updateSubmitState);
        form.addEventListener('ticket-form:revalidate', updateSubmitState);
        form.addEventListener('form:revalidate', updateSubmitState);

        // Hook into all selects (and dynamic TomSelect bindings)
        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', updateSubmitState);
            if (select.tomselect) {
                select.tomselect.on('change', updateSubmitState);
            }
        });

        // Event delegation for any TomSelect initialized dynamically inside this form
        form.addEventListener('tomselect:initialized', (e) => {
            if (e.detail) {
                e.detail.on('change', updateSubmitState);
            }
            updateSubmitState();
        });

        // Initial validation run
        updateSubmitState();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormSubmitLock);
} else {
    initFormSubmitLock();
}

export default initFormSubmitLock;

