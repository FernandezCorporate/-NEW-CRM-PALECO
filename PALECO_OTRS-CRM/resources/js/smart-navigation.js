/**
 * Smart Navigation Engine
 * Handles contextual browser history backtracking with authenticated dashboard or guest portal fallback.
 */
const handleSmartBack = (button) => {
    const fallbackUrl = button.getAttribute('data-fallback-url') || '/';

    try {
        // If the user navigated from within the application, step backwards in history
        if (document.referrer && (new URL(document.referrer)).origin === window.location.origin) {
            window.history.back();
            return;
        }

        // If window history entries exist, step backwards
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) {
        // Safe cross-origin error handling
    }

    // Direct entry / fresh tab fallback
    window.location.href = fallbackUrl;
};

const initSmartNavigation = () => {
    document.querySelectorAll('[data-smart-back]').forEach((button) => {
        if (button.dataset.smartBackBound === 'true') return;
        button.dataset.smartBackBound = 'true';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            handleSmartBack(button);
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmartNavigation);
} else {
    initSmartNavigation();
}

