/* Compose an e-mail: Quill rich text editor that writes its HTML back into the bodyHtml field. Used by admin/imap/compose. */
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.querySelector('[name*="bodyHtml"]');
    const editorDiv = document.getElementById('editor');
    if (!editorDiv || !textarea) {
        return;
    }

    const editor = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        },
        placeholder: 'Schrijf je HTML-e-mail...'
    });

    if (textarea.value) {
        editor.root.innerHTML = textarea.value;
    }

    // Sync the editor content back to the hidden textarea on submit
    textarea.form.addEventListener('submit', function () {
        textarea.value = editor.root.innerHTML;
    });
});
