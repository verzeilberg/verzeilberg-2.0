/* Strava activity list: loads the activities of the chosen month (table + chart). Used by strava/index and admin/strava/index. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const activitiesBody = document.getElementById('activitiesBody');
    const activityCount = document.getElementById('activityCount');
    const chartCtx = document.getElementById('activitiesChart').getContext('2d');
    let chart = null;

    function loadActivities() {
        const formData = new FormData(filterForm);
        
        fetch(config.chartUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTable(data.activities);
                renderChart(data.activities);
                activityCount.textContent = data.activities.length;
            }
        })
        .catch(error => {
            console.error('Error loading activities:', error);
        });
    }

    function renderTable(activities) {
        activitiesBody.innerHTML = '';
        
        if (activities.length === 0) {
            activitiesBody.innerHTML = '<tr><td colspan="9" class="text-center py-4">Geen activiteiten gevonden</td></tr>';
            return;
        }

        activities.forEach(activity => {
            const row = document.createElement('tr');
            row.className = 'activity-row';
            row.innerHTML = `
                <td>${formatDate(activity.start_date)}</td>
                <td><strong>${escapeHtml(activity.name)}</strong></td>
                <td><span class="badge bg-secondary type-badge">${getTypeLabel(activity.type)}</span></td>
                <td>${activity.distance.toFixed(2)} km</td>
                <td>${formatTime(activity.moving_time)}</td>
                <td>${activity.total_elevation_gain.toFixed(0)} m</td>
                <td>${activity.average_speed ? (activity.average_speed * 3.6).toFixed(1) + ' km/h' : '-'}</td>
                <td>${activity.average_heartrate ? Math.round(activity.average_heartrate) + ' bpm' : '-'}</td>
                <td>
                    <a href="${config.detailUrl.replace('__ID__', encodeURIComponent(activity.id))}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            `;
            activitiesBody.appendChild(row);
        });
    }

    function renderChart(activities) {
        if (chart) {
            chart.destroy();
        }

        // Group by date
        const dailyData = {};
        activities.forEach(a => {
            const date = a.start_date.split(' ')[0];
            if (!dailyData[date]) {
                dailyData[date] = { distance: 0, time: 0, count: 0 };
            }
            dailyData[date].distance += a.distance;
            dailyData[date].time += a.moving_time;
            dailyData[date].count++;
        });

        const labels = Object.keys(dailyData).sort();
        const distances = labels.map(d => dailyData[d].distance);
        const times = labels.map(d => dailyData[d].time / 60); // minutes
        const counts = labels.map(d => dailyData[d].count);

        chart = new Chart(chartCtx, {
            type: 'bar',
            data: {
                labels: labels.map(d => formatDateShort(d)),
                datasets: [
                    {
                        label: 'Afstand (km)',
                        data: distances,
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Tijd (min)',
                        data: times,
                        type: 'line',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Afstand (km)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Tijd (min)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('nl-NL', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatDateShort(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('nl-NL', { day: '2-digit', month: '2-digit' });
    }

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
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

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        loadActivities();
    });

    // Initial load
    loadActivities();
});
