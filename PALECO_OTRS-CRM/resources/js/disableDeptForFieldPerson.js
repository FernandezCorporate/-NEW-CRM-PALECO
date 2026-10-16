const initDisableDeptForFieldPerson = () => {
    const roleSelect = document.getElementById('user-role-select');
    const deptSelect = document.getElementById('department-select');
    const deptMessage = document.getElementById('dept-team-message');
    const deptRequiredMarker = document.getElementById('dept-required-marker');

    if (deptSelect) {
        const toggleDepartmentInput = () => {
            const tsInstance = deptSelect.tomselect;
            
            let roleSlug = '';
            if (roleSelect) {
                const selectedOption = roleSelect.options[roleSelect.selectedIndex];
                roleSlug = selectedOption ? selectedOption.getAttribute('data-slug') : '';
            } else if (deptSelect.dataset.userRoleSlug) {
                roleSlug = deptSelect.dataset.userRoleSlug;
            }
            
            if (roleSlug !== 'supervisor') {
                if (tsInstance) {
                    tsInstance.clear();
                    tsInstance.disable();
                } else {
                    deptSelect.value = "";
                    deptSelect.disabled = true;
                }
                deptSelect.required = false;
                deptRequiredMarker?.classList.add('hidden');
                deptMessage?.classList.remove('hidden');
            } else {
                if (tsInstance) {
                    tsInstance.enable();
                } else {
                    deptSelect.disabled = false;
                }
                deptSelect.required = true;
                deptRequiredMarker?.classList.remove('hidden');
                deptMessage?.classList.add('hidden');
            }

            if (deptSelect.form) {
                deptSelect.form.dispatchEvent(new Event('input', { bubbles: true }));
                deptSelect.form.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        if (roleSelect && !roleSelect.dataset.bound) {
            roleSelect.dataset.bound = 'true';
            roleSelect.addEventListener('change', toggleDepartmentInput);
        }

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