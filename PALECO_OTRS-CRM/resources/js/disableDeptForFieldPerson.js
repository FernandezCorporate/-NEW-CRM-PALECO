document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.getElementById('user-role-select');
    const deptSelect = document.getElementById('department-select');
    const deptMessage = document.getElementById('dept-team-message');

    if (roleSelect && deptSelect) {
        
        const toggleDepartmentInput = () => {
            // Access the specific Tom Select instance attached to the DOM element
            const tsInstance = deptSelect.tomselect;
            
            // Assuming 'field_personnel' is the enum value output in your HTML
            if (roleSelect.value === 'field_personnel') {
                if (tsInstance) {
                    tsInstance.clear();
                    tsInstance.disable();
                } else {
                    // Fallback for standard HTML select behavior
                    deptSelect.value = "";
                    deptSelect.disabled = true;
                }
                deptMessage.classList.remove('hidden');
            } else {
                if (tsInstance) {
                    tsInstance.enable();
                } else {
                    deptSelect.disabled = false;
                }
                deptMessage.classList.add('hidden');
            }
        };

        // Listen for user changes
        roleSelect.addEventListener('change', toggleDepartmentInput);
        
        // Run immediately on page load (Wrapped in a tiny timeout to ensure Tom Select has finished booting first)
        setTimeout(toggleDepartmentInput, 100); 
    }
});