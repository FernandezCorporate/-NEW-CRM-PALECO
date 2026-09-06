import './togglePassword';
import './preferences';
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

    // Reflect anchor navigation, including deep links and browser Back/Forward.
    const historyLinks = document.querySelectorAll('.ticket-history-nav a[href^="#"]');
    const updateHistorySelection = () => {
        historyLinks.forEach((link) => {
            if (link.getAttribute('href') === window.location.hash) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };
    if (historyLinks.length) {
        updateHistorySelection();
        window.addEventListener('hashchange', updateHistorySelection);
    }

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
        const scroller = table.parentElement;
        if (scroller?.matches('.overflow-x-auto, .system-table-scroll')) {
            scroller.tabIndex = 0;
            scroller.setAttribute('role', 'region');
            scroller.setAttribute('aria-label', 'Records table. Scroll horizontally to view all columns.');
        }
    });

    document.querySelectorAll('.workspace-surface form[method="GET"]').forEach((form) => {
        form.classList.add('filter-toolbar');
        form.querySelectorAll('input:not([type="hidden"]), select').forEach((field) => {
            if (!field.labels?.length && !field.hasAttribute('aria-label')) {
                const names = { search: 'Search records', filter: 'Filter records', sort: 'Sort records', status: 'Ticket status', category: 'Activity category', department: 'Department' };
                field.setAttribute('aria-label', names[field.name] || field.name.replaceAll('_', ' '));
            }
        });
    });

    document.querySelectorAll('.workspace-surface a[title], .workspace-surface button[title]').forEach((control) => {
        if (!control.textContent.trim() && !control.hasAttribute('aria-label')) {
            control.setAttribute('aria-label', control.title);
        }
    });

    document.querySelectorAll('.workspace-surface input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), .workspace-surface textarea, .workspace-surface select').forEach((field) => {
        // Tom Select owns its internal search input; style the outer control only.
        if (field.closest('.ts-wrapper, .ts-dropdown')) return;
        field.classList.add('system-field');
    });

    // Operational forms and tables stay immediately visible; only explicitly
    // marked dashboard sections participate in entrance motion.

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
