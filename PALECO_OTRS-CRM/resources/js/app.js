import './togglePassword';
import './toggleCardTable';
import './preventDoubleSubmit';
import './tomSelect-input_with_autoSuggest';
import './teamInlines';
import './disableDeptForFieldPerson';
import './ticket-form';
import './lightbox';
import TomSelect from 'tom-select';


document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

    const setSidebarOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        sidebarToggle?.setAttribute('aria-expanded', String(open));
    };

    sidebarToggle?.addEventListener('click', () => {
        setSidebarOpen(!document.body.classList.contains('sidebar-open'));
    });
    sidebarOverlay?.addEventListener('click', () => setSidebarOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebarOpen(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) setSidebarOpen(false);
    });

    const controlsRoot = document.querySelector('[data-dashboard-controls]');

    if (controlsRoot) {
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
                return Array.isArray(selection)
                    ? [...new Set(selection.filter((id) => validIds.has(id)))].slice(0, maximumControls)
                    : fallback;
            } catch {
                return fallback;
            }
        };

        const defaults = parseSelection(controlsRoot.dataset.defaultControls);

        const readSelection = () => {
            try {
                const saved = window.localStorage.getItem(storageKey);
                return saved === null ? defaults : parseSelection(saved, defaults);
            } catch {
                return defaults;
            }
        };

        const renderControls = (selection, announce = false) => {
            const selectedIds = new Set(selection);
            items.forEach((item) => item.classList.toggle('hidden', !selectedIds.has(item.dataset.controlItem)));
            emptyState?.classList.toggle('hidden', selection.length > 0);

            if (announce && feedback) {
                feedback.textContent = selection.length
                    ? `${selection.length} dashboard ${selection.length === 1 ? 'control' : 'controls'} saved.`
                    : 'Dashboard controls cleared.';
            }
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
            choices.forEach((choice) => {
                choice.checked = selectedIds.has(choice.value);
            });
            updateChoiceState();
        };

        let selectedControls = readSelection();
        renderControls(selectedControls);

        openButton?.addEventListener('click', () => {
            syncChoices(selectedControls);
            if (typeof dialog?.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog?.setAttribute('open', '');
            }
        });

        choices.forEach((choice) => choice.addEventListener('change', updateChoiceState));
        cancelButton?.addEventListener('click', () => dialog?.close());
        resetButton?.addEventListener('click', () => syncChoices(defaults));

        saveButton?.addEventListener('click', () => {
            selectedControls = choices
                .filter((choice) => choice.checked)
                .map((choice) => choice.value)
                .slice(0, maximumControls);

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(selectedControls));
            } catch {
                // The controls still update for this visit if browser storage is unavailable.
            }

            renderControls(selectedControls, true);
            dialog?.close();
            openButton?.focus();
        });

        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    }

    document.querySelectorAll('.workspace-surface table').forEach((table) => {
        table.classList.add('system-table');

        const parent = table.parentElement;
        if (parent && !parent.classList.contains('overflow-x-auto') && !parent.classList.contains('system-table-scroll')) {
            const scroller = document.createElement('div');
            scroller.className = 'system-table-scroll';
            parent.insertBefore(scroller, table);
            scroller.appendChild(table);
        }
    });

    document.querySelectorAll('.workspace-surface input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), .workspace-surface textarea, .workspace-surface select').forEach((field) => {
        field.classList.add('system-field');
    });

    document.querySelectorAll('.workspace-surface > *').forEach((section, index) => {
        if (!section.hasAttribute('data-animate') && index < 5) {
            section.setAttribute('data-animate', '');
            section.classList.add('ui-reveal');
            section.style.setProperty('--delay', `${Math.min(index * 45, 135)}ms`);
        }
    });

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const animatedElements = document.querySelectorAll('[data-animate]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        animatedElements.forEach((element) => element.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.14 });

        animatedElements.forEach((element) => observer.observe(element));
    }

    // Target our specific filter dropdowns
    document.querySelectorAll('.ts-filter-dropdown').forEach(function(selectElement) {
        new TomSelect(selectElement, {
            // Disables the typing/search area
            controlInput: null,
            
            // Force the form to submit when a new option is clicked
            onChange: function(value) {
                let form = selectElement.closest('form');
                if (form) {
                    form.submit();
                } else {
                    console.error("Could not find the parent form to submit.");
                }
            }
        });
    });
});
