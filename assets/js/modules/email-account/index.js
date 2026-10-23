/*
 * E-mail accounts list: delete (via the admin #deleteModal) and test the IMAP and SMTP connection.
 * Used by admin/email_account/index; the script tag sets data-delete-url, data-test-url and data-test-smtp-url (with __ID__).
 */
const emailAccountConfig = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function () {
    function post(url, token) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '_token=' + encodeURIComponent(token)
        }).then(res => res.json());
    }

    // Delete
    let deleteId = null;
    let deleteToken = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.querySelectorAll('.delete-account').forEach(btn => {
        btn.addEventListener('click', function () {
            deleteId = this.dataset.id;
            deleteToken = this.dataset.csrfToken;
            document.getElementById('deleteModalMessage').textContent = 'E-mailaccount "' + this.dataset.name + '" verwijderen? Alle gesynchroniseerde berichten worden ook verwijderd.';
            deleteModal.show();
        });
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
        if (!deleteId || !deleteToken) {
            return;
        }
        post(emailAccountConfig.deleteUrl.replace('__ID__', deleteId), deleteToken).then(data => {
            deleteModal.hide();
            if (data.success) {
                location.reload();
            } else {
                AppDialog.alert(data.message || 'Verwijderen mislukt');
            }
        });
    });

    // Connection tests
    function testButtons(selector, url, icon, okMessage, failMessage) {
        document.querySelectorAll(selector).forEach(btn => {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                post(url.replace('__ID__', btn.dataset.id), btn.dataset.csrfToken).then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas ' + icon + '"></i>';
                    AppDialog.alert(data.success ? okMessage : failMessage + data.message, { type: data.success ? 'success' : 'error' });
                });
            });
        });
    }

    testButtons('.test-account', emailAccountConfig.testUrl, 'fa-plug', 'Verbinding gelukt!', 'Verbinding mislukt: ');
    testButtons('.test-smtp', emailAccountConfig.testSmtpUrl, 'fa-paper-plane', 'SMTP-testmail verstuurd!', 'SMTP-test mislukt: ');
});
