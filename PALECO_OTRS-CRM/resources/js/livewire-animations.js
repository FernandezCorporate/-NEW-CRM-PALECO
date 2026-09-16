document.addEventListener("DOMContentLoaded", function() {
    function applyAnimations() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animatedElements = document.querySelectorAll('[data-animate]:not(.is-visible)');

        if (reduceMotion || !('IntersectionObserver' in window)) {
            animatedElements.forEach((element) => element.classList.add('is-visible'));
        } else {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.14 });

            animatedElements.forEach((element) => observer.observe(element));
        }
    }

    applyAnimations();

    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                requestAnimationFrame(() => applyAnimations());
            });
        });
    });
});