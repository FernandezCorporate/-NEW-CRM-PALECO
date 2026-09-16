document.addEventListener("DOMContentLoaded", function() {
    const controlsRoot = document.querySelector('[data-dashboard-controls]');
    if (!controlsRoot) return;

    const dialog = controlsRoot.querySelector('[data-controls-dialog]');
    const openButton = controlsRoot.querySelector('[data-controls-open]');
    const cancelButton = controlsRoot.querySelector('[data-controls-cancel]');
    const resetButton = controlsRoot.querySelector('[data-controls-reset]');
    const saveButton = controlsRoot.querySelector('[data-controls-save]');
    const emptyState = controlsRoot.querySelector('[data-controls-empty]');
    const feedback = controlsRoot.querySelector('[data-controls-feedback]');
    const count = controlsRoot.querySelector('[data-controls-count]');
    const items = [...controlsRoot.querySelectorAll('[data-control-item]')];
    const choices = [...controlsRoot.querySelectorAll('input[data-control-choice]')];
    const validIds = new Set(items.map((item) => item.dataset.controlItem));
    const storageKey = controlsRoot.dataset.storageKey;
    const maximumControls = 4;

    const parseSelection = (value, fallback = []) => {
        try {
            const selection = JSON.parse(value ?? '[]');
            return Array.isArray(selection) ? [...new Set(selection.filter((id) => validIds.has(id)))].slice(0, maximumControls) : fallback;
        } catch { return fallback; }
    };

    const defaults = parseSelection(controlsRoot.dataset.defaultControls);

    const readSelection = () => {
        try {
            const saved = window.localStorage.getItem(storageKey);
            return saved === null ? defaults : parseSelection(saved, defaults);
        } catch { return defaults; }
    };

    const renderControls = (selection, announce = false) => {
        const selectedIds = new Set(selection);
        items.forEach((item) => item.classList.toggle('hidden', !selectedIds.has(item.dataset.controlItem)));
        emptyState?.classList.toggle('hidden', selection.length > 0);
        if (announce && feedback) feedback.textContent = selection.length ? `${selection.length} dashboard controls saved.` : 'Dashboard controls cleared.';
    };

    const updateChoiceState = () => {
        const selectedCount = choices.filter((choice) => choice.checked).length;
        if (count) count.textContent = `${selectedCount} of ${maximumControls} selected`;
        choices.forEach((choice) => {
            choice.disabled = !choice.checked && selectedCount >= maximumControls;
            choice.closest('.control-choice')?.classList.toggle('is-disabled', choice.disabled);
        });
    };

    const syncChoices = (selection) => {
        const selectedIds = new Set(selection);
        choices.forEach((choice) => choice.checked = selectedIds.has(choice.value));
        updateChoiceState();
    };

    let selectedControls = readSelection();
    renderControls(selectedControls);

    openButton?.addEventListener('click', () => {
        syncChoices(selectedControls);
        if (typeof dialog?.showModal === 'function') dialog.showModal(); else dialog?.setAttribute('open', '');
    });

    choices.forEach((choice) => choice.addEventListener('change', updateChoiceState));
    cancelButton?.addEventListener('click', () => dialog?.close());
    resetButton?.addEventListener('click', () => syncChoices(defaults));

    saveButton?.addEventListener('click', () => {
        selectedControls = choices.filter((c) => c.checked).map((c) => c.value).slice(0, maximumControls);
        try { window.localStorage.setItem(storageKey, JSON.stringify(selectedControls)); } catch {}
        renderControls(selectedControls, true);
        dialog?.close();
        openButton?.focus();
    });

    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
});