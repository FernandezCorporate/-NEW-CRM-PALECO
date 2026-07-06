import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    
    document.querySelectorAll('.tom-select-sync').forEach((selectElement) => {
        new TomSelect(selectElement, {
            create: false,
            maxOptions: null,
            
            onChange: function(value) {
                if (selectElement.dataset.autosubmit === 'true' && selectElement.form) {
                    selectElement.form.submit();
                }
            }
        });
    });

});