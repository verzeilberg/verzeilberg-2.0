/*
 * Message list of a mailbox folder: mark read/unread, delete (via the admin #deleteModal), sync the folder,
 * bulk delete in the Trash and refresh the folder list. Used by admin/imap/messages.
 * The script tag sets data-read-url, data-unread-url, data-delete-url, data-sync-url, data-folders-url (with __ID__),
 * data-messages-url (with __ID__ and __FOLDER__), data-current-folder and data-folder-count.
 */
const imapMessagesConfig = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function () {
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        });
    }

    // Mark as read / unread
    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            post(imapMessagesConfig.readUrl.replace('__ID__', this.dataset.id), '_token=' + encodeURIComponent(this.dataset.csrfToken))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        row.classList.remove('table-active', 'fw-bold');
                        const badge = document.querySelector('#folderList .list-group-item.active .badge');
                        if (badge) {
                            badge.textContent = parseInt(badge.textContent) - 1;
                        }
                    }
                });
        });
    });

    document.querySelectorAll('.mark-unread').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            post(imapMessagesConfig.unreadUrl.replace('__ID__', this.dataset.id), '_token=' + encodeURIComponent(this.dataset.csrfToken))
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        row.classList.add('table-active', 'fw-bold');
                    }
                });
        });
    });

    // Delete one message
    let deleteId = null;
    let deleteToken = null;
    const deleteModalElement = document.getElementById('deleteModal');
    const deleteModal = deleteModalElement ? new bootstrap.Modal(deleteModalElement) : null;

    document.querySelectorAll('.delete-msg').forEach(btn => {
        btn.addEventListener('click', function () {
            deleteId = this.dataset.id;
            deleteToken = this.dataset.csrfToken;
            document.getElementById('deleteModalMessage').textContent = 'Bericht "' + this.dataset.subject + '" verwijderen?';
            deleteModal.show();
        });
    });

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!deleteId || !deleteToken) {
                return;
            }
            post(imapMessagesConfig.deleteUrl.replace('__ID__', deleteId), '_token=' + encodeURIComponent(deleteToken))
                .then(res => res.json())
                .then(data => {
                    deleteModal.hide();
                    if (data.success) {
                        location.reload();
                    } else {
                        AppDialog.alert(data.message || 'Verwijderen mislukt');
                    }
                });
        });
    }

    // Sync the folder
    document.querySelectorAll('.sync-folder').forEach(btn => {
        btn.addEventListener('click', function () {
            const resetButton = () => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-sync me-1"></i>Synchroniseren';
            };
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Synchroniseren...';

            post(imapMessagesConfig.syncUrl.replace('__ID__', this.dataset.id), '_token=' + encodeURIComponent(this.dataset.csrfToken) + '&folder=' + encodeURIComponent(this.dataset.folder) + '&limit=100')
                .then(res => res.json())
                .then(data => {
                    resetButton();
                    if (data.success) {
                        AppDialog.alert('Gesynchroniseerd: ' + data.synced + ' berichten, ' + data.new + ' nieuw', { type: 'success' })
                            .then(() => location.reload());
                    } else {
                        AppDialog.alert('Synchroniseren mislukt: ' + data.message);
                    }
                })
                .catch(err => {
                    resetButton();
                    AppDialog.alert('Fout bij synchroniseren: ' + err.message);
                });
        });
    });

    // Bulk delete in the Trash folder
    const selectAllCheckbox = document.getElementById('selectAllMessages');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');
    const bulkDeleteMessageIds = document.getElementById('bulkDeleteMessageIds');

    function updateBulkDeleteButton() {
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checkedCount === 0;
            bulkDeleteBtn.innerHTML = checkedCount > 0
                ? '<i class="fas fa-trash-alt me-1"></i>Definitief verwijderen (' + checkedCount + ')'
                : '<i class="fas fa-trash-alt me-1"></i>Definitief verwijderen';
        }
    }

    if (selectAllCheckbox && messageCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function () {
            messageCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkDeleteButton();
        });

        messageCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === messageCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < messageCheckboxes.length;
                updateBulkDeleteButton();
            });
        });

        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function () {
                const checked = document.querySelectorAll('.message-checkbox:checked');
                if (checked.length === 0) {
                    return;
                }
                const ids = Array.from(checked).map(cb => cb.value);
                const subjects = Array.from(checked).map(cb => cb.dataset.subject).filter(s => s);

                let message = ids.length + ' bericht(en) definitief verwijderen';
                if (subjects.length > 0) {
                    message += ': ' + subjects.slice(0, 3).join(', ');
                    if (subjects.length > 3) {
                        message += ' en nog ' + (subjects.length - 3);
                    }
                }
                message += '? Dit kan niet ongedaan worden gemaakt.';

                AppDialog.confirm(message, { title: 'Definitief verwijderen', confirmLabel: 'Definitief verwijderen', confirmIcon: 'fa-trash-alt' }).then(ok => {
                    if (ok) {
                        bulkDeleteMessageIds.value = ids.join(',');
                        bulkDeleteForm.submit();
                    }
                });
            });
        }
    }

    // Refresh the folder list from the server
    const refreshFolders = document.querySelector('.refresh-folders');
    if (refreshFolders) {
        refreshFolders.addEventListener('click', function () {
            const btn = this;
            const id = btn.dataset.id;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(imapMessagesConfig.foldersUrl.replace('__ID__', id), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync"></i>';
                    if (!data.success || !data.folders) {
                        AppDialog.alert('Mappen vernieuwen mislukt: ' + (data.message || 'Onbekende fout'));
                        return;
                    }
                    const folderList = document.getElementById('folderList');
                    const currentFolder = imapMessagesConfig.currentFolder;
                    folderList.innerHTML = '';
                    data.folders.forEach(folder => {
                        const a = document.createElement('a');
                        a.href = imapMessagesConfig.messagesUrl.replace('__ID__', id).replace('__FOLDER__', encodeURIComponent(folder));
                        a.className = 'list-group-item list-group-item-action' + (folder === currentFolder ? ' active' : '');
                        a.textContent = folder;
                        if (folder === currentFolder) {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-primary rounded-pill folder-count';
                            badge.textContent = imapMessagesConfig.folderCount;
                            a.append(' ', badge);
                        }
                        folderList.appendChild(a);
                    });
                });
        });
    }
});
