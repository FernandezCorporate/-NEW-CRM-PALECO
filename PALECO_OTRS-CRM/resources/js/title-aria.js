const initTitleAria = () => {
    document.querySelectorAll('.workspace-surface a[title], .workspace-surface button[title]').forEach((control) => {
        if (!control.textContent.trim() && !control.hasAttribute('aria-label')) {
            control.setAttribute('aria-label', control.title);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initTitleAria);
} else {
    initTitleAria();
}