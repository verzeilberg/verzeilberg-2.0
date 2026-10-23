/* Mailbox overview: sync the INBOX of one account. Used by admin/imap/index (script tag sets data-sync-url with __ID__). */
const imapAccountsConfig = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sync-account').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(imapAccountsConfig.syncUrl.replace('__ID__', btn.dataset.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(btn.dataset.csrfToken) + '&folder=INBOX&limit=20'
            })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync"></i>';
                    if (data.success) {
                        AppDialog.alert('Gesynchroniseerd: ' + data.synced + ' berichten, ' + data.new + ' nieuw', { type: 'success' })
                            .then(() => location.reload());
                    } else {
                        AppDialog.alert('Synchroniseren mislukt: ' + data.message);
                    }
                });
        });
    });
});
