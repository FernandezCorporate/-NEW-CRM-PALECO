/**
 * Live Form Validation (Laravel Precognition)
 * Validates forms marked with data-live-validation against their real FormRequest
 * without submitting them, then shows each error message under its own field.
 *
 * - On blur / change, only the fields the user has touched are validated.
 * - On submit, every field is validated first; the form is only sent when it passes.
 * - Errors come from the FormRequest rules and messages(), so the browser and the
 *   server always show the same wording.
 */

const ERROR_FIELD_CLASS = '!border-rose-400';
const DEBOUNCE_MS = 400;

// "members.0.user_id" -> "members[0][user_id]"
const keyToName = (key) => {
    const [head, ...rest] = key.split('.');
    return head + rest.map((part) => `[${part}]`).join('');
};

// "members[0][user_id]" -> "members.0.user_id"
const nameToKey = (name) => name.replace(/\]\[/g, '.').replace(/\[/g, '.').replace(/\]/g, '');

const errorId = (key) => `error-${key.replace(/\./g, '-')}`;

const findField = (form, key) => form.querySelector(`[name="${CSS.escape(keyToName(key))}"]`);

// The element the user actually sees: Tom Select hides the real <select>
const visibleControl = (field) => field?.tomselect?.control ?? field;
const placementAnchor = (field) => {
    if (field?.tomselect) return field.tomselect.wrapper;

    // Inputs with an overlaid icon (e.g. the password eye button) sit in a positioned
    // wrapper; the message goes after that wrapper so the icon keeps its place
    const parent = field?.parentElement;
    const hasOverlay = parent && getComputedStyle(parent).position !== 'static'
        && [...parent.children].some((child) => child !== field && getComputedStyle(child).position === 'absolute');

    return hasOverlay ? parent : field;
};

const getSummaryBox = (form) => {
    let box = form.querySelector('[data-live-error-summary]');
    if (!box) {
        box = document.createElement('div');
        box.dataset.liveErrorSummary = 'true';
        box.setAttribute('role', 'alert');
        box.className = 'mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg text-sm text-rose-800 hidden';
        form.prepend(box);
    }
    return box;
};

const clearFieldError = (form, key) => {
    form.querySelector(`#${CSS.escape(errorId(key))}`)?.remove();
    // Server-rendered x-form.error messages use the plain field name as their id
    form.querySelector(`#error-${CSS.escape(key)}`)?.remove();

    const field = findField(form, key);
    if (!field) return;
    visibleControl(field).classList.remove(ERROR_FIELD_CLASS);
    field.removeAttribute('aria-invalid');
    field.removeAttribute('aria-describedby');
};

const showFieldError = (form, key, message) => {
    const field = findField(form, key);
    if (!field) return false;

    const messageEl = document.createElement('p');
    messageEl.id = errorId(key);
    messageEl.className = 'form-error';
    messageEl.setAttribute('role', 'alert');
    messageEl.textContent = message;

    if (field.type === 'hidden') {
        // Hidden inputs (e.g. the linked consumer's account code) show their message
        // in the visible block that controls them
        const holder = form.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
        if (!holder) return false;
        holder.append(messageEl);
        return true;
    }

    placementAnchor(field).insertAdjacentElement('afterend', messageEl);
    visibleControl(field).classList.add(ERROR_FIELD_CLASS);
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', messageEl.id);
    return true;
};

/**
 * Replaces the messages of the given fields with the new errors.
 * Errors that have no matching field on the page are listed at the top of the form.
 */
const renderErrors = (form, checkedKeys, errors) => {
    checkedKeys.forEach((key) => clearFieldError(form, key));
    Object.keys(errors).forEach((key) => clearFieldError(form, key));

    const unplaced = [];
    Object.entries(errors).forEach(([key, messages]) => {
        if (!showFieldError(form, key, messages[0])) unplaced.push(messages[0]);
    });

    const box = getSummaryBox(form);
    box.innerHTML = '';
    if (unplaced.length) {
        const list = document.createElement('ul');
        list.className = 'list-disc list-inside space-y-0.5';
        unplaced.forEach((text) => {
            const item = document.createElement('li');
            item.textContent = text;
            list.append(item);
        });
        box.append(list);
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
    }
};

const buildFormData = (form, state, submitter) => {
    const data = new FormData(form, submitter ?? undefined);

    // While the user has not reached a "confirm" field yet, mirror the original value
    // so rules like `confirmed` do not complain about a field they have not typed
    if (!submitter) {
        form.querySelectorAll('[name$="_confirmation"]').forEach((confirmField) => {
            const baseName = confirmField.name.replace(/_confirmation$/, '');
            if (!state.touched.has(nameToKey(confirmField.name)) && !confirmField.value) {
                data.set(confirmField.name, data.get(baseName) ?? '');
            }
        });
    }

    return data;
};

/**
 * Sends a precognitive request: Laravel runs the route's FormRequest and answers
 * 204 (valid) or 422 (errors) without running the controller.
 */
const precognitiveCheck = async (form, state, { only = null, submitter = null } = {}) => {
    state.controller?.abort();
    state.controller = new AbortController();

    const headers = {
        Precognition: 'true',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (only) headers['Precognition-Validate-Only'] = only.join(',');

    const response = await fetch(form.action, {
        method: 'POST', // PUT/PATCH forms carry their real verb in the _method field
        headers,
        body: buildFormData(form, state, submitter),
        credentials: 'same-origin',
        signal: state.controller.signal,
    });

    if (response.status === 204) return { ok: true, errors: {} };
    if (response.status === 422) {
        const body = await response.json();
        return { ok: false, errors: body.errors ?? {} };
    }

    // Expired session, missing permission, server error: let the normal submit handle it
    return { ok: null, errors: {} };
};

const validateTouched = async (form, state) => {
    if (!state.touched.size) return;
    const keys = [...state.touched];

    try {
        const result = await precognitiveCheck(form, state, { only: keys });
        if (result.ok === null) return;
        renderErrors(form, keys, result.errors);
    } catch (error) {
        if (error.name !== 'AbortError') console.warn('Live validation unavailable:', error);
    }
};

const setSubmitterBusy = (button, busy) => {
    if (!button) return;
    button.disabled = busy;
    button.toggleAttribute('aria-busy', busy);
    button.classList.toggle('opacity-75', busy);
};

const initForm = (form) => {
    if (form.dataset.liveValidationBound) return;
    form.dataset.liveValidationBound = 'true';

    // The server decides what is valid; the browser's own bubbles would block the
    // submit before the full check could list every problem
    form.noValidate = true;

    const state = { touched: new Set(), controller: null, timer: null, passed: false };

    const schedule = (delay) => {
        clearTimeout(state.timer);
        state.timer = setTimeout(() => validateTouched(form, state), delay);
    };

    const touch = (target) => {
        if (!target.name || target.type === 'hidden' || target.name === '_token') return false;
        state.touched.add(nameToKey(target.name));
        return true;
    };

    form.addEventListener('focusout', (event) => {
        const wrapper = event.target.closest('.ts-wrapper');
        if (wrapper) {
            // Leaving a Tom Select box: check the hidden <select> it stands for
            if (wrapper.contains(event.relatedTarget)) return;
            const select = wrapper.previousElementSibling; // Tom Select places its box right after the <select>
            if (select?.tagName === 'SELECT' && touch(select)) schedule(0);
            return;
        }
        if (touch(event.target)) schedule(0);
    });

    form.addEventListener('change', (event) => {
        if (touch(event.target)) schedule(0);
    });

    // A field that already shows an error is re-checked while the user corrects it
    form.addEventListener('input', (event) => {
        if (event.target.getAttribute('aria-invalid') === 'true') schedule(DEBOUNCE_MS);
    });

    // Scripts that change hidden values (e.g. linking a consumer) ask for a re-check
    form.addEventListener('form:revalidate', () => schedule(DEBOUNCE_MS));
    form.addEventListener('ticket-form:revalidate', () => schedule(DEBOUNCE_MS));

    form.addEventListener('submit', async (event) => {
        if (state.passed) {
            state.passed = false;
            return; // Already checked: let the browser send it
        }

        event.preventDefault();
        clearTimeout(state.timer);

        const submitter = event.submitter ?? form.querySelector('button[type="submit"], input[type="submit"]');
        setSubmitterBusy(submitter, true);

        let result;
        try {
            result = await precognitiveCheck(form, state, { submitter });
        } catch (error) {
            if (error.name === 'AbortError') {
                setSubmitterBusy(submitter, false);
                return;
            }
            result = { ok: null, errors: {} };
        }

        setSubmitterBusy(submitter, false);

        if (result.ok === false) {
            Object.keys(result.errors).forEach((key) => state.touched.add(key));
            renderErrors(form, [...state.touched], result.errors);

            const firstKey = Object.keys(result.errors)[0];
            const firstField = firstKey ? findField(form, firstKey) : null;
            const target = firstField?.type === 'hidden'
                ? form.querySelector(`[data-error-for="${CSS.escape(firstKey)}"]`)
                : placementAnchor(firstField);
            (target ?? getSummaryBox(form)).scrollIntoView({ behavior: 'smooth', block: 'center' });
            (firstField?.tomselect ?? (firstField?.type === 'hidden' ? null : firstField))?.focus?.();
            return;
        }

        // Valid, or the check could not run: submit normally and let the server respond
        if (result.ok) renderErrors(form, [...state.touched], {});
        state.passed = true;
        if (submitter && submitter.form === form) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    });
};

const initLiveValidation = () => {
    document.querySelectorAll('form[data-live-validation]').forEach(initForm);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLiveValidation);
} else {
    initLiveValidation();
}

export default initLiveValidation;
