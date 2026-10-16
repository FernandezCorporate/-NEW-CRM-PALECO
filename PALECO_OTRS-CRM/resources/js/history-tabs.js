/**
 * Single-responsibility tab controller for Ticket Details Activity & History.
 * Controls dynamic visibility across history tables and the communication remarks thread.
 * Synchronized with Livewire DOM morphing for real-time WebSocket updates.
 */
const initHistoryTabs = () => {
    const navs = document.querySelectorAll('.ticket-history-nav');
    if (!navs.length) return;

    navs.forEach((nav) => {
        const getTargetId = (tab) => {
            return tab.getAttribute('data-tab-target') || tab.getAttribute('href')?.replace('#', '');
        };

        const currentTabs = Array.from(nav.querySelectorAll('[data-tab-target], a[href^="#"]'));
        const tabTargetIds = currentTabs.map(getTargetId).filter(Boolean);
        const currentPanels = Array.from(document.querySelectorAll('[data-tab-panel]')).filter(panel => 
            tabTargetIds.includes(panel.getAttribute('data-tab-panel')) || tabTargetIds.includes(panel.id)
        );

        const activateTab = (targetId, updateHash = true) => {
            if (!targetId) return;

            // Verify target panel exists
            const targetPanel = currentPanels.find((panel) => panel.getAttribute('data-tab-panel') === targetId || panel.id === targetId);
            if (!targetPanel) return;

            // Toggle Tabs
            currentTabs.forEach((tab) => {
                const isMatch = getTargetId(tab) === targetId;
                tab.setAttribute('aria-selected', isMatch ? 'true' : 'false');
                if (isMatch) {
                    tab.setAttribute('aria-current', 'page');
                } else {
                    tab.removeAttribute('aria-current');
                }
            });

            // Toggle Panels
            currentPanels.forEach((panel) => {
                const isMatch = panel.getAttribute('data-tab-panel') === targetId || panel.id === targetId;
                if (isMatch) {
                    panel.classList.remove('hidden');
                    panel.setAttribute('aria-hidden', 'false');
                } else {
                    panel.classList.add('hidden');
                    panel.setAttribute('aria-hidden', 'true');
                }
            });

            if (updateHash) {
                try {
                    history.replaceState(null, '', `#${targetId}`);
                } catch {
                    // Graceful fallback for environments with restricted state history
                }
            }
        };

        // Attach delegated click listener to nav to avoid duplicate listeners on Livewire re-renders
        if (!nav._historyTabsBound) {
            nav.addEventListener('click', (e) => {
                const tab = e.target.closest('[data-tab-target], a[href^="#"]');
                if (!tab) return;
                e.preventDefault();
                const targetId = getTargetId(tab);
                activateTab(targetId, true);
            });
            nav._historyTabsBound = true;
        }

        // Resolve active tab from URL hash, defaulting to first tab
        const syncActiveTab = () => {
            if (!currentTabs.length || !currentPanels.length) return;

            const initialHash = window.location.hash ? window.location.hash.replace('#', '') : null;
            const hasMatchingInitialPanel = initialHash && currentPanels.some((panel) => panel.getAttribute('data-tab-panel') === initialHash || panel.id === initialHash);

            const defaultTargetId = hasMatchingInitialPanel ? initialHash : getTargetId(currentTabs[0]);
            activateTab(defaultTargetId, false);
        };

        syncActiveTab();

        // React to hashchange events
        if (!nav._historyTabsHashBound) {
            window.addEventListener('hashchange', () => {
                const hash = window.location.hash.replace('#', '');
                if (hash && tabTargetIds.includes(hash)) {
                    activateTab(hash, false);
                }
            });
            nav._historyTabsHashBound = true;
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHistoryTabs);
} else {
    initHistoryTabs();
}

document.addEventListener('livewire:initialized', () => {
    if (typeof Livewire !== 'undefined' && Livewire.hook) {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                requestAnimationFrame(() => {
                    initHistoryTabs();
                });
            });
        });
    }
});