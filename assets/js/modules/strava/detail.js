/* Strava activity route on a Leaflet map with start and end markers. Used by strava/detail and admin/strava/detail. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const points = JSON.parse(config.points || '[]');
    const start = config.startLat !== '' && config.startLat !== undefined
        ? [parseFloat(config.startLat), parseFloat(config.startLng)]
        : [52.0, 5.0];

    const map = L.map('map').setView(start, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    if (points.length === 0) {
        map.setView([52.0, 5.0], 7);
        return;
    }

    // decodePolyline() gives {lat, lng} objects
    const latLngs = points.map(p => [p.lat, p.lng]);
    const polyline = L.polyline(latLngs, {color: '#FC4C02', weight: 4, opacity: 0.8}).addTo(map);
    map.fitBounds(polyline.getBounds());

    const markerIcon = color => L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-' + color + '.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    L.marker(latLngs[0], {icon: markerIcon('green')}).addTo(map).bindPopup('Start').openPopup();
    L.marker(latLngs[latLngs.length - 1], {icon: markerIcon('red')}).addTo(map).bindPopup('Finish');
});
