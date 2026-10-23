/*
 * Message and confirmation dialogs as a Bootstrap modal, instead of the browser's alert() and confirm().
 * Loaded by both base templates (public site and admin), after bootstrap.bundle and before common.js:
 *   AppDialog.confirm('Delete this?', {title, confirmLabel, confirmClass, icon}).then(ok => { if (ok) … });
 *   AppDialog.alert('Saving failed', {type: 'error'});   // type: 'error' (default), 'success' or 'info'
 * Both return a promise that settles once the dialog is fully closed, so the next modal can open right away.
 * The message is shown as text (never as HTML); line breaks in it are kept.
 */
window.AppDialog = (function () {
    const TYPES = {
        error: { title: 'Er ging iets mis', icon: 'fa-exclamation-triangle', header: 'bg-danger text-white', button: 'btn-danger' },
        success: { title: 'Gelukt', icon: 'fa-check-circle', header: 'bg-success text-white', button: 'btn-success' },
        info: { title: 'Melding', icon: 'fa-info-circle', header: 'bg-primary text-white', button: 'btn-primary' },
        confirm: { title: 'Weet je het zeker?', icon: 'fa-question-circle', header: 'bg-warning', button: 'btn-danger' },
    };

    let element = null;
    let queue = Promise.resolve();

    function build() {
        element = document.createElement('div');
        element.className = 'modal fade';
        element.id = 'appDialog';
        element.tabIndex = -1;
        element.setAttribute('aria-labelledby', 'appDialogTitle');
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h5 class="modal-title" id="appDialogTitle"><i class="fas me-2"></i><span></span></h5>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>' +
                    '</div>' +
                    '<div class="modal-body"><p class="mb-0 pre-line"></p></div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary js-dialog-cancel" data-bs-dismiss="modal">Annuleren</button>' +
                        '<button type="button" class="btn js-dialog-ok"><i class="fas me-1"></i><span></span></button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(element);
    }

    // Another modal (e.g. the one whose action just failed) is first closed completely: Bootstrap shows one at a time
    // (also one that is still fading out after a hide(): Bootstrap keeps it display:block until it's hidden)
    function whenNoOtherModal() {
        const open = Array.from(document.querySelectorAll('.modal:not(#appDialog)')).find(modal => modal.style.display === 'block');
        if (!open) {
            return Promise.resolve();
        }
        return new Promise(resolve => {
            open.addEventListener('hidden.bs.modal', () => resolve(), { once: true });
            if (open.classList.contains('show')) {
                bootstrap.Modal.getOrCreateInstance(open).hide();
            }
        });
    }

    function open(message, options, isConfirm) {
        const type = TYPES[isConfirm ? 'confirm' : (options.type || 'error')];

        // One dialog at a time: a second call waits until the first is closed
        const result = queue.then(whenNoOtherModal).then(() => new Promise(resolve => {
            if (!element) {
                build();
            }
            const header = element.querySelector('.modal-header');
            header.className = 'modal-header ' + type.header;
            element.querySelector('.btn-close').classList.toggle('btn-close-white', type.header.includes('text-white'));
            element.querySelector('.modal-title i').className = 'fas ' + (options.icon || type.icon) + ' me-2';
            element.querySelector('.modal-title span').textContent = options.title || type.title;
            element.querySelector('.modal-body p').textContent = message;

            const cancel = element.querySelector('.js-dialog-cancel');
            const ok = element.querySelector('.js-dialog-ok');
            cancel.hidden = !isConfirm;
            ok.className = 'btn js-dialog-ok ' + (options.confirmClass || (isConfirm ? type.button : 'btn-primary'));
            ok.querySelector('i').className = 'fas ' + (isConfirm ? (options.confirmIcon || 'fa-check') : 'fa-check') + ' me-1';
            ok.querySelector('span').textContent = options.confirmLabel || (isConfirm ? 'Doorgaan' : 'OK');

            let confirmed = false;
            const modal = bootstrap.Modal.getOrCreateInstance(element);
            ok.onclick = () => {
                confirmed = true;
                modal.hide();
            };
            element.addEventListener('hidden.bs.modal', () => resolve(confirmed), { once: true });
            // The safe choice has the focus: Cancel for a question, OK for a message
            element.addEventListener('shown.bs.modal', () => (isConfirm ? cancel : ok).focus(), { once: true });
            modal.show();
        }));

        queue = result.catch(() => {});
        return result;
    }

    return {
        confirm: (message, options) => open(message, options || {}, true),
        alert: (message, options) => open(message, options || {}, false).then(() => undefined),
    };
})();
