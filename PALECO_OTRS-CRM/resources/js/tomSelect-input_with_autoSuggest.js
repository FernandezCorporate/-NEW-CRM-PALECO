import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    
    document.querySelectorAll('.tom-select-sync').forEach((selectElement) => {
        new TomSelect(selectElement, {
            create: false,
            maxOptions: null,
            
            onChange: function(value) {
                // REMOVED 'value &&' so that clicking "All Departments" (which submits value="all") 
                // or clearing the input actively submits the form.
                if (selectElement.dataset.autosubmit === 'true' && selectElement.form) {
                    selectElement.form.submit();
                }
            }
        });
    });

});