document.addEventListener('DOMContentLoaded', function() {
    const linkCheckbox = document.getElementById('link_consumer');
    const accountContainer = document.getElementById('account_code_container');
    const accountInput = document.getElementById('account_code');
    const verifyBtn = document.getElementById('verify_consumer_btn');
    const previewBox = document.getElementById('consumer_preview');

    if (linkCheckbox && accountContainer && accountInput) {
        if (linkCheckbox.checked) accountInput.setAttribute('required', 'required');

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
                previewBox.classList.add('hidden');
            }
        });

        if (verifyBtn) {
            verifyBtn.addEventListener('click', async function() {
                // Auto-format spaces to dashes
                let code = accountInput.value.trim().replace(/\s+/g, '-');
                accountInput.value = code;

                // Frontend Regex Validation (e.g., 02-0504-8538)
                const formatRegex = /^\d{2}-\d{4}-\d{4}$/;
                
                if (!code) {
                    showPreview('error', 'Please enter an account code to verify.');
                    return;
                }
                
                if (!formatRegex.test(code)) {
                    showPreview('error', 'Invalid format. Please use the standard XX-XXXX-XXXX notation.');
                    return;
                }

                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Verifying...';
                previewBox.classList.add('hidden');

                try {
                    const response = await fetch(`/cwd/consumers/verify/${encodeURIComponent(code)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const responseText = await response.text();
                    let data;
                    
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        console.error("Server HTML Error:", responseText);
                        throw new Error("Server returned an invalid response format. Check console (F12).");
                    }

                    if (response.ok && data.success) {
                        const statusColor = data.consumer.status === 'A' ? 'text-emerald-600' : 'text-red-600';
                        const statusText = data.consumer.status === 'A' ? 'Active' : (data.consumer.status || 'Unknown');
                        
                        const html = `
                            <div class="font-bold text-gray-900 mb-1">${data.consumer.name}</div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-bold ${statusColor} uppercase tracking-wider">${statusText}</span>
                                <span class="text-xs text-gray-500 font-mono">• S/N: ${data.consumer.meter_serial || 'N/A'}</span>
                            </div>
                            <div class="text-xs text-gray-600 leading-tight">${data.consumer.address}</div>
                        `;
                        showPreview('success', html);
                    } else {
                        const errorMsg = data.errors?.account_code?.[0] || data.message || 'Verification failed.';
                        showPreview('error', errorMsg);
                    }
                } catch (error) {
                    showPreview('error', error.message || 'A network error occurred while verifying.');
                } finally {
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify';
                }
            });
        }

        function showPreview(type, content) {
            previewBox.classList.remove('hidden', 'bg-emerald-50', 'border-emerald-200', 'bg-red-50', 'border-red-200', 'text-red-700');
            
            if (type === 'success') {
                previewBox.classList.add('bg-emerald-50', 'border-emerald-200', 'block');
                previewBox.innerHTML = content;
            } else {
                previewBox.classList.add('bg-red-50', 'border-red-200', 'text-red-700', 'block');
                previewBox.innerHTML = `<div class="flex gap-2 items-start"><svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span class="font-medium">${content}</span></div>`;
            }
        }
    }
});