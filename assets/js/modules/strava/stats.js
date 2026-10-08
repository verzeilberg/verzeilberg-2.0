/* Strava statistics: activities per year and per type (Chart.js). Used by strava/stats and admin/strava/stats. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    // Year chart
    const yearCtx = document.getElementById('yearChart').getContext('2d');
    const years = JSON.parse(config.years);
    const statsByYear = JSON.parse(config.statsByYear);
    
    const yearLabels = years.slice().reverse();
    const yearCounts = yearLabels.map(y => statsByYear[y]?.count || 0);
    const yearDistances = yearLabels.map(y => statsByYear[y]?.totalDistance || 0);
    const yearTimes = yearLabels.map(y => (statsByYear[y]?.totalTime || 0) / 3600); // hours
    const yearElevation = yearLabels.map(y => statsByYear[y]?.totalElevation || 0);

    new Chart(yearCtx, {
        type: 'bar',
        data: {
            labels: yearLabels,
            datasets: [
                {
                    label: 'Activiteiten',
                    data: yearCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Afstand (km)',
                    data: yearDistances,
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'y1'
                },
                {
                    label: 'Tijd (uur)',
                    data: yearTimes,
                    type: 'line',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Aantal activiteiten' },
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Afstand / Tijd' },
                    grid: { drawOnChartArea: false },
                    beginAtZero: true
                }
            }
        }
    });

    // Type distribution chart
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    
    // Aggregate all types
    const typeTotals = {};
    for (const year of years) {
        const stats = statsByYear[year];
        if (stats?.byType) {
            for (const [type, data] of Object.entries(stats.byType)) {
                if (!typeTotals[type]) {
                    typeTotals[type] = { count: 0, distance: 0, time: 0 };
                }
                typeTotals[type].count += data.count;
                typeTotals[type].distance += data.distance;
                typeTotals[type].time += data.time;
            }
        }
    }

    const typeLabels = Object.keys(typeTotals);
    const typeCounts = typeLabels.map(t => typeTotals[t].count);
    const colors = [
        'rgba(255, 99, 132, 0.7)',
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)'
    ];
    const borderColors = [
        'rgba(255, 99, 132, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)'
    ];

    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: typeLabels.map(t => getTypeLabel(t)),
            datasets: [{
                data: typeCounts,
                backgroundColor: colors.slice(0, typeLabels.length),
                borderColor: borderColors.slice(0, typeLabels.length),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return `${h}u ${m}m`;
    }

    function getTypeLabel(type) {
        const types = {
            'Run': 'Hardlopen',
            'Ride': 'Fietsen',
            'Swim': 'Zwemmen',
            'Walk': 'Wandelen',
            'Hike': 'Wandelen',
            'VirtualRide': 'Virtueel fietsen',
            'VirtualRun': 'Virtueel hardlopen'
        };
        return types[type] || type;
    }
});
