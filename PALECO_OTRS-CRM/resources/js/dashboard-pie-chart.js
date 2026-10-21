/**
 * Interactive SVG Pie/Donut Chart Controller for CWD Dashboard
 * Handles mouseover/mousemove/mouseleave, tooltip positioning, slice hover enlargement,
 * and sync with the status legend.
 */
export function initDashboardPieChart() {
    const container = document.getElementById('status-pie-container');
    if (!container) return;

    // Avoid double initialization on the same DOM element
    if (container.dataset.pieInitialized === 'true') return;
    container.dataset.pieInitialized = 'true';

    const svg = container.querySelector('.pie-chart-svg');
    const tooltip = document.getElementById('pie-interactive-tooltip');
    const tooltipDot = document.getElementById('pie-tooltip-dot');
    const tooltipLabel = document.getElementById('pie-tooltip-label');
    const tooltipTotal = document.getElementById('pie-tooltip-total');
    const tooltipPercent = document.getElementById('pie-tooltip-percent');

    const centerValue = document.getElementById('pie-center-value');
    const centerLabel = document.getElementById('pie-center-label');

    const defaultTotal = centerValue ? centerValue.textContent.trim() : '0';
    const defaultLabel = centerLabel ? centerLabel.textContent.trim() : 'Tickets';

    function resetCenter() {
        if (centerValue) centerValue.textContent = defaultTotal;
        if (centerLabel) {
            centerLabel.textContent = defaultLabel;
            centerLabel.className = 'text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-0.5 truncate max-w-[100px] transition-colors duration-150';
        }
        if (tooltip) {
            tooltip.classList.add('opacity-0');
        }
        if (svg) svg.classList.remove('has-hover');

        container.querySelectorAll('.pie-slice').forEach(s => s.classList.remove('is-active'));
        container.querySelectorAll('.status-legend-item').forEach(l => l.classList.remove('is-highlighted', 'bg-slate-100'));
    }

    function activateStatus(statusKey, clientX, clientY) {
        if (!statusKey) return;

        const slice = container.querySelector(`.pie-slice[data-status-key="${statusKey}"]`);
        const legendItem = container.querySelector(`.status-legend-item[data-status-key="${statusKey}"]`);

        container.querySelectorAll('.pie-slice').forEach(s => s.classList.remove('is-active'));
        container.querySelectorAll('.status-legend-item').forEach(l => l.classList.remove('is-highlighted', 'bg-slate-100'));

        if (slice) {
            slice.classList.add('is-active');
            if (svg) svg.classList.add('has-hover');

            const label = slice.dataset.label || '';
            const total = slice.dataset.total || '0';
            const percent = slice.dataset.percent || '';
            const color = slice.dataset.color || '#64748b';

            if (centerValue) centerValue.textContent = Number(total).toLocaleString();
            if (centerLabel) {
                centerLabel.textContent = label;
                centerLabel.className = 'text-[11px] font-bold text-slate-700 mt-0.5 truncate max-w-[110px] transition-colors duration-150';
            }

            if (tooltip && tooltipLabel && tooltipTotal && tooltipPercent && tooltipDot) {
                tooltipDot.style.backgroundColor = color;
                tooltipLabel.textContent = label;
                tooltipTotal.textContent = `${total} ${total === '1' ? 'ticket' : 'tickets'}`;
                tooltipPercent.textContent = percent;

                if (clientX !== undefined && clientY !== undefined) {
                    const rect = container.getBoundingClientRect();
                    const x = clientX - rect.left;
                    const y = clientY - rect.top;

                    tooltip.style.left = `${Math.round(x)}px`;
                    tooltip.style.top = `${Math.round(y - 14)}px`;
                }

                tooltip.classList.remove('opacity-0');
            }
        }

        if (legendItem) {
            legendItem.classList.add('is-highlighted', 'bg-slate-100');
        }
    }

    // Event delegation on container for hover
    container.addEventListener('mouseover', (e) => {
        const slice = e.target.closest('.pie-slice');
        const legendItem = e.target.closest('.status-legend-item');

        if (slice) {
            activateStatus(slice.dataset.statusKey, e.clientX, e.clientY);
        } else if (legendItem) {
            const legendRect = legendItem.getBoundingClientRect();
            activateStatus(legendItem.dataset.statusKey, legendRect.left + legendRect.width / 2, legendRect.top);
        }
    });

    container.addEventListener('mousemove', (e) => {
        const slice = e.target.closest('.pie-slice');
        if (slice && tooltip) {
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            tooltip.style.left = `${Math.round(x)}px`;
            tooltip.style.top = `${Math.round(y - 14)}px`;
        }
    });

    container.addEventListener('mouseout', (e) => {
        const toElement = e.relatedTarget;
        if (!toElement || !container.contains(toElement)) {
            resetCenter();
        } else if (e.target.closest('.pie-slice') && !toElement.closest('.pie-slice') && !toElement.closest('.status-legend-item')) {
            resetCenter();
        } else if (e.target.closest('.status-legend-item') && !toElement.closest('.status-legend-item') && !toElement.closest('.pie-slice')) {
            resetCenter();
        }
    });

    // Clicking a slice navigates to filtered tickets
    container.addEventListener('click', (e) => {
        const slice = e.target.closest('.pie-slice');
        if (slice && slice.dataset.url) {
            window.location.href = slice.dataset.url;
        }
    });
}

// Global initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardPieChart);
} else {
    initDashboardPieChart();
}

// Re-draw after each Livewire update. Livewire may already be running when this bundle loads,
// so register immediately when possible instead of relying only on livewire:initialized.
let pieChartHookRegistered = false;
const registerPieChartHook = () => {
    if (pieChartHookRegistered || typeof window.Livewire === 'undefined' || !window.Livewire.hook) return;
    pieChartHookRegistered = true;

    // 'morphed' runs after Livewire has patched the DOM; the commit hook's succeed() never fires in this Livewire version
    window.Livewire.hook('morphed', () => {
        const container = document.getElementById('status-pie-container');
        if (container) {
            delete container.dataset.pieInitialized;
        }
        initDashboardPieChart();
    });
};

registerPieChartHook();
document.addEventListener('livewire:init', registerPieChartHook);
document.addEventListener('livewire:initialized', registerPieChartHook);

