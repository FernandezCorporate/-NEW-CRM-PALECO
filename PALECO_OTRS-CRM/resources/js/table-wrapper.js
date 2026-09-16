document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.workspace-surface table').forEach((table) => {
        table.classList.add('system-table');
        const parent = table.parentElement;
        if (parent && !parent.classList.contains('overflow-x-auto') && !parent.classList.contains('system-table-scroll')) {
            const scroller = document.createElement('div');
            scroller.className = 'system-table-scroll';
            parent.insertBefore(scroller, table);
            scroller.appendChild(table);
        }
        const scroller = table.parentElement;
        if (scroller?.matches('.overflow-x-auto, .system-table-scroll')) {
            scroller.tabIndex = 0;
            scroller.setAttribute('role', 'region');
            scroller.setAttribute('aria-label', 'Records table. Scroll horizontally to view all columns.');
        }
    });
});