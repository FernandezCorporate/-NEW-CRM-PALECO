document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.workspace-surface input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), .workspace-surface textarea, .workspace-surface select').forEach((field) => {
        if (field.closest('.ts-wrapper, .ts-dropdown')) return;
        field.classList.add('system-field');
    });
});