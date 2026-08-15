import './togglePassword';
import './toggleCardTable';
import './preventDoubleSubmit';
import './tomSelect-input_with_autoSuggest';
import './teamInlines';
import './disableDeptForFieldPerson';
import './ticket-form';
import TomSelect from 'tom-select';


document.addEventListener("DOMContentLoaded", function() {
    // Target our specific filter dropdowns
    document.querySelectorAll('.ts-filter-dropdown').forEach(function(selectElement) {
        new TomSelect(selectElement, {
            // Disables the typing/search area
            controlInput: null,
            
            // Force the form to submit when a new option is clicked
            onChange: function(value) {
                let form = selectElement.closest('form');
                if (form) {
                    form.submit();
                } else {
                    console.error("Could not find the parent form to submit.");
                }
            }
        });
    });
});