/* Route permission list (admin/route_permission/index): enable/disable a rule. Deleting uses components/ajax-delete.js. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-rp').forEach(button => {
        button.addEventListener('click', function () {
            fetch(this.dataset.toggleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(this.dataset.csrfToken)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
        });
    });
});
