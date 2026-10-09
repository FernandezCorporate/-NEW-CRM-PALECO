import TomSelect from 'tom-select';

const initTomSelectSync = () => {
    document.querySelectorAll('.tom-select-sync').forEach((selectElement) => {
        if (selectElement.tomselect) return;
        try {
            new TomSelect(selectElement, {
                create: false,
                maxOptions: null,
                onChange: function(value) {
                    if (selectElement.dataset.autosubmit === 'true' && selectElement.form) {
                        selectElement.form.submit();
                    }
                }
            });
        } catch (e) {
            console.warn('TomSelect failed to initialize on element:', selectElement, e);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTomSelectSync);
} else {
    initTomSelectSync();
}