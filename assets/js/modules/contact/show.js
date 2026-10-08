/*
 * Contact message detail (admin/contact/show): the shared #deleteModal submits a normal POST form
 * (data-delete-url + data-delete-token on the trigger button), so the controller can redirect with a flash.
 */
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteModal');
    if (!deleteModal) {
        return;
    }
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        deleteModal.querySelector('#deleteModalMessage').textContent = button.getAttribute('data-delete-message');

        deleteModal.querySelector('#confirmDeleteBtn').onclick = function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = button.getAttribute('data-delete-url');

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = button.getAttribute('data-delete-token');
            form.appendChild(tokenInput);

            document.body.appendChild(form);
            form.submit();
        };
    });
});
