/*
 * Blog post form (admin/blog/new and admin/blog/edit).
 * Once the post is saved the script tag carries the upload URLs (data-image-upload-url, data-file-upload-url,
 * data-youtube-add-url) and images, files and videos are uploaded right away. Delete buttons (.delete-image etc.)
 * and status buttons (.change-status) carry their own data-delete-url / data-status-url and data-csrf-token.
 * Deleting confirms in #deleteImageModal / #deleteFileModal / #deleteYoutubeModal when the page has them,
 * otherwise with a browser confirm.
 */
(function () {
    const config = document.currentScript.dataset;

    function showError(message) {
        const errorModal = document.getElementById('errorModal');
        if (errorModal) {
            document.getElementById('errorModalMessage').textContent = message;
            new bootstrap.Modal(errorModal).show();
        } else {
            alert(message);
        }
    }

    function postJson(url, body, headers) {
        return fetch(url, { method: 'POST', headers: headers || {}, body: body })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            });
    }

    // Upload form: post it with fetch and reload on success
    function bindUpload(formId, url) {
        const form = document.getElementById(formId);
        if (!form || !url) {
            return;
        }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            postJson(url, new FormData(this))
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showError(data.message || 'Unknown error');
                    }
                })
                .catch(err => showError('Request failed: ' + err.message));
        });
    }

    // Delete buttons (.delete-image etc.) with data-delete-url and data-csrf-token
    function bindDelete(selector, modalId, confirmId, confirmText) {
        const modalElement = document.getElementById(modalId);
        const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
        let current = null;

        function remove() {
            postJson(current.dataset.deleteUrl, '_token=' + encodeURIComponent(current.dataset.csrfToken), { 'Content-Type': 'application/x-www-form-urlencoded' })
                .then(data => {
                    if (data.success) {
                        if (modal) {
                            modal.hide();
                        }
                        location.reload();
                    } else {
                        showError(data.message || 'Unknown error');
                    }
                })
                .catch(err => showError('Request failed: ' + err.message));
        }

        document.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', function () {
                current = this;
                if (modal) {
                    modal.show();
                } else if (confirm(confirmText)) {
                    remove();
                }
            });
        });

        if (modal) {
            document.getElementById(confirmId).addEventListener('click', function () {
                if (current && current.dataset.csrfToken) {
                    remove();
                }
            });
        }
    }

    // Media that is still part of the (unsaved) form is just taken out of the page
    function bindFormRemove(selector, itemSelector, confirmText) {
        document.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', function () {
                if (confirm(confirmText)) {
                    this.closest(itemSelector).remove();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindUpload('imageUploadForm', config.imageUploadUrl);
        bindUpload('fileUploadForm', config.fileUploadUrl);
        bindUpload('youtubeForm', config.youtubeAddUrl);

        bindDelete('.delete-image', 'deleteImageModal', 'confirmDeleteImage', 'Delete this image?');
        bindDelete('.delete-file', 'deleteFileModal', 'confirmDeleteFile', 'Delete this file?');
        bindDelete('.delete-youtube', 'deleteYoutubeModal', 'confirmDeleteYoutube', 'Delete this video?');

        bindFormRemove('.remove-form-image', '.image-item', 'Remove this image?');
        bindFormRemove('.remove-form-file', '.file-item', 'Remove this file?');
        bindFormRemove('.remove-form-youtube', '.youtube-item', 'Remove this video?');

        document.querySelectorAll('.change-status').forEach(button => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                fetch(this.dataset.statusUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'status=' + this.dataset.status
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            });
        });
    });
})();
