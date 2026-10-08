/*
 * One e-mail message: mark read/unread, delete, move to another folder. Used by admin/imap/message_show.
 * The script tag sets data-read-url, data-unread-url, data-delete-url, data-move-url, data-move-token and data-back-url.
 */
const imapMessageConfig = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function () {
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        }).then(res => res.json());
    }

    function markButton(selector, url) {
        const button = document.querySelector(selector);
        if (!button) {
            return;
        }
        button.addEventListener('click', function () {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Marking...';
            post(url, '_token=' + encodeURIComponent(button.dataset.csrfToken)).then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
    }

    markButton('.mark-read', imapMessageConfig.readUrl);
    markButton('.mark-unread', imapMessageConfig.unreadUrl);

    const deleteBtn = document.querySelector('.delete-msg');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            if (!confirm('Delete message "' + deleteBtn.dataset.subject + '"?')) {
                return;
            }
            post(imapMessageConfig.deleteUrl, '_token=' + encodeURIComponent(deleteBtn.dataset.csrfToken)).then(data => {
                if (data.success) {
                    window.location.href = imapMessageConfig.backUrl;
                }
            });
        });
    }

    const moveForm = document.getElementById('moveForm');
    if (moveForm) {
        moveForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const folder = new FormData(this).get('folder');
            post(imapMessageConfig.moveUrl, '_token=' + encodeURIComponent(imapMessageConfig.moveToken) + '&folder=' + encodeURIComponent(folder)).then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('moveModal')).hide();
                    location.reload();
                } else {
                    alert('Move failed: ' + data.message);
                }
            });
        });
    }
});
