/*
 * Blog post list (admin/blog/index): delete a post (#deletePostModal) and change its status from the row dropdown.
 * The dropdown items carry data-delete-url / data-status-url (+ data-csrf-token, data-post-id, data-status).
 */
(function () {
    function showError(message) {
        AppDialog.alert(message);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // The dropdowns sit in a scrolling table, start them explicitly
        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(element => {
            bootstrap.Dropdown.getOrCreateInstance(element);
        });

        let currentDeleteUrl = null;
        let currentPostCsrfToken = null;
        const deletePostModal = new bootstrap.Modal(document.getElementById('deletePostModal'));

        document.querySelectorAll('.delete-post').forEach(button => {
            button.addEventListener('click', function () {
                currentDeleteUrl = this.dataset.deleteUrl;
                currentPostCsrfToken = this.dataset.csrfToken;
                deletePostModal.show();
            });
        });

        document.getElementById('confirmDeletePost').addEventListener('click', function () {
            if (!currentDeleteUrl || !currentPostCsrfToken) {
                return;
            }
            fetch(currentDeleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(currentPostCsrfToken)
            })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        deletePostModal.hide();
                        location.reload();
                    } else {
                        showError(data.message || 'Onbekende fout');
                    }
                })
                .catch(err => showError('Verzoek mislukt: ' + err.message));
        });

        document.querySelectorAll('.change-status').forEach(link => {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const postId = this.dataset.postId;
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
                            const badge = document.querySelector('.status-badge[data-post-id="' + postId + '"]');
                            if (badge) {
                                badge.textContent = ['Concept', 'Online', 'Offline', 'Gearchiveerd'][data.status] || 'Onbekend';
                                badge.className = 'badge bg-' + ['secondary', 'success', 'warning', 'danger'][data.status] + ' status-badge';
                            }
                        }
                    });
            });
        });
    });
})();
