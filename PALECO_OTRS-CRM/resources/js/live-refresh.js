/**
 * Live refresh for server-rendered (non-Livewire) pages.
 *
 * Livewire components already re-render themselves from broadcast events. Pages built from plain Blade
 * opt in with data attributes instead:
 *
 *   data-live-region="key"            element whose inner HTML is swapped with the same element from a fresh copy of the page
 *   data-live-notice                  hidden element revealed instead of swapping content (used on forms, so typed input is never lost)
 *   data-live-events="A,B"            broadcast names (without the leading dot) that should trigger it
 *   data-live-channel="cwd.operations" private channel to listen on (default: cwd.operations)
 *   data-live-match="ticket_id:ID"    optional: only react when the event payload carries that value (several pairs separated by |)
 *
 * A region holding a form the user has started typing in is not swapped; its notice (or a generated one) is shown instead.
 */

const DEFAULT_CHANNEL = 'cwd.operations';
const REFRESH_DELAY_MS = 400;

const pendingRegions = new Set();
let refreshTimer = null;

const parseMatch = (raw) => {
    if (!raw) return [];

    return raw.split('|').map((pair) => {
        const [key, value] = pair.split(':');
        return { key: key.trim(), value: (value ?? '').trim() };
    });
};

// An event matches when any listed key in the payload equals the expected value
const matches = (element, payload) => {
    const rules = parseMatch(element.dataset.liveMatch);
    if (rules.length === 0) return true;

    return rules.some(({ key, value }) => payload && String(payload[key] ?? '') === value);
};

// Named form values in a region. Compared with a snapshot taken after the page finished loading, because
// enhancers such as Tom Select change fields while they initialise and that is not the user typing.
const serializeFields = (region) =>
    JSON.stringify([...region.querySelectorAll('input[name], textarea[name], select[name]')]
        .filter((field) => field.type !== 'hidden')
        .map((field) => {
            if (field.type === 'checkbox' || field.type === 'radio') return [field.name, field.value, field.checked];
            if (field.tagName === 'SELECT') return [field.name, [...field.selectedOptions].map((option) => option.value)];
            return [field.name, field.value];
        }));

const snapshots = new WeakMap();
const takeSnapshot = (region) => snapshots.set(region, serializeFields(region));

const isDirty = (region) =>
    (snapshots.has(region) && snapshots.get(region) !== serializeFields(region))
    || (region.contains(document.activeElement) && document.activeElement.matches('input, textarea, select'));

const revealNotice = (element) => {
    element.hidden = false;
    element.classList.remove('hidden');
};

// A dirty region gets a notice instead of a swap, so nothing the user typed is thrown away
const noticeFor = (region) => {
    let notice = region.querySelector(':scope > [data-live-generated-notice]');
    if (notice) return notice;

    notice = document.createElement('div');
    notice.dataset.liveGeneratedNotice = '';
    notice.setAttribute('role', 'status');
    notice.className = 'mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800';
    notice.innerHTML = '<span>This record was just updated by someone else.</span>';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'font-semibold underline underline-offset-2';
    button.textContent = 'Reload';
    button.addEventListener('click', () => window.location.reload());
    notice.appendChild(button);

    region.prepend(notice);
    return notice;
};

const refreshRegions = async () => {
    const keys = [...pendingRegions];
    pendingRegions.clear();

    let fresh;
    try {
        const response = await fetch(window.location.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
        });

        // A redirect usually means the record moved out of reach (e.g. it was archived); leave the page as it is
        if (!response.ok || response.redirected) return;

        fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
    } catch (error) {
        console.warn('[LiveRefresh] Could not refresh the page content:', error);
        return;
    }

    keys.forEach((key) => {
        const current = document.querySelector(`[data-live-region="${key}"]`);
        const replacement = fresh.querySelector(`[data-live-region="${key}"]`);
        if (!current || !replacement) return;

        if (isDirty(current)) {
            revealNotice(noticeFor(current));
            return;
        }

        current.innerHTML = replacement.innerHTML;
        takeSnapshot(current);
    });

    document.dispatchEvent(new CustomEvent('live-refresh:updated', { detail: { regions: keys } }));
};

const scheduleRefresh = (key) => {
    pendingRegions.add(key);
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(refreshRegions, REFRESH_DELAY_MS);
};

const initLiveRefresh = () => {
    const targets = document.querySelectorAll('[data-live-region][data-live-events], [data-live-notice][data-live-events]');
    if (targets.length === 0 || !window.Echo) return;

    // One listener per channel and event, shared by every element that asked for it
    const subscriptions = new Map();

    targets.forEach((element) => {
        const channel = element.dataset.liveChannel || DEFAULT_CHANNEL;
        element.dataset.liveEvents.split(',').map((name) => name.trim()).filter(Boolean).forEach((eventName) => {
            const id = `${channel}|${eventName}`;
            if (!subscriptions.has(id)) subscriptions.set(id, { channel, eventName, elements: [] });
            subscriptions.get(id).elements.push(element);
        });
    });

    subscriptions.forEach(({ channel, eventName, elements }) => {
        window.Echo.private(channel).listen(`.${eventName}`, (payload) => {
            elements.forEach((element) => {
                if (!element.isConnected || !matches(element, payload)) return;

                if (element.hasAttribute('data-live-notice')) {
                    revealNotice(element);
                } else {
                    scheduleRefresh(element.dataset.liveRegion);
                }
            });
        });
    });
};

document.addEventListener('DOMContentLoaded', initLiveRefresh);

// Snapshot form values once every DOMContentLoaded enhancer (Tom Select, form scripts) has run
window.addEventListener('load', () => {
    document.querySelectorAll('[data-live-region]').forEach(takeSnapshot);
});
