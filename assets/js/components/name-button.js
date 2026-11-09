/*
 * "Naam" button: puts [[ ]] around the selected text, so the server shows it as initials to visitors who
 * aren't logged in (App\Service\NameMasker). Without a selection it inserts [[]] with the cursor in between.
 *   <textarea data-name-button>   gets a button below it
 *   <trix-editor>                 gets a button in its toolbar (load this script before Trix)
 */
(function () {
    function button(className) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = className;
        el.title = 'Naam: ingelogde bezoekers zien de naam, anderen alleen de initialen';
        el.innerHTML = '<i class="fas fa-user-secret"></i> Naam';
        el.addEventListener('mousedown', event => event.preventDefault());
        return el;
    }

    function attachTextarea(field) {
        const el = button('btn btn-sm btn-outline-secondary mt-1 ms-1');
        // Next to the emoji button when there is one
        const after = field.nextElementSibling && field.nextElementSibling.classList.contains('emoji-picker-toggle') ? field.nextElementSibling : field;
        after.insertAdjacentElement('afterend', el);
        el.addEventListener('click', function () {
            const start = field.selectionStart;
            const end = field.selectionEnd;
            const selected = field.value.slice(start, end).trim();
            field.setRangeText('[[' + selected + ']]', start, end, 'end');
            if (selected === '') {
                field.setSelectionRange(start + 2, start + 2);
            }
            field.focus();
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    function attachTrix(editorElement) {
        if (editorElement.dataset.nameButton === 'ready' || !editorElement.toolbarElement) {
            return;
        }
        editorElement.dataset.nameButton = 'ready';
        const group = document.createElement('span');
        group.className = 'trix-button-group';
        const el = button('trix-button trix-name-button');
        group.appendChild(el);
        editorElement.toolbarElement.querySelector('.trix-button-row').appendChild(group);

        el.addEventListener('click', function () {
            const editor = editorElement.editor;
            const range = editor.getSelectedRange();
            const selected = editor.getDocument().getStringAtRange(range).trim();
            editor.setSelectedRange(range);
            editor.insertString('[[' + selected + ']]');
            if (selected === '') {
                editor.setSelectedRange(range[0] + 2);
            }
            editorElement.focus();
        });
    }

    document.addEventListener('trix-initialize', event => attachTrix(event.target));
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('textarea[data-name-button]').forEach(attachTextarea);
        document.querySelectorAll('trix-editor').forEach(editor => editor.editor && attachTrix(editor));
    });
})();
