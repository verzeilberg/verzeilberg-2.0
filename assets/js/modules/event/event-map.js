/* Event location on a Leaflet map (event/detail); with my Strava race, its route instead. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const route = JSON.parse(config.route || '[]').map(p => [p.lat, p.lng]);
    const lat = parseFloat(config.latitude);
    const lng = parseFloat(config.longitude);
    if (route.length === 0 && (isNaN(lat) || isNaN(lng))) {
        return;
    }

    const map = L.map('eventMap');

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    if (route.length > 0) {
        const line = L.polyline(route, {color: '#FC4C02', weight: 4, opacity: 0.8}).addTo(map);
        map.fitBounds(line.getBounds(), {padding: [16, 16]});
        return;
    }

    map.setView([lat, lng], 13);
    // The title as text, not HTML
    const popup = document.createElement('span');
    popup.textContent = config.title;
    L.marker([lat, lng]).addTo(map).bindPopup(popup).openPopup();
});
