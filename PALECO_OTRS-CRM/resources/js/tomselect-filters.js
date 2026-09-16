import TomSelect from 'tom-select';

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.ts-filter-dropdown').forEach(function(selectElement) {
        new TomSelect(selectElement, {
            controlInput: null,
            onChange: function(value) {
                let form = selectElement.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    });
});