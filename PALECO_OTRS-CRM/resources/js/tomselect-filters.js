import TomSelect from 'tom-select';

const initTomSelectFilters = () => {
    document.querySelectorAll('.ts-filter-dropdown').forEach(function(selectElement) {
        if (selectElement.tomselect) return;
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
};

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initTomSelectFilters);
} else {
    initTomSelectFilters();
}