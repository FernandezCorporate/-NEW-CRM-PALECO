/**
 * Automatically dismisses detached HTML5 validation bubbles when scrolling
 * inside an overflow container (such as main.overflow-y-auto in the app shell).
 */
const initValidationScrollDismiss = () => {
    let isValidationBubbleActive = false;
    let validationTimestamp = 0;

    // Track when a validation bubble is opened
    document.addEventListener('invalid', () => {
        isValidationBubbleActive = true;
        validationTimestamp = Date.now();
    }, true);

    // Reset when the user starts interacting with form inputs
    ['input', 'change', 'mousedown', 'pointerdown'].forEach((evt) => {
        document.addEventListener(evt, () => {
            isValidationBubbleActive = false;
        }, true);
    });

    const dismissValidationBubble = () => {
        // Allow a small grace period (200ms) for the browser's own automatic scroll-to-invalid field
        if (isValidationBubbleActive && Date.now() - validationTimestamp > 200) {
            isValidationBubbleActive = false;
            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }
        }
    };

    // User-initiated scrolling events
    window.addEventListener('wheel', dismissValidationBubble, { passive: true });
    window.addEventListener('touchmove', dismissValidationBubble, { passive: true });
    window.addEventListener('scroll', dismissValidationBubble, { capture: true, passive: true });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initValidationScrollDismiss);
} else {
    initValidationScrollDismiss();
}

