/*
 * Videos for logged in visitors on the blog edit page (admin/blog/edit, #blogVideos).
 * The file is sent in chunks (data-chunk-size, below the server's upload limit) to data-upload-url, one after the
 * other, with a progress bar; a failed chunk is retried a few times. Delete buttons (.delete-video) carry
 * data-delete-url and data-csrf-token. See App\Controller\Admin\BlogVideoController.
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('videoUploadForm');
    if (!form) {
        return;
    }

    const fileInput = document.getElementById('videoFile');
    const titleInput = document.getElementById('videoTitle');
    const progress = document.getElementById('videoProgress');
    const bar = progress.querySelector('.progress-bar');
    const button = document.getElementById('videoUploadButton');
    const chunkSize = parseInt(form.dataset.chunkSize, 10);
    const maxSize = parseInt(form.dataset.maxSize, 10);

    function setProgress(fraction) {
        const percent = Math.floor(fraction * 100) + '%';
        bar.style.width = percent;
        bar.textContent = percent;
    }

    function randomId() {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    }

    async function sendChunk(body) {
        let lastError;
        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                const res = await fetch(form.dataset.uploadUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json().catch(() => ({ success: false, message: 'Onverwacht antwoord van de server (' + res.status + ')' }));
                if (data.success) {
                    return data;
                }
                // The server refused it (wrong type, too big): trying again won't help
                if (res.status >= 400 && res.status < 500) {
                    throw Object.assign(new Error(data.message || 'Uploaden mislukt'), { final: true });
                }
                lastError = new Error(data.message || 'Uploaden mislukt');
            } catch (err) {
                if (err.final) {
                    throw err;
                }
                lastError = err;
            }
            await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
        }
        throw lastError;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const file = fileInput.files[0];
        if (!file) {
            return;
        }
        if (file.size > maxSize) {
            AppDialog.alert('Deze video is te groot (maximaal ' + Math.round(maxSize / 1024 / 1024 / 1024) + ' GB).');
            return;
        }

        const uploadId = randomId();
        button.disabled = true;
        fileInput.disabled = true;
        progress.classList.remove('d-none');
        setProgress(0);
        // Leaving the page halfway would lose the upload
        const warn = e => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', warn);

        try {
            for (let offset = 0; offset < file.size; offset += chunkSize) {
                const last = offset + chunkSize >= file.size;
                const body = new FormData();
                body.append('_token', form.dataset.csrfToken);
                body.append('uploadId', uploadId);
                body.append('offset', String(offset));
                body.append('chunk', file.slice(offset, offset + chunkSize), 'chunk');
                if (last) {
                    body.append('last', '1');
                    body.append('size', String(file.size));
                    body.append('name', file.name);
                    body.append('title', titleInput.value);
                }
                await sendChunk(body);
                setProgress(Math.min(1, (offset + chunkSize) / file.size));
            }
            window.removeEventListener('beforeunload', warn);
            location.reload();
        } catch (err) {
            window.removeEventListener('beforeunload', warn);
            button.disabled = false;
            fileInput.disabled = false;
            progress.classList.add('d-none');
            AppDialog.alert('Uploaden mislukt: ' + err.message);
        }
    });

    document.querySelectorAll('.delete-video').forEach(deleteButton => {
        deleteButton.addEventListener('click', function () {
            AppDialog.confirm('Deze video verwijderen? Het bestand wordt ook van de server verwijderd.', { confirmLabel: 'Verwijderen', confirmIcon: 'fa-trash' }).then(ok => {
                if (!ok) {
                    return;
                }
                fetch(this.dataset.deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: '_token=' + encodeURIComponent(this.dataset.csrfToken)
                })
                    .then(res => res.json())
                    .then(data => data.success ? location.reload() : AppDialog.alert(data.message || 'Verwijderen mislukt'))
                    .catch(err => AppDialog.alert('Verwijderen mislukt: ' + err.message));
            });
        });
    });
});
