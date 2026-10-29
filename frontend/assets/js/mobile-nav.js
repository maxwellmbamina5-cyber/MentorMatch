document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.app-navbar').forEach(function (nav) {
        const toggle = nav.querySelector('.app-navbar-toggle');
        const menu = nav.querySelector('.app-navbar-collapse');

        if (!toggle || !menu) return;

        toggle.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    menu.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Open navigation');
                }
            });
        });
    });
});
