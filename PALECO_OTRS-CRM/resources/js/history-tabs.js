/**
 * Single-responsibility tab controller for Ticket Details Activity & History.
 * Controls dynamic visibility across history tables and the communication remarks thread.
 */
const initHistoryTabs = () => {
    const nav = document.querySelector('.ticket-history-nav');
    if (!nav) return;

    const tabs = Array.from(nav.querySelectorAll('[data-tab-target], a[href^="#"]'));
    if (!tabs.length) return;

    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
    if (!panels.length) return;

    const getTargetId = (tab) => {
        return tab.getAttribute('data-tab-target') || tab.getAttribute('href')?.replace('#', '');
    };

    const activateTab = (targetId, updateHash = true) => {
        if (!targetId) return;

        // Verify target panel exists
        const targetPanel = panels.find((panel) => panel.getAttribute('data-tab-panel') === targetId || panel.id === targetId);
        if (!targetPanel) return;

        // Toggle Tabs
        tabs.forEach((tab) => {
            const isMatch = getTargetId(tab) === targetId;
            tab.setAttribute('aria-selected', isMatch ? 'true' : 'false');
            if (isMatch) {
                tab.setAttribute('aria-current', 'page');
            } else {
                tab.removeAttribute('aria-current');
            }
        });

        // Toggle Panels
        panels.forEach((panel) => {
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

    // Attach click listeners to tabs
    tabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = getTargetId(tab);
            activateTab(targetId, true);
        });
    });

    // Resolve initial active tab from URL hash, defaulting to first tab
    const initialHash = window.location.hash ? window.location.hash.replace('#', '') : null;
    const hasMatchingInitialPanel = initialHash && panels.some((panel) => panel.getAttribute('data-tab-panel') === initialHash || panel.id === initialHash);

    const defaultTargetId = hasMatchingInitialPanel ? initialHash : getTargetId(tabs[0]);
    activateTab(defaultTargetId, false);

    // React to hashchange events
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            activateTab(hash, false);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHistoryTabs);
} else {
    initHistoryTabs();
}