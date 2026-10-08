/* Event location on a Leaflet map (event/detail). */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const lat = parseFloat(config.latitude);
    const lng = parseFloat(config.longitude);
    if (isNaN(lat) || isNaN(lng)) {
        return;
    }

    const map = L.map('eventMap').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // The title as text, not HTML
    const popup = document.createElement('span');
    popup.textContent = config.title;
    L.marker([lat, lng]).addTo(map).bindPopup(popup).openPopup();
});
