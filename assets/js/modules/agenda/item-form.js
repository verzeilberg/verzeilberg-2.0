/* Agenda item form (admin/agenda/item_form): hides the times for a whole-day item, and the Google place search
 * (initAgendaLocationSearch is the callback of the Maps script, so it must stay global and load before that script). */
// Whole day: no times
document.addEventListener('DOMContentLoaded', function() {
    const allDay = document.querySelector('.js-all-day');
    if (!allDay) return;
    const toggle = function() {
        document.querySelectorAll('.js-time-col').forEach(col => col.classList.toggle('d-none', allDay.checked));
    };
    allDay.addEventListener('change', toggle);
    toggle();
});

// Places Autocomplete (Places API New): the chosen place's name and address go into the location field
async function initAgendaLocationSearch() {
    const container = document.getElementById('locationSearch');
    const location = document.getElementById('agenda_item_location');
    if (!container || !location) return;
    try {
        const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
        const search = new PlaceAutocompleteElement();
        search.placeholder = 'Search a place or address…';
        // Light like the rest of the admin (Google's element follows the OS dark mode otherwise)
        search.style.colorScheme = 'light';
        search.style.width = '100%';
        container.appendChild(search);
        // Google refused the search (e.g. "Places API (New)" not enabled for the key): say so
        search.addEventListener('gmp-error', function() {
            const error = document.getElementById('locationSearchError');
            error.textContent = 'Google place search is not available for this API key (enable "Places API (New)" in Google Cloud). You can still type the location below.';
            error.classList.remove('d-none');
        });
        search.addEventListener('gmp-select', async function({ placePrediction }) {
            const place = placePrediction.toPlace();
            await place.fetchFields({ fields: ['displayName', 'formattedAddress'] });
            const name = place.displayName || '';
            const address = place.formattedAddress || '';
            // "Sporthal De Bloemhof, Bloemhof 1, 1431 XX Aalsmeer"; no duplicate when the name is the address
            location.value = (name && !address.startsWith(name) ? name + ', ' : '') + address;
        });
    } catch (error) {
        // Without a working key the normal location field still works
        container.closest('.col-md-6')?.querySelector('label[for="locationSearch"]')?.remove();
        container.remove();
        console.warn('Google place search unavailable:', error);
    }
}
