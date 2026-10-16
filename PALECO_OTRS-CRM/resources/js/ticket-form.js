const initTicketCategoryToggle = () => {
    const otherCheckbox = document.getElementById('other_category');
    const categorySelect = document.getElementById('category_id');
    const customInput = document.getElementById('other_category_name');
    const categoryLabel = document.getElementById('category_id_label');
    const customLabel = document.getElementById('other_category_name_label');
    const customMarker = document.getElementById('other_category_required_marker');

    if (!otherCheckbox || !categorySelect || !customInput) return;
    if (otherCheckbox.dataset.bound) return;
    otherCheckbox.dataset.bound = 'true';

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
            categorySelect.required = false;
            categoryLabel?.classList.add('text-slate-400');
            categoryLabel?.classList.remove('text-slate-700');

            // Activate manual input elements
            customInput.disabled = false;
            customInput.required = true;
            customInput.classList.remove('bg-slate-50', 'text-slate-400');
            customLabel?.classList.add('text-slate-700', 'font-bold');
            customLabel?.classList.remove('text-slate-400');
            customMarker?.classList.remove('hidden');
        } else {
            // Restore interactive select states
            if (tsInstance) {
                tsInstance.enable();
            } else {
                categorySelect.disabled = false;
            }
            categorySelect.required = true;
            categoryLabel?.classList.add('text-slate-700');
            categoryLabel?.classList.remove('text-slate-400');

            // Deactivate manual inputs
            customInput.disabled = true;
            customInput.required = false;
            customInput.value = '';
            customInput.classList.add('bg-slate-50', 'text-slate-400');
            customLabel?.classList.add('text-slate-400');
            customLabel?.classList.remove('text-slate-700', 'font-bold');
            customMarker?.classList.add('hidden');
        }

        otherCheckbox.form?.dispatchEvent(new CustomEvent('ticket-form:revalidate', { bubbles: true }));
        otherCheckbox.form?.dispatchEvent(new Event('input', { bubbles: true }));
        otherCheckbox.form?.dispatchEvent(new Event('change', { bubbles: true }));
    };

    otherCheckbox.addEventListener('change', toggleCategoryFields);

    // Event-driven initial execution eliminating setTimeout hack
    if (categorySelect.tomselect) {
        toggleCategoryFields();
    } else {
        categorySelect.addEventListener('tomselect:initialized', toggleCategoryFields, { once: true });
        toggleCategoryFields();
    }
};

const initTicketForm = () => {
    initTicketCategoryToggle();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTicketForm);
} else {
    initTicketForm();
}