/*
 * Behaviour every page needs (public site and admin). Loaded by both base templates.
 * Page-specific scripts live in components/ (reusable) and modules/<feature>/ and are only loaded where used.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Flash messages close themselves after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }, 5000);
    });

    // Smooth scroll for in-page anchor links (not for tabs, collapses etc. that use href="#…" themselves)
    document.querySelectorAll('a[href^="#"]:not([data-bs-toggle])').forEach(function (anchor) {
        anchor.addEventListener('click', function (event) {
            const href = this.getAttribute('href');
            if (href === '#') {
                return;
            }
            const target = document.querySelector(href);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Declarative helpers, so templates need no inline event handlers:
    //   <select data-auto-submit>        submits its form on change
    //   <button data-print>              prints the page
    //   <input data-select-on-click>     selects its text on click (e.g. a feed URL)
    //   <form data-confirm="Sure?">      asks before submitting
    //   <tr class="js-row-link" data-href="…">  the whole row is a link (inner links/buttons keep working)
    document.addEventListener('change', function (event) {
        const field = event.target.closest('[data-auto-submit]');
        if (field && field.form) {
            field.form.submit();
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-print]')) {
            window.print();
            return;
        }

        const input = event.target.closest('[data-select-on-click]');
        if (input) {
            input.select();
            return;
        }

        const row = event.target.closest('.js-row-link[data-href]');
        if (row && !event.target.closest('a, button, input, select, textarea, label, form') && window.getSelection().toString() === '') {
            window.location.href = row.dataset.href;
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
