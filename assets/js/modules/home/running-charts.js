/* Home page running block: km per month of the running year (bars), and the km added up per month against last
   year (lines). Colors checked with the palette validator on white: lightness, chroma, colorblind separation and
   >= 3:1 contrast. Last year is also dashed, so the two lines differ without color too. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const THIS_YEAR = '#2563b8'; // the same blue as the bars on /running
    const LAST_YEAR = '#d9622b';
    const INK = '#17212f';
    const INK_MUTED = '#5a6b80';
    const GRID = '#e1e8ef';
    const km = value => value.toLocaleString('nl-NL', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' km';
    const addUp = values => values.reduce((sums, value) => [...sums, (sums.at(-1) ?? 0) + value], []);

    const months = JSON.parse(config.months);
    const year = config.year;
    const monthKm = JSON.parse(config.monthKm);
    const shown = parseInt(config.shownMonths, 10);

    const scales = {
        x: {grid: {display: false}, ticks: {color: INK_MUTED}},
        y: {beginAtZero: true, grid: {color: GRID}, border: {display: false}, ticks: {color: INK_MUTED, callback: value => value.toLocaleString('nl-NL')}},
    };

    // One series: no legend, the title names it
    new Chart(document.getElementById('homeMonthChart'), {
        type: 'bar',
        data: {labels: months, datasets: [{data: monthKm, backgroundColor: THIS_YEAR, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 28}]},
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {legend: {display: false}, tooltip: {callbacks: {label: context => km(context.parsed.y)}}},
            scales: scales,
        },
    });

    const lines = [{
        label: year,
        data: addUp(monthKm).slice(0, shown),
        borderColor: THIS_YEAR,
        backgroundColor: THIS_YEAR,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 5,
        tension: 0,
    }];
    if (config.previousMonthKm) {
        lines.push({
            label: String(parseInt(year, 10) - 1),
            data: addUp(JSON.parse(config.previousMonthKm)),
            borderColor: LAST_YEAR,
            backgroundColor: LAST_YEAR,
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHoverRadius: 5,
            tension: 0,
        });
    }

    // The year at the end of each line, in text ink (the line's color stays on the line)
    const endLabels = {
        id: 'endLabels',
        afterDatasetsDraw(chart) {
            const ctx = chart.ctx;
            ctx.save();
            ctx.font = '600 12px Barlow, system-ui, sans-serif';
            ctx.fillStyle = INK;
            ctx.textBaseline = 'middle';
            chart.data.datasets.forEach((dataset, index) => {
                const points = chart.getDatasetMeta(index).data;
                const last = points.at(-1);
                if (last) {
                    const text = dataset.label;
                    const x = Math.min(last.x + 6, chart.chartArea.right - ctx.measureText(text).width);
                    ctx.fillText(text, x, last.y - 10);
                }
            });
            ctx.restore();
        },
    };

    new Chart(document.getElementById('homeCumulativeChart'), {
        type: 'line',
        data: {labels: months, datasets: lines},
        plugins: [endLabels],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {padding: {top: 16, right: 8}},
            interaction: {mode: 'index', intersect: false},
            plugins: {
                legend: {display: lines.length > 1, position: 'top', align: 'start', labels: {color: INK, boxWidth: 24, boxHeight: 2, usePointStyle: false}},
                tooltip: {callbacks: {label: context => context.dataset.label + ': ' + km(context.parsed.y)}},
            },
            scales: scales,
        },
    });
});
