const initDisableDeptForFieldPerson = () => {
    const roleSelect = document.getElementById('user-role-select');
    const deptSelect = document.getElementById('department-select');
    const deptMessage = document.getElementById('dept-team-message');

    if (roleSelect && deptSelect && !roleSelect.dataset.bound) {
        roleSelect.dataset.bound = 'true';

        const toggleDepartmentInput = () => {
            const tsInstance = deptSelect.tomselect;
            
            const selectedOption = roleSelect.options[roleSelect.selectedIndex];
            const roleSlug = selectedOption ? selectedOption.getAttribute('data-slug') : '';
            
            if (roleSlug !== 'supervisor') {
                if (tsInstance) {
                    tsInstance.clear();
                    tsInstance.disable();
                } else {
                    deptSelect.value = "";
                    deptSelect.disabled = true;
                }
                deptMessage?.classList.remove('hidden');
            } else {
                if (tsInstance) {
                    tsInstance.enable();
                } else {
                    deptSelect.disabled = false;
                }
                deptMessage?.classList.add('hidden');
            }
        };

        roleSelect.addEventListener('change', toggleDepartmentInput);

        // Event-driven check eliminating setTimeout hack
        if (deptSelect.tomselect) {
            toggleDepartmentInput();
        } else {
            deptSelect.addEventListener('tomselect:initialized', toggleDepartmentInput, { once: true });
            toggleDepartmentInput();
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDisableDeptForFieldPerson);
} else {
    initDisableDeptForFieldPerson();
}