const initLivewireAnimations = () => {
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

    // Livewire may already be running when this bundle loads, so register immediately when possible
    let registered = false;
    const registerHook = () => {
        if (registered || typeof window.Livewire === 'undefined' || !window.Livewire.hook) return;
        registered = true;
        // 'morphed' runs after Livewire has patched the DOM; the commit hook's succeed() never fires in this Livewire version
        window.Livewire.hook('morphed', () => applyAnimations());
    };

    registerHook();
    document.addEventListener('livewire:init', registerHook);
    document.addEventListener('livewire:initialized', registerHook);
};

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initLivewireAnimations);
} else {
    initLivewireAnimations();
}