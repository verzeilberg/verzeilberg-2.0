/* Agenda calendar (agenda/_calendar, included by agenda/index and admin/agenda/index):
 * filter checkboxes, the item details modal with Google Maps, and deleting an item from the modal (admin). */
document.addEventListener('DOMContentLoaded', function() {
    // Filter: reload as soon as an agenda is (un)ticked
    document.querySelectorAll('.js-agenda-filter input[type="checkbox"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() { checkbox.form.submit(); });
    });

    // Item details in the modal (values are set as text, never as HTML)
    const modal = document.getElementById('agendaItemModal');
    if (!modal) return;
    let current = null;
    modal.addEventListener('show.bs.modal', function(event) {
        current = event.relatedTarget;
        if (!current) return;
        modal.querySelector('.modal-title').textContent = current.dataset.title;
        // Google Maps for the location (URLs built here, so the location is always encoded)
        const location = current.dataset.location || '';
        modal.querySelector('[data-wrapper="maps"]').classList.toggle('d-none', location === '');
        if (location !== '') {
            const query = encodeURIComponent(location);
            modal.querySelector('.js-agenda-maps-link').href = 'https://www.google.com/maps/search/?api=1&query=' + query;
            modal.querySelector('.js-agenda-route-link').href = 'https://www.google.com/maps/dir/?api=1&destination=' + query;
            const map = modal.querySelector('.js-agenda-map');
            if (map) map.src = map.dataset.embedUrl + '&q=' + query;
        }

        ['when', 'agenda', 'location', 'description'].forEach(function(name) {
            modal.querySelector('[data-field="' + name + '"]').textContent = current.dataset[name] || '';
            const wrapper = modal.querySelector('[data-wrapper="' + name + '"]');
            if (wrapper) wrapper.classList.toggle('d-none', !current.dataset[name]);
        });
        const fromGoogle = current.dataset.google === '1';
        const edit = modal.querySelector('.js-agenda-item-edit');
        if (edit) {
            edit.href = current.dataset.editUrl || '#';
            edit.classList.toggle('d-none', fromGoogle);
        }
        modal.querySelector('.js-agenda-item-delete')?.classList.toggle('d-none', fromGoogle);
        modal.querySelector('.js-agenda-item-google')?.classList.toggle('d-none', !fromGoogle);
    });

    const deleteButton = modal.querySelector('.js-agenda-item-delete');
    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            if (!current) return;
            // Hand over to the shared confirmation modal of the admin layout (components/delete-modal.js)
            const confirmTrigger = document.createElement('button');
            confirmTrigger.dataset.deleteUrl = current.dataset.deleteUrl;
            confirmTrigger.dataset.csrfToken = current.dataset.csrfToken;
            confirmTrigger.dataset.deleteMessage = 'Delete "' + current.dataset.title + '" from the agenda?';
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show(confirmTrigger);
        });
    }
});
