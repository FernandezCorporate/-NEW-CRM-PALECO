document.addEventListener("DOMContentLoaded", function() {
    const historyLinks = document.querySelectorAll('.ticket-history-nav a[href^="#"]');
    const updateHistorySelection = () => {
        historyLinks.forEach((link) => {
            if (link.getAttribute('href') === window.location.hash) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };
    if (historyLinks.length) {
        updateHistorySelection();
        window.addEventListener('hashchange', updateHistorySelection);
    }
});