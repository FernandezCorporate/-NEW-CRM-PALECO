document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.workspace-surface a[title], .workspace-surface button[title]').forEach((control) => {
        if (!control.textContent.trim() && !control.hasAttribute('aria-label')) {
            control.setAttribute('aria-label', control.title);
        }
    });
});