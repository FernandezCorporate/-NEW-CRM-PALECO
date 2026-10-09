const initToggleCardTable = () => {
    const btnList = document.getElementById('list-view-btn');
    const btnCard = document.getElementById('card-view-btn');
    const viewList = document.getElementById('list-view-container');
    const viewCard = document.getElementById('card-view-container');

    if (!btnList || !btnCard || !viewList || !viewCard) return;
    if (btnList.dataset.bound) return;
    btnList.dataset.bound = 'true';

    const pageKey = window.location.pathname.replace(/\//g, '_') + '_viewPref';

    const setView = (viewType) => {
        if (viewType === 'card') {
            viewList.classList.add('hidden');
            viewCard.classList.remove('hidden');
            viewCard.classList.add('grid');
            
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

    const savedView = localStorage.getItem(pageKey) || 'list';
    setView(savedView);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToggleCardTable);
} else {
    initToggleCardTable();
}