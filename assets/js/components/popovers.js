/* Starts the Bootstrap popovers on the page (content may contain HTML, so escape it in the template). */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (element) {
        new bootstrap.Popover(element, { html: true, sanitize: false });
    });
});
