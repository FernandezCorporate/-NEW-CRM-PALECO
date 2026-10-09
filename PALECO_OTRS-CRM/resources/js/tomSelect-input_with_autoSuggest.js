import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    
    document.querySelectorAll('.tom-select-sync').forEach((selectElement) => {
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

});