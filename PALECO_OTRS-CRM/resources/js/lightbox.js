document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.js-lightbox-trigger');
    const lightbox = document.getElementById('image-lightbox');
    const lightboxImg = document.getElementById('lightbox-image');
    const closeElements = document.querySelectorAll('.js-lightbox-close');

    // Abort if the modal isn't on the current page
    if (!lightbox || !lightboxImg) return;

    // 1. Open Lightbox
    triggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            const src = trigger.getAttribute('data-image-src');
            
            // Set image and reset zoom classes
            lightboxImg.src = src;
            lightboxImg.classList.remove('scale-[2]', 'cursor-zoom-out');
            lightboxImg.classList.add('cursor-zoom-in');
            
            // Show overlay and lock background scrolling
            lightbox.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    });

    // 2. Close Lightbox (Shared logic)
    const closeLightbox = () => {
        lightbox.classList.add('hidden');
        document.body.style.overflow = 'auto';
    };

    // Attach close event to button and background overlay
    closeElements.forEach(el => {
        el.addEventListener('click', (e) => {
            // Ensure we don't close if they click the actual zoomed image
            if (e.target !== lightboxImg) {
                closeLightbox();
            }
        });
    });

    // 3. Toggle Zoom on Image Click
    lightboxImg.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent event bubbling to the background overlay
        
        if (lightboxImg.classList.contains('scale-[2]')) {
            lightboxImg.classList.remove('scale-[2]', 'cursor-zoom-out');
            lightboxImg.classList.add('cursor-zoom-in');
        } else {
            lightboxImg.classList.add('scale-[2]', 'cursor-zoom-out');
            lightboxImg.classList.remove('cursor-zoom-in');
        }
    });

    // 4. Keyboard Accessibility (Close on ESC)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !lightbox.classList.contains('hidden')) {
            closeLightbox();
        }
    });
});