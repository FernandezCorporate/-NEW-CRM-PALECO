const initPreventDoubleSubmit = () => {
    let activeLoadingModal = null;
    let modalTimeoutTimer = null;

    const showLoadingModal = (title, subtitle = 'Please wait while records are processed...') => {
        if (activeLoadingModal) return;

        const overlay = document.createElement('div');
        overlay.id = 'system-mutation-loading-modal';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 backdrop-blur-xs transition-opacity duration-200 opacity-0';

        overlay.innerHTML = `
            <div class="control-dialog bg-white rounded-2xl border border-slate-200 p-8 shadow-2xl max-w-sm w-[calc(100%-2rem)] text-center transform transition-transform duration-200 scale-95">
                <div class="mx-auto flex items-center justify-center w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 mb-4">
                    <svg class="animate-spin h-7 w-7 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900 mb-1">${title}</h3>
                <p class="text-xs text-slate-500">${subtitle}</p>
            </div>
        `;

        document.body.appendChild(overlay);
        activeLoadingModal = overlay;

        // Smooth fade-in
        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            const card = overlay.querySelector('div');
            if (card) card.classList.remove('scale-95');
        });

        // Safety auto-dismiss timer (15 seconds) in case of server timeouts
        clearTimeout(modalTimeoutTimer);
        modalTimeoutTimer = setTimeout(() => {
            hideLoadingModal();
        }, 15000);
    };

    const hideLoadingModal = () => {
        clearTimeout(modalTimeoutTimer);
        if (activeLoadingModal) {
            activeLoadingModal.remove();
            activeLoadingModal = null;
        }
        const stale = document.getElementById('system-mutation-loading-modal');
        if (stale) stale.remove();
    };

    // Universal function to handle loading state for both forms and links
    const applyLoadingState = (element) => {
        // Prevent double triggers
        if (element.dataset.loading === 'true') return;
        element.dataset.loading = 'true';
        
        // Disable if it's a form button
        if (element.tagName.toLowerCase() === 'button' || element.tagName.toLowerCase() === 'input') {
            element.disabled = true;
        }
        
        // Apply Tailwind classes
        element.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

        // Determine Loading Text
        let text = element.getAttribute('data-loading-text');
        if (text === null) {
            text = element.innerText.trim() ? 'Processing...' : '';
        }

        // Inject SVG spinner
        element.innerHTML = `
            <svg class="animate-spin -ml-1 ${text ? 'mr-2' : ''} h-4 w-4 inline-block text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            ${text}
        `;
    };

    // 1. Intercept ALL Forms (skipping AJAX / client-managed forms)
    document.querySelectorAll('form').forEach(form => {
        if (form.dataset.submitBound) return;

        // Skip forms that manage their own async submission lifecycle or explicitly opt out
        if (
            form.hasAttribute('data-no-modal') ||
            form.hasAttribute('data-ajax') ||
            form.getAttribute('data-prevent-double-submit') === 'false' ||
            !!form.closest('#remark-history')
        ) {
            return;
        }

        form.dataset.submitBound = 'true';
        form.addEventListener('submit', function (e) {
            // Forms with live validation manage their own submission lifecycle;
            // only apply double-submit protection when live validation has verified and passed.
            if (form.hasAttribute('data-live-validation') && !form.dataset.liveValidationPassed) {
                return;
            }

            if (
                form.hasAttribute('data-no-modal') ||
                form.hasAttribute('data-ajax') ||
                form.getAttribute('data-prevent-double-submit') === 'false' ||
                !!form.closest('#remark-history')
            ) {
                return;
            }

            if (e.defaultPrevented) return;
            if (!this.checkValidity()) return;
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitBtn || submitBtn.disabled) return;

            // Only trigger modal loading for mutation forms (POST / PUT / PATCH / DELETE), not GET search forms!
            const isMutation = (form.method || '').toUpperCase() === 'POST' || !!form.querySelector('input[name="_method"]');

            queueMicrotask(() => {
                if (!e.defaultPrevented && submitBtn && !submitBtn.disabled) {
                    applyLoadingState(submitBtn);
                    form.classList.add('is-submitting');
                    form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(el => {
                        if (el.tagName.toLowerCase() === 'input' || el.tagName.toLowerCase() === 'textarea') {
                            el.readOnly = true;
                        }
                    });

                    if (isMutation) {
                        let text = submitBtn.getAttribute('data-loading-text');
                        if (!text) {
                            text = submitBtn.innerText.trim() ? submitBtn.innerText.trim() + '...' : 'Processing...';
                        }
                        showLoadingModal(text);
                    }
                }
            });
        });
    });

    // 2. Intercept ALL Action Links (anchor tags only; form buttons are handled by form submit event)
    document.querySelectorAll('a.action-link').forEach(link => {
        if (link.dataset.linkBound) return;
        link.dataset.linkBound = 'true';
        link.addEventListener('click', function (e) {
            if (this.target === '_blank' || e.ctrlKey || e.metaKey) return;
            applyLoadingState(this);
        });
    });

    // 3. Fix for browser navigation / bfcache freezing the UI in the loading state (never force window.location.reload())
    window.addEventListener('pageshow', () => {
        hideLoadingModal();
        document.querySelectorAll('form.is-submitting').forEach(form => {
            form.classList.remove('is-submitting');
            delete form.dataset.liveValidationPassed;
            form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(el => {
                if (!el.hasAttribute('data-original-readonly')) {
                    el.readOnly = false;
                }
            });
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('data-loading');
            }
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPreventDoubleSubmit);
} else {
    initPreventDoubleSubmit();
}
