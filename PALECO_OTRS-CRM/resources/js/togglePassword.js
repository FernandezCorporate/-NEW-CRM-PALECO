const initTogglePassword = () => {
    const toggleBtn = document.getElementById('toggle-password-btn');
    const passwordInput = document.getElementById('login-password');
    const eyeOpen = document.getElementById('eye-icon-open');
    const eyeClosed = document.getElementById('eye-icon-closed');

    if (toggleBtn && passwordInput && !toggleBtn.dataset.bound) {
        toggleBtn.dataset.bound = 'true';
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeOpen?.classList.toggle('hidden');
            eyeClosed?.classList.toggle('hidden');
        });
    }

    const confirmToggleBtn = document.getElementById('toggle-confirm-password-btn');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const confirmEyeOpen = document.getElementById('confirm-eye-open');
    const confirmEyeClosed = document.getElementById('confirm-eye-closed');

    if (confirmToggleBtn && confirmPasswordInput && !confirmToggleBtn.dataset.bound) {
        confirmToggleBtn.dataset.bound = 'true';
        confirmToggleBtn.addEventListener('click', () => {
            const isPassword = confirmPasswordInput.getAttribute('type') === 'password';
            confirmPasswordInput.setAttribute('type', isPassword ? 'text' : 'password');
            confirmEyeOpen?.classList.toggle('hidden');
            confirmEyeClosed?.classList.toggle('hidden');
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTogglePassword);
} else {
    initTogglePassword();
}