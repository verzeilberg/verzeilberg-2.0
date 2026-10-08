/* Checklist answers list (admin/checklist/answers): delete an answer after confirmation. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    function buildDeleteAnswerUrl(id) {
        return config.deleteUrl.replace('__ID__', id);
    }

    // Delete answer
    document.querySelectorAll('.delete-answer').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const token = this.dataset.csrfToken;
            if (confirm('Are you sure you want to delete this answer?')) {
                fetch(buildDeleteAnswerUrl(id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({ _token: token })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message || 'Error');
                });
            }
        });
    });
});
