document.addEventListener("DOMContentLoaded", function() {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

    const setSidebarOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        sidebarToggle?.setAttribute('aria-expanded', String(open));
    };

    sidebarToggle?.addEventListener('click', () => setSidebarOpen(!document.body.classList.contains('sidebar-open')));
    sidebarOverlay?.addEventListener('click', () => setSidebarOpen(false));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setSidebarOpen(false); });
    window.addEventListener('resize', () => { if (window.innerWidth >= 768) setSidebarOpen(false); });
});