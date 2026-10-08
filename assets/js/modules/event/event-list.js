/* Event list (admin/event/index): deletes an event after confirming in the shared #deleteModal. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    let deleteEventId = null;
    let deleteEventToken = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.delete-event').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteEventId = this.dataset.eventId;
            deleteEventToken = this.dataset.csrfToken;
            const eventTitle = this.dataset.eventTitle;
            document.getElementById('deleteModalMessage').textContent = 'Are you sure you want to delete the event "' + eventTitle + '"? This action cannot be undone.';
            deleteModal.show();
        });
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deleteEventId || !deleteEventToken) return;
        
        fetch(config.deleteUrl.replace('__ID__', deleteEventId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '_token=' + encodeURIComponent(deleteEventToken)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                deleteModal.hide();
                location.reload();
            } else {
                deleteModal.hide();
                alert(data.message || 'Failed to delete event');
            }
        });
    });
});
