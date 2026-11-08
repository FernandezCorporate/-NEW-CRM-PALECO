/**
 * Ticket Operations Sidebar Toggle Controller
 * Toggles between Assigned Personnel and Escalation Hierarchy panels
 * in the unified right-column sidebar card on Ticket Details.
 * Fully compatible with Livewire differential morphdom updates.
 */

const initTicketSidebarToggle = () => {
    const card = document.querySelector('[data-ticket-sidebar-card]');
    if (!card) return;

    const titleEl = card.querySelector('[data-sidebar-title]');
    const iconPersonnel = card.querySelector('[data-sidebar-icon="personnel"]');
    const iconHierarchy = card.querySelector('[data-sidebar-icon="hierarchy"]');
    const tabs = card.querySelectorAll('[data-sidebar-tab]');
    const panelPersonnel = card.querySelector('[data-sidebar-panel="personnel"]');
    const panelHierarchy = card.querySelector('[data-sidebar-panel="hierarchy"]');

    if (!tabs.length || !panelPersonnel || !panelHierarchy) return;

    const setSidebarView = (targetView, persist = true) => {
        const isPersonnel = targetView === 'personnel';

        // 1. Toggle panels
        panelPersonnel.classList.toggle('hidden', !isPersonnel);
        panelHierarchy.classList.toggle('hidden', isPersonnel);

        // 2. Toggle icons
        if (iconPersonnel) {
            iconPersonnel.classList.toggle('hidden', !isPersonnel);
            iconPersonnel.classList.toggle('inline-flex', isPersonnel);
        }
        if (iconHierarchy) {
            iconHierarchy.classList.toggle('hidden', isPersonnel);
            iconHierarchy.classList.toggle('inline-flex', !isPersonnel);
        }

        // 3. Update title
        if (titleEl) {
            titleEl.textContent = isPersonnel ? 'Assigned Personnel' : 'Escalation Hierarchy';
        }

        // 4. Update tab button styles and ARIA attributes
        tabs.forEach((tab) => {
            const matches = tab.dataset.sidebarTab === targetView;
            tab.setAttribute('aria-selected', String(matches));

            if (matches) {
                tab.classList.remove('text-slate-500', 'font-medium');
                tab.classList.add('bg-white', 'text-slate-800', 'shadow-2xs', 'font-bold');
            } else {
                tab.classList.remove('bg-white', 'text-slate-800', 'shadow-2xs', 'font-bold');
                tab.classList.add('text-slate-500', 'font-medium');
            }
        });

        // 5. Optionally persist choice in sessionStorage for the duration of the ticket session
        if (persist) {
            try {
                sessionStorage.setItem('paleco_ticket_sidebar_view', targetView);
            } catch (e) {
                // Safe ignore if storage quota or private browsing restricts
            }
        }
    };

    // Event delegation on card to survive Livewire re-renders without duplicate listeners
    if (!card._sidebarToggleBound) {
        card.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('[data-sidebar-tab]');
            if (!tabBtn) return;
            e.preventDefault();
            const targetView = tabBtn.dataset.sidebarTab;
            setSidebarView(targetView, true);
        });
        card._sidebarToggleBound = true;
    }

    // Initial state: check URL hash or session preference
    let initialView = 'personnel';
    if (window.location.hash === '#hierarchy' || window.location.hash === '#escalation') {
        initialView = 'hierarchy';
    } else {
        try {
            const saved = sessionStorage.getItem('paleco_ticket_sidebar_view');
            if (saved === 'hierarchy' || saved === 'personnel') {
                initialView = saved;
            }
        } catch (e) {}
    }

    setSidebarView(initialView, false);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTicketSidebarToggle);
} else {
    initTicketSidebarToggle();
}

// Livewire 3 hook to re-sync if the page or Livewire island is updated
document.addEventListener('livewire:navigated', initTicketSidebarToggle);

export default initTicketSidebarToggle;
