/*
 * Confirmation modal of the admin (#deleteModal in templates/admin/base.html.twig).
 * A trigger button opens it with data-bs-toggle="modal" data-bs-target="#deleteModal" and sets:
 *   data-delete-url, data-csrf-token, data-delete-message
 *   optional data-confirm-title / -label / -icon / -class to reuse it for other actions (e.g. archive)
 * On confirm it POSTs to the URL and reloads the page when the JSON answer has success: true.
 */
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteModal');
    if (!deleteModal) {
        return;
    }

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button || !button.hasAttribute('data-delete-url')) {
            return;
        }
        const url = button.getAttribute('data-delete-url');
        const csrfToken = button.getAttribute('data-csrf-token');

        document.getElementById('deleteModalMessage').textContent = button.getAttribute('data-delete-message') || 'Weet je zeker dat je dit item wilt verwijderen?';
        document.getElementById('deleteModalTitle').textContent = button.getAttribute('data-confirm-title') || 'Verwijderen bevestigen';
        document.getElementById('confirmDeleteLabel').textContent = button.getAttribute('data-confirm-label') || 'Verwijderen';
        document.getElementById('confirmDeleteIcon').className = 'fas ' + (button.getAttribute('data-confirm-icon') || 'fa-trash') + ' me-1';

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.className = 'btn ' + (button.getAttribute('data-confirm-class') || 'btn-danger');
        confirmBtn.onclick = function () {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ _token: csrfToken })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        // AppDialog closes this modal first
                        AppDialog.alert(data.message || 'Er is een fout opgetreden');
                    }
                })
                .catch(() => {
                    AppDialog.alert('Er is een fout opgetreden');
                });
        };
    });
});
