/*
 * Delete a row of an admin list after confirming in the shared #deleteModal (admin base), posting the
 * CSRF token as a form field. Used by the category, menu, role, route permission and user lists.
 *   <button class="js-ajax-delete" data-delete-url="…" data-csrf-token="…" data-delete-message="Delete …?"
 *           data-error-message="Failed to delete …">
 * (No data-bs-toggle: this is not the JSON variant of components/delete-modal.js.)
 */
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('deleteModal');
    if (!modalElement) {
        return;
    }
    const deleteModal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let current = null;

    document.querySelectorAll('.js-ajax-delete').forEach(function (button) {
        button.addEventListener('click', function () {
            current = this;
            document.getElementById('deleteModalMessage').textContent = this.dataset.deleteMessage;
            deleteModal.show();
        });
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
        if (!current || !current.dataset.csrfToken) {
            return;
        }
        const button = current;
        fetch(button.dataset.deleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '_token=' + encodeURIComponent(button.dataset.csrfToken)
        })
            .then(res => res.json())
            .then(data => {
                deleteModal.hide();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || button.dataset.errorMessage || 'Failed to delete');
                }
            });
    });
});
