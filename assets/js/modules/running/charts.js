/* Running overview (running/index): km per month of the chosen year, and km per year with that year highlighted.
   One series per chart, so one hue and no legend; the exact numbers are in the tooltip and the table below each chart. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const BAR = '#2563b8';       // checked: lightness, chroma and >= 3:1 on white
    const BAR_MUTED = '#7896c6'; // the other years, still >= 3:1 on white
    const INK_MUTED = '#5a6b80';
    const GRID = '#e1e8ef';
    const km = value => value.toLocaleString('nl-NL', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' km';

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {display: false},
            tooltip: {callbacks: {label: context => km(context.parsed.y)}},
        },
        scales: {
            x: {grid: {display: false}, ticks: {color: INK_MUTED}},
            y: {beginAtZero: true, grid: {color: GRID}, border: {display: false}, ticks: {color: INK_MUTED, callback: value => value.toLocaleString('nl-NL')}},
        },
    };
    const bars = (labels, data, colors) => ({
        type: 'bar',
        data: {labels: labels, datasets: [{data: data, backgroundColor: colors, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 32}]},
        options: options,
    });

    new Chart(document.getElementById('monthChart'), bars(JSON.parse(config.months), JSON.parse(config.monthKm), BAR));

    const years = JSON.parse(config.years).map(String);
    new Chart(document.getElementById('yearChart'), bars(
        years,
        Object.values(JSON.parse(config.yearKm)),
        years.map(year => year === config.year ? BAR : BAR_MUTED)
    ));
});
