/* Route permission form (admin/route_permission/form): shows which admin routes the typed pattern matches. */
document.addEventListener('DOMContentLoaded', function () {
    const preview = document.getElementById('route-pattern-preview');
    const input = document.querySelector('[list="admin-route-suggestions"]');
    if (!preview || !input) {
        return;
    }

    const routes = JSON.parse(preview.dataset.routes);
    const escapeRegex = s => s.replace(/[.+?^${}()|[\]\\]/g, '\\$&');

    function update() {
        const pattern = input.value.trim().toLowerCase();
        preview.replaceChildren();
        if (pattern === '') {
            return;
        }

        const regex = new RegExp('^' + escapeRegex(pattern).replace(/\*/g, '.*') + '$');
        const matched = routes.filter(route => regex.test(route));

        const summary = document.createElement('div');
        summary.className = matched.length ? 'text-success' : 'text-danger';
        summary.textContent = matched.length
            ? 'Komt overeen met ' + matched.length + ' route' + (matched.length === 1 ? '' : 's') + ':'
            : 'Komt met geen enkele admin-route overeen.';
        preview.append(summary);

        if (matched.length) {
            const list = document.createElement('div');
            list.className = 'font-monospace text-muted';
            list.textContent = matched.join(', ');
            preview.append(list);
        }
    }

    input.addEventListener('input', update);
    update();
});
