document.addEventListener('DOMContentLoaded', function() {
    const linkCheckbox = document.getElementById('link_consumer');
    const accountContainer = document.getElementById('account_code_container');
    const accountInput = document.getElementById('account_code');

    if (linkCheckbox && accountContainer && accountInput) {
        if (linkCheckbox.checked) {
            accountInput.setAttribute('required', 'required');
        }

        linkCheckbox.addEventListener('change', function() {
            if (this.checked) {
                accountContainer.classList.remove('hidden');
                accountContainer.classList.add('block');
                accountInput.setAttribute('required', 'required');
                accountInput.focus();
            } else {
                accountContainer.classList.remove('block');
                accountContainer.classList.add('hidden');
                accountInput.removeAttribute('required');
                accountInput.value = ''; 
            }
        });
    }
});