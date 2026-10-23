/*
 * Approve, reject and delete blog comments (admin/blog/comments and the admin dashboard).
 * Script tag config: data-approve-url, data-reject-url and optional data-delete-url, each with __ID__ for the comment id.
 * Deleting confirms in the shared #deleteModal and removes the row #comment-<id>.
 */
(function () {
    const config = document.currentScript.dataset;

    function post(url, options) {
        return fetch(url, Object.assign({ method: 'POST' }, options)).then(res => res.json());
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.approve-comment, .reject-comment').forEach(button => {
            button.addEventListener('click', function () {
                const template = this.classList.contains('approve-comment') ? config.approveUrl : config.rejectUrl;
                post(template.replace('__ID__', this.dataset.commentId), { headers: { 'Content-Type': 'application/json' } })
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            });
        });

        if (!config.deleteUrl) {
            return;
        }
        let deleteCommentId = null;
        let deleteCommentToken = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        document.querySelectorAll('.delete-comment').forEach(button => {
            button.addEventListener('click', function () {
                deleteCommentId = this.dataset.commentId;
                deleteCommentToken = this.dataset.csrfToken;
                document.getElementById('deleteModalMessage').textContent = 'Weet je zeker dat je deze reactie wilt verwijderen? Dit kan niet ongedaan worden gemaakt.';
                deleteModal.show();
            });
        });

        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            if (!deleteCommentId || !deleteCommentToken) {
                return;
            }
            post(config.deleteUrl.replace('__ID__', deleteCommentId), {
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(deleteCommentToken)
            })
                .then(data => {
                    if (data.success) {
                        deleteModal.hide();
                        document.getElementById('comment-' + deleteCommentId).remove();
                    }
                });
        });
    });
})();
