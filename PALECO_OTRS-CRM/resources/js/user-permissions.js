/**
 * User Permission Checklist
 * On the Create User form, shows the permissions of the picked role and ticks its defaults.
 * Hidden permissions are disabled so they are never sent.
 */
const initPermissionChecklist = () => {
    const checklist = document.getElementById('permission-checklist');
    if (!checklist || checklist.dataset.bound) return;
    checklist.dataset.bound = 'true';

    const editable = checklist.dataset.editable === 'true';
    const isCreate = checklist.dataset.mode === 'create';
    const roleSelect = document.getElementById('user-role-select');
    const adminWarning = document.getElementById('permission-admin-warning');
    const items = [...checklist.querySelectorAll('[data-permission-item]')];

    const selectedRoleSlug = () => roleSelect?.selectedOptions[0]?.dataset.slug ?? '';

    // Shows the permissions of the role; resetting ticks all of them, since a new account starts with its role's defaults
    const applyRole = (reset) => {
        const slug = isCreate ? selectedRoleSlug() : null;

        items.forEach((item) => {
            const box = item.querySelector('input[type="checkbox"]');
            const applies = !isCreate || item.dataset.roles.split(' ').includes(slug);
            item.classList.toggle('hidden', !applies);
            box.disabled = !editable || !applies;
            if (applies && reset) box.checked = true;
        });

        checklist.querySelectorAll('[data-permission-group]').forEach((group) => {
            group.classList.toggle('hidden', !group.querySelector('[data-permission-item]:not(.hidden)'));
        });

        if (isCreate) {
            checklist.classList.toggle('hidden', !slug);
            adminWarning?.classList.toggle('hidden', slug !== 'admin');
        }
    };

    if (isCreate && roleSelect) {
        roleSelect.addEventListener('change', () => applyRole(true));
    }

    document.getElementById('permission-reset')?.addEventListener('click', () => {
        applyRole(true);
        checklist.closest('form')?.dispatchEvent(new Event('form:revalidate'));
    });

    // After a failed submit the previous ticks are kept; otherwise a new account starts from the defaults
    applyRole(isCreate && checklist.dataset.restoreOld !== 'true');
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPermissionChecklist);
} else {
    initPermissionChecklist();
}

export default initPermissionChecklist;
