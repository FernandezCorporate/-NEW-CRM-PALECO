import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('team-members-container');
    const template = document.getElementById('member-row-template');
    const addBtn = document.getElementById('add-member-btn');
    const noMembersState = document.getElementById('no-members-state');
    
    if (!container || !template || !addBtn) return;

    let memberIndex = 0;
    let tsInstances = [];

    function toggleEmptyState() {
        if (container.children.length === 0) {
            noMembersState.classList.remove('hidden');
        } else {
            noMembersState.classList.add('hidden');
        }
    }

    function syncDisabledOptions() {
        const selectedValues = tsInstances
            .map(ts => ts.getValue())
            .filter(val => val !== "" && val !== null);

        tsInstances.forEach(ts => {
            const currentVal = ts.getValue();
            
            Array.from(ts.input.options).forEach(option => {
                if (option.value === "") return;
                
                if (selectedValues.includes(option.value) && option.value !== currentVal) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
            
            ts.sync();
        });
    }

    // --- INITIALIZE PRE-RENDERED ROWS ---
    const existingSelects = container.querySelectorAll('.tom-select-dynamic');
    existingSelects.forEach((selectElement) => {
        const ts = new TomSelect(selectElement, {
            create: false,
            maxOptions: null,
            // REMOVED: dropdownParent: 'body'
            onChange: function() {
                syncDisabledOptions();
            }
        });
        tsInstances.push(ts);
    });

    const existingRows = container.querySelectorAll('.member-row');
    if (existingRows.length > 0) {
        memberIndex = existingRows.length;
    }
    
    toggleEmptyState();
    syncDisabledOptions();
    // -------------------------------------------------------------

    addBtn.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        
        clone.querySelectorAll('[name*="__INDEX__"]').forEach((element) => {
            element.name = element.name.replace('__INDEX__', memberIndex);
        });

        const selectElement = clone.querySelector('.tom-select-dynamic');
        container.appendChild(clone);

        if (selectElement) {
            const ts = new TomSelect(selectElement, {
                create: false,
                maxOptions: null,
                // REMOVED: dropdownParent: 'body'
                onChange: function() {
                    syncDisabledOptions();
                }
            });
            tsInstances.push(ts);
        }

        memberIndex++;
        
        toggleEmptyState();
        syncDisabledOptions();
    });

    container.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.remove-member-btn');
        
        if (removeBtn) {
            const row = removeBtn.closest('.member-row');
            
            const selectElement = row.querySelector('.tom-select-dynamic');
            if (selectElement && selectElement.tomselect) {
                tsInstances = tsInstances.filter(ts => ts !== selectElement.tomselect);
                selectElement.tomselect.destroy();
            }

            row.remove();
            
            toggleEmptyState();
            syncDisabledOptions();
        }
    });
});