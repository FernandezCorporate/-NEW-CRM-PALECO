const defaults = { mode: 'light', textSize: 'standard', reduceMotion: false, compact: false, pattern: true };
const normalize = (value) => ({
    mode: ['light', 'dark'].includes(value?.mode) ? value.mode : defaults.mode,
    textSize: ['standard', 'large', 'larger'].includes(value?.textSize) ? value.textSize : defaults.textSize,
    reduceMotion: typeof value?.reduceMotion === 'boolean' ? value.reduceMotion : defaults.reduceMotion,
    compact: typeof value?.compact === 'boolean' ? value.compact : defaults.compact,
    pattern: typeof value?.pattern === 'boolean' ? value.pattern : defaults.pattern,
});

const root = document.documentElement;
const key = root.dataset.preferencesKey;
if (key) {
    let preferences = { ...defaults };
    let storageFailed = false;
    try { preferences = normalize(JSON.parse(localStorage.getItem(key))); }
    catch { storageFailed = true; }

    const apply = () => {
        // Retired color preferences must not override the PALECO palette.
        root.dataset.theme = 'green';
        root.dataset.mode = preferences.mode;
        root.dataset.textSize = preferences.textSize;
        root.dataset.reduceMotion = String(preferences.reduceMotion);
        root.dataset.compact = String(preferences.compact);
        root.dataset.pattern = String(preferences.pattern);
    };
    apply();

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('[data-preferences-form]');
        const status = form?.querySelector('[data-preferences-status]');
        const sync = () => {
            form?.querySelectorAll('input').forEach((input) => {
                input.checked = input.type === 'radio'
                    ? preferences[input.name] === input.value : preferences[input.name];
            });
        };
        const save = () => {
            apply();
            try {
                localStorage.setItem(key, JSON.stringify(preferences));
                storageFailed = false;
            } catch { storageFailed = true; }
            if (status) status.textContent = storageFailed
                ? 'Applied for this page. Browser storage is unavailable; preferences could not be saved.'
                : 'Preferences saved on this browser.';
        };
        sync();
        if (storageFailed && status) status.textContent = 'Saved preferences could not be loaded. Defaults are shown.';
        form?.addEventListener('submit', (event) => event.preventDefault());
        form?.addEventListener('change', () => {
            const data = new FormData(form);
            preferences = normalize({
                mode: data.get('mode'), textSize: data.get('textSize'),
                reduceMotion: data.has('reduceMotion'), compact: data.has('compact'), pattern: data.has('pattern'),
            });
            save();
        });
        form?.querySelector('[data-preferences-reset]')?.addEventListener('click', () => {
            preferences = { ...defaults };
            sync();
            save();
        });
        window.addEventListener('storage', (event) => {
            if (event.key !== key && event.key !== null) return;
            try { preferences = normalize(JSON.parse(event.newValue)); }
            catch { preferences = { ...defaults }; }
            apply();
            sync();
            if (status) status.textContent = 'Preferences updated from another tab.';
        });
    });
}
