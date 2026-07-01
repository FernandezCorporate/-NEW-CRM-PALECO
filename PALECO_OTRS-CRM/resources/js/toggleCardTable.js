document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Target generalized IDs that can be pasted into ANY blade file
    const btnList = document.getElementById('list-view-btn');
    const btnCard = document.getElementById('card-view-btn');
    const viewList = document.getElementById('list-view-container');
    const viewCard = document.getElementById('card-view-container');

    // Only run if the toggle elements exist on the current page
    if (!btnList || !btnCard || !viewList || !viewCard) return;

    // 2. Create a dynamic storage key based on the URL (e.g., '_admin_departments_viewPref')
    const pageKey = window.location.pathname.replace(/\//g, '_') + '_viewPref';

    const setView = (viewType) => {
        if (viewType === 'card') {
            viewList.classList.add('hidden');
            viewCard.classList.remove('hidden');
            viewCard.classList.add('grid'); // Ensure grid layout applies
            
            btnCard.classList.add('bg-slate-100', 'text-emerald-600');
            btnList.classList.remove('bg-slate-100', 'text-emerald-600');
            
            localStorage.setItem(pageKey, 'card');
        } else {
            viewCard.classList.add('hidden');
            viewCard.classList.remove('grid');
            viewList.classList.remove('hidden');
            
            btnList.classList.add('bg-slate-100', 'text-emerald-600');
            btnCard.classList.remove('bg-slate-100', 'text-emerald-600');
            
            localStorage.setItem(pageKey, 'list');
        }
    };

    btnList.addEventListener('click', () => setView('list'));
    btnCard.addEventListener('click', () => setView('card'));

    // Initialize state
    const savedView = localStorage.getItem(pageKey) || 'list';
    setView(savedView);
});