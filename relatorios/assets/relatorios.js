(() => {
    'use strict';

    document.documentElement.classList.add('js');
    const dataEl = document.getElementById('report-data');
    const report = dataEl ? JSON.parse(dataEl.textContent || '{}') : null;

    const revealItems = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    const locale = 'pt-BR';
    const formatNumber = (value, decimals = 0) => new Intl.NumberFormat(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);

    const formatDuration = (seconds) => {
        if (!Number.isFinite(seconds)) return '—';
        if (seconds < 60) return `${Math.round(seconds)}s`;
        if (seconds < 3600) return `${Math.max(1, Math.round(seconds / 60))} min`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.round((seconds % 3600) / 60);
        return minutes ? `${hours}h ${minutes}min` : `${hours}h`;
    };

    document.querySelectorAll('[data-countup]').forEach((el) => {
        const target = Number(el.dataset.countup || 0);
        const decimals = Number(el.dataset.decimals || 0);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            el.textContent = formatNumber(target, decimals);
            return;
        }
        const start = performance.now();
        const duration = 850;
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatNumber(target * eased, decimals);
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });

    document.querySelectorAll('[data-duration]').forEach((el) => {
        const seconds = Number(el.dataset.duration || 0);
        const target = seconds;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || target <= 0) return;
        const start = performance.now();
        const duration = 850;
        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatDuration(target * eased);
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });

    const monthInput = document.getElementById('mes');
    if (monthInput) {
        monthInput.addEventListener('change', () => monthInput.form?.requestSubmit());
    }

    if (!report || !report.overview || typeof Chart === 'undefined') return;

    if (typeof ChartDataLabels !== 'undefined') {
        Chart.register(ChartDataLabels);
    }
    Chart.defaults.color = '#a6a0a0';
    Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 10;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 7;
    Chart.defaults.plugins.legend.labels.boxHeight = 7;
    Chart.defaults.plugins.legend.labels.padding = 14;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(12,12,15,.96)';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,.12)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 9;
    Chart.defaults.plugins.tooltip.titleFont = { family: "'Sora', sans-serif", weight: '600' };

    const colors = {
        red: '#ed3c53', redSoft: 'rgba(237,60,83,.22)', orange: '#ff9f55',
        teal: '#49d9b0', blue: '#6ea8ff', purple: '#b790ff', yellow: '#f6c85f',
        grey: '#68666b', ink: '#f8f4ef',
    };
    const noGrid = { grid: { display: false }, border: { display: false } };

    const activityCanvas = document.getElementById('activityChart');
    if (activityCanvas) {
        const ctx = activityCanvas.getContext('2d');
        const gradientIn = ctx.createLinearGradient(0, 0, 0, 340);
        gradientIn.addColorStop(0, 'rgba(73,217,176,.32)');
        gradientIn.addColorStop(1, 'rgba(73,217,176,0)');
        const gradientOut = ctx.createLinearGradient(0, 0, 0, 340);
        gradientOut.addColorStop(0, 'rgba(237,60,83,.28)');
        gradientOut.addColorStop(1, 'rgba(237,60,83,0)');
        new Chart(activityCanvas, {
            type: 'line',
            data: {
                labels: report.daily.map((day) => day.label),
                datasets: [
                    { label: 'Recebidas', data: report.daily.map((day) => day.inbound), borderColor: colors.teal, backgroundColor: gradientIn, fill: true, tension: .36, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                    { label: 'Enviadas', data: report.daily.map((day) => day.outbound), borderColor: colors.red, backgroundColor: gradientOut, fill: true, tension: .36, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                    { label: 'Novas conversas', data: report.daily.map((day) => day.conversations), borderColor: colors.orange, backgroundColor: colors.orange, type: 'bar', borderRadius: 4, maxBarThickness: 8, yAxisID: 'y1', order: 2 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, datalabels: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.055)' }, ticks: { precision: 0 } },
                    y1: { beginAtZero: true, position: 'right', ...noGrid, ticks: { precision: 0, display: false } },
                },
            },
        });
    }

    const sourceCanvas = document.getElementById('sourceChart');
    if (sourceCanvas) {
        const acquisition = report.channels;
        const palette = { meta: colors.red, google: colors.blue, instagram: colors.purple, whatsapp: colors.teal, outros: '#9c8f89' };
        const acquisitionTotal = acquisition.reduce((total, channel) => total + channel.conversations, 0);
        const sourceCenter = document.getElementById('sourceCenter');
        if (sourceCenter) {
            sourceCenter.innerHTML = `<strong>${formatNumber(acquisitionTotal)}</strong><span>com origem</span>`;
        }
        new Chart(sourceCanvas, {
            type: 'doughnut',
            data: {
                labels: acquisition.map((channel) => channel.label),
                datasets: [{ data: acquisition.map((channel) => channel.conversations), backgroundColor: acquisition.map((channel) => palette[channel.key] || colors.grey), borderWidth: 0, hoverOffset: 5 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '73%',
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: { display: false },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw)} contatos` } },
                },
            },
        });
    }

    const channelCanvas = document.getElementById('channelChart');
    if (channelCanvas) {
        const acquisition = report.channels;
        new Chart(channelCanvas, {
            type: 'bar',
            data: {
                labels: acquisition.map((channel) => channel.label),
                datasets: [{ label: 'Conversão', data: acquisition.map((channel) => channel.conversion_pct), backgroundColor: acquisition.map((channel) => channel.key === 'meta' ? colors.red : channel.key === 'google' ? colors.blue : channel.key === 'instagram' ? colors.purple : colors.teal), borderRadius: 6, maxBarThickness: 28 }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { display: true, anchor: 'end', align: 'right', color: colors.ink, font: { weight: '700' }, formatter: (value) => `${formatNumber(value, 1)}%` },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw, 1)}% com agenda vinculada` } },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { callback: (value) => `${value}%`, precision: 0 } },
                    y: { ...noGrid, ticks: { color: colors.ink } },
                },
                layout: { padding: { right: 52 } },
            },
        });
    }

    const agendaCanvas = document.getElementById('agendaChart');
    if (agendaCanvas) {
        const statusMeta = {
            finalizado: ['Finalizadas', colors.teal],
            confirmado: ['Confirmadas', colors.blue],
            concluido: ['Concluídas', colors.teal],
            cancelado: ['Canceladas', colors.yellow],
            falta: ['Faltas', colors.red],
        };
        const groups = {};
        (report.agenda?.date?.statuses || []).forEach((item) => {
            const meta = statusMeta[item.status] || ['Outros', colors.grey];
            if (!groups[meta[0]]) groups[meta[0]] = { label: meta[0], total: 0, color: meta[1] };
            groups[meta[0]].total += Number(item.total);
        });
        const rows = Object.values(groups);
        new Chart(agendaCanvas, {
            type: 'doughnut',
            data: { labels: rows.map((row) => row.label), datasets: [{ data: rows.map((row) => row.total), backgroundColor: rows.map((row) => row.color), borderWidth: 0, hoverOffset: 5 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '73%',
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: { display: false },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw)} sessões` } },
                },
            },
        });
    }

    const objectionCanvas = document.getElementById('objectionChart');
    if (objectionCanvas) {
        new Chart(objectionCanvas, {
            type: 'bar',
            data: {
                labels: report.objections.map((item) => item.label),
                datasets: [{ data: report.objections.map((item) => item.conversations), backgroundColor: report.objections.map((item) => item.key === 'price_objection' ? colors.red : item.key === 'price' ? colors.orange : colors.blue), borderRadius: 6, maxBarThickness: 20 }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { display: true, anchor: 'end', align: 'right', color: colors.ink, font: { weight: '700' }, formatter: (value, context) => `${formatNumber(value)} · ${formatNumber(report.objections[context.dataIndex].pct, 1)}%` },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw)} conversas` } },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { precision: 0 } },
                    y: { ...noGrid, ticks: { color: colors.ink } },
                },
                layout: { padding: { right: 55 } },
            },
        });
    }

    const responseCanvas = document.getElementById('responseChart');
    if (responseCanvas) {
        new Chart(responseCanvas, {
            type: 'bar',
            data: {
                labels: report.response.map((item) => item.label),
                datasets: [{ data: report.response.map((item) => item.total), backgroundColor: report.response.map((item, index) => index < 3 ? colors.teal : index < 5 ? colors.yellow : colors.red), borderRadius: 6, maxBarThickness: 34 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { display: true, anchor: 'end', align: 'top', color: colors.ink, font: { weight: '700' }, formatter: (value) => formatNumber(value) },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw)} respostas · ${formatNumber(report.response[item.dataIndex].pct, 1)}%` } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 8.5 } } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { precision: 0 } },
                },
            },
        });
    }

    const pipelineCanvas = document.getElementById('pipelineChart');
    if (pipelineCanvas) {
        const palette = { novo: colors.orange, agendado: colors.blue, fechado: colors.teal, pre_agendado: colors.purple, perdido: colors.red, sem_etapa: colors.grey, sem_lead: colors.grey };
        new Chart(pipelineCanvas, {
            type: 'bar',
            data: {
                labels: report.pipeline.map((item) => item.label),
                datasets: [{ data: report.pipeline.map((item) => item.total), backgroundColor: report.pipeline.map((item) => palette[item.key] || colors.grey), borderRadius: 6, maxBarThickness: 20 }],
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { display: true, anchor: 'end', align: 'right', color: colors.ink, font: { weight: '700' }, formatter: (value) => formatNumber(value) },
                    tooltip: { callbacks: { label: (item) => ` ${formatNumber(item.raw)} contatos` } },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { precision: 0 } },
                    y: { ...noGrid, ticks: { color: colors.ink } },
                },
                layout: { padding: { right: 28 } },
            },
        });
    }
})();


