/* E-mail account form: hides the SMTP fields when "use IMAP settings for SMTP" is checked. Used by admin/email_account/new and edit. */
document.addEventListener('DOMContentLoaded', function () {
    const useImapCheckbox = document.getElementById('email_account_useImapForSmtp');
    const smtpFields = document.getElementById('smtp-fields');
    if (!useImapCheckbox || !smtpFields) {
        return;
    }

    function toggleSmtpFields() {
        smtpFields.style.display = useImapCheckbox.checked ? 'none' : 'block';
    }

    useImapCheckbox.addEventListener('change', toggleSmtpFields);
    toggleSmtpFields();
});
