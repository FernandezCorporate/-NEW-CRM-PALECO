document.addEventListener('DOMContentLoaded', () => {
    const otherCheckbox = document.getElementById('other_category');
    const categorySelect = document.getElementById('category_id');
    const customInput = document.getElementById('other_category_name');
    const categoryLabel = document.getElementById('category_id_label');
    const customLabel = document.getElementById('other_category_name_label');

    if (!otherCheckbox || !categorySelect || !customInput) return;

    const toggleCategoryFields = () => {
        // Retrieve Tom Select programmatic instance bound to the element node
        const tsInstance = categorySelect.tomselect;

        if (otherCheckbox.checked) {
            // Modify visual state via Tom Select programmatic hooks
            if (tsInstance) {
                tsInstance.disable();
                tsInstance.clear();
            } else {
                categorySelect.disabled = true;
                categorySelect.value = '';
            }
            categoryLabel.classList.add('text-gray-400');
            categoryLabel.classList.remove('text-gray-700');

            // Activate manual input elements
            customInput.disabled = false;
            customInput.classList.remove('bg-gray-100');
            customLabel.classList.add('text-gray-700', 'font-bold');
            customLabel.classList.remove('text-gray-400');
        } else {
            // Restore interactive select states
            if (tsInstance) {
                tsInstance.enable();
            } else {
                categorySelect.disabled = false;
            }
            categoryLabel.classList.add('text-gray-700');
            categoryLabel.classList.remove('text-gray-400');

            // Deactivate manual inputs
            customInput.disabled = true;
            customInput.value = '';
            customInput.classList.add('bg-gray-100');
            customLabel.classList.add('text-gray-400');
            customLabel.classList.remove('text-gray-700', 'font-bold');
        }
    };

    otherCheckbox.addEventListener('change', toggleCategoryFields);
    
    // Tiny macrotask execution delay ensures global Tom Select initializers finish binding instances
    setTimeout(toggleCategoryFields, 50);
});