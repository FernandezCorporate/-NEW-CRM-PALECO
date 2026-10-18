const bindPasswordToggle = (toggleBtn, passwordInput, eyeOpen, eyeClosed) => {
    if (!toggleBtn || !passwordInput || toggleBtn.dataset.bound === 'true') {
        return;
    }

    toggleBtn.dataset.bound = 'true';

    const syncVisibility = () => {
        const isPassword = (passwordInput.getAttribute('type') || passwordInput.type) === 'password';
        toggleBtn.setAttribute('aria-pressed', isPassword ? 'false' : 'true');

        const labelText = isPassword ? 'Show password' : 'Hide password';
        toggleBtn.setAttribute('aria-label', labelText);
        toggleBtn.setAttribute('title', labelText);

        if (eyeOpen) {
            eyeOpen.classList.toggle('hidden', isPassword);
        }
        if (eyeClosed) {
            eyeClosed.classList.toggle('hidden', !isPassword);
        }
    };

    // Ensure icon state matches input type upon initial bind
    syncVisibility();

    toggleBtn.addEventListener('click', () => {
        const isCurrentlyPassword = (passwordInput.getAttribute('type') || passwordInput.type) === 'password';
        const nextType = isCurrentlyPassword ? 'text' : 'password';

        passwordInput.setAttribute('type', nextType);
        passwordInput.type = nextType;
        syncVisibility();
    });
};

const initTogglePassword = () => {
    // 1. Primary login toggle (ID-based or data attribute)
    const toggleBtn = document.getElementById('toggle-password-btn') || document.querySelector('[data-password-toggle="login-password"]');
    const passwordInput = document.getElementById('login-password');
    const eyeOpen = document.getElementById('eye-icon-open');
    const eyeClosed = document.getElementById('eye-icon-closed');

    bindPasswordToggle(toggleBtn, passwordInput, eyeOpen, eyeClosed);

    // 2. Secondary confirm password toggle
    const confirmToggleBtn = document.getElementById('toggle-confirm-password-btn') || document.querySelector('[data-password-toggle="confirm-password"]');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const confirmEyeOpen = document.getElementById('confirm-eye-open');
    const confirmEyeClosed = document.getElementById('confirm-eye-closed');

    bindPasswordToggle(confirmToggleBtn, confirmPasswordInput, confirmEyeOpen, confirmEyeClosed);

    // 3. Generic data-password-toggle selectors for future forms
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        const targetId = btn.dataset.passwordToggle;
        if (targetId && targetId !== 'login-password' && targetId !== 'confirm-password') {
            const input = document.getElementById(targetId);
            const open = btn.querySelector('[data-eye-open]');
            const closed = btn.querySelector('[data-eye-closed]');
            bindPasswordToggle(btn, input, open, closed);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTogglePassword);
} else {
    initTogglePassword();
}

document.addEventListener('livewire:navigated', initTogglePassword);