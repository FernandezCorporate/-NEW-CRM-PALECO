document.addEventListener('DOMContentLoaded', () => {
    
    // Attach listener to every form currently on the page
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function (e) {
            
            // 1. HTML5 Validation Check
            // If the form is invalid (e.g., missing a required field), stop here.
            // Do not disable the button so the user can fix their mistake.
            if (!this.checkValidity()) {
                return;
            }

            // 2. Find the primary submit button
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');

            if (submitBtn) {
                // Disable the button to prevent double-clicks
                submitBtn.disabled = true;
                
                // Add Tailwind classes to visually dim it and change the cursor
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

                // 3. Smart Text Replacement (For <button> tags only)
                if (submitBtn.tagName.toLowerCase() === 'button') {
                    // Look for a custom loading text attribute, default to 'Processing...'
                    const loadingText = submitBtn.getAttribute('data-loading-text') || 'Processing...';
                    
                    // Inject a generic SVG spinner that inherits the button's text color
                    submitBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline-block text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        ${loadingText}
                    `;
                }
            }
        });
    });
});