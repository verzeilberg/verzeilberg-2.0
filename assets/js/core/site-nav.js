// Marks the main-menu link of the current section (the menu is rendered without an active state)
(function () {
    const path = window.location.pathname;
    let best = null;
    document.querySelectorAll('.site-nav .navbar-nav .nav-link[href^="/"]').forEach(link => {
        const href = link.getAttribute('href');
        const matches = href === '/' ? path === '/' : path === href || path.startsWith(href.replace(/\/$/, '') + '/');
        if (matches && (!best || href.length > best.getAttribute('href').length)) {
            best = link;
        }
    });
    if (best) {
        best.classList.add('active');
        best.setAttribute('aria-current', 'page');
    }
})();
