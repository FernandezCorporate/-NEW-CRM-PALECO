document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.workspace-surface form[method="GET"]').forEach((form) => {
        form.classList.add('filter-toolbar');
        form.querySelectorAll('input:not([type="hidden"]), select').forEach((field) => {
            if (!field.labels?.length && !field.hasAttribute('aria-label')) {
                const names = { search: 'Search records', filter: 'Filter records', sort: 'Sort records', status: 'Ticket status', category: 'Activity category', department: 'Department' };
                field.setAttribute('aria-label', names[field.name] || field.name.replaceAll('_', ' '));
            }
        });
    });
});