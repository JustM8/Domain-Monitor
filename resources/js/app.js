import './bootstrap';
import Chart from 'chart.js/auto';

const getCssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const destroyChart = (chart) => {
    if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
    }
};

const initSupportAnalytics = () => {
    const dataEl = document.getElementById('support-analytics-data');
    if (!dataEl) {
        return;
    }

    const canvasIds = [
        'support-analytics-status-chart',
        'support-analytics-ratings-chart',
        'support-analytics-topics-chart',
        'support-analytics-managers-chart',
    ];

    const existingCharts = window.__supportAnalyticsCharts ?? {};
    Object.values(existingCharts).forEach(destroyChart);
    window.__supportAnalyticsCharts = {};

    const data = JSON.parse(dataEl.textContent || '{}');
    const colors = {
        primary: getCssVar('--portal-primary') || '#5b748b',
        accent: getCssVar('--portal-accent') || '#9f7156',
        muted: getCssVar('--portal-muted') || '#6e746f',
        text: getCssVar('--portal-text') || '#232831',
        border: getCssVar('--portal-border') || 'rgba(0,0,0,0.12)',
        surface: getCssVar('--portal-surface-strong') || '#f5f2ec',
    };

    Chart.defaults.color = colors.muted;
    Chart.defaults.font.family = 'Manrope, "Segoe UI", sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    const gridColor = colors.border;
    const commonBar = {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                position: 'bottom',
                align: 'start',
                labels: {
                    boxWidth: 10,
                    boxHeight: 10,
                },
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                grid: { color: gridColor },
                ticks: { precision: 0 },
            },
            y: {
                grid: { display: false },
            },
        },
    };

    const setChart = (id, config) => {
        const canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }

        const chart = new Chart(canvas, config);
        window.__supportAnalyticsCharts[id] = chart;
    };

    setChart('support-analytics-status-chart', {
        type: 'doughnut',
        data: {
            labels: data.status?.labels ?? [],
            datasets: [{
                data: data.status?.values ?? [],
                backgroundColor: [colors.primary, colors.accent, '#7f9db1'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    backgroundColor: 'rgba(20, 24, 32, 0.96)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                },
            },
        },
    });

    setChart('support-analytics-ratings-chart', {
        type: 'bar',
        data: {
            labels: data.ratings?.labels ?? [],
            datasets: [{
                label: 'К-сть звернень',
                data: data.ratings?.values ?? [],
                backgroundColor: [
                    '#d86e64',
                    '#e38f53',
                    '#dcb85c',
                    '#a6bc63',
                    '#7eb77b',
                    '#5aa8a3',
                ],
                borderRadius: 10,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(20, 24, 32, 0.96)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { precision: 0 },
                },
                y: {
                    grid: { display: false },
                },
            },
        },
    });

    setChart('support-analytics-topics-chart', {
        type: 'bar',
        data: {
            labels: data.topics?.labels ?? [],
            datasets: [
                {
                    label: 'Усього',
                    data: data.topics?.tickets ?? [],
                    backgroundColor: colors.primary,
                    borderRadius: 10,
                    borderSkipped: false,
                },
                {
                    label: 'Закриті',
                    data: data.topics?.closed ?? [],
                    backgroundColor: colors.accent,
                    borderRadius: 10,
                    borderSkipped: false,
                },
            ],
        },
        options: {
            ...commonBar,
            scales: {
                ...commonBar.scales,
                x: {
                    ...commonBar.scales.x,
                    stacked: false,
                },
                y: {
                    ...commonBar.scales.y,
                },
            },
        },
    });

    setChart('support-analytics-managers-chart', {
        type: 'bar',
        data: {
            labels: data.managers?.labels ?? [],
            datasets: [
                {
                    label: 'Усього',
                    data: data.managers?.tickets ?? [],
                    backgroundColor: '#6687a3',
                    borderRadius: 10,
                    borderSkipped: false,
                },
                {
                    label: 'Закриті',
                    data: data.managers?.closed ?? [],
                    backgroundColor: '#b07a63',
                    borderRadius: 10,
                    borderSkipped: false,
                },
            ],
        },
        options: {
            ...commonBar,
            scales: {
                ...commonBar.scales,
                x: {
                    ...commonBar.scales.x,
                    stacked: false,
                },
                y: {
                    ...commonBar.scales.y,
                },
            },
        },
    });
};

const bootSupportAnalytics = () => {
    initSupportAnalytics();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSupportAnalytics, { once: true });
} else {
    bootSupportAnalytics();
}

window.addEventListener('load', bootSupportAnalytics, { once: true });
