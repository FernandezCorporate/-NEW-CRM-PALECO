document.addEventListener('DOMContentLoaded', () => {
    
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

    // 1. Intercept ALL Forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!this.checkValidity()) return;
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) applyLoadingState(submitBtn);
        });
    });

    // 2. Intercept ALL Action Links
    document.querySelectorAll('.action-link').forEach(link => {
        link.addEventListener('click', function (e) {
            if (this.target === '_blank' || e.ctrlKey || e.metaKey) return;
            applyLoadingState(this);
        });
    });

    // 3. Fix for browser back button (bfcache) freezing the UI in the loading state
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });
});