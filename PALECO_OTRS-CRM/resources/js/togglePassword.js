// Wrap in DOMContentLoaded to ensure the HTML is fully parsed before attaching listeners
document.addEventListener('DOMContentLoaded', () => {
    
    const toggleBtn = document.getElementById('toggle-password-btn');
    const passwordInput = document.getElementById('login-password');
    const eyeOpen = document.getElementById('eye-icon-open');
    const eyeClosed = document.getElementById('eye-icon-closed');

    // Always check if the elements exist first. 
    // Since app.js runs on every page, this prevents null errors on pages without a login form.
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeOpen.classList.toggle('hidden');
            eyeClosed.classList.toggle('hidden');
        });
    }

});