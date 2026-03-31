document.addEventListener('DOMContentLoaded', function () {
    const shell = document.getElementById('nxShell');
    const backdrop = document.getElementById('nxBackdrop');
    const openSidebarBtn = document.getElementById('nxOpenSidebar');
    const closeSidebarBtn = document.getElementById('nxCloseSidebar');
    const appSwitch = document.getElementById('nxSwitch');
    const appSwitchBtn = document.getElementById('nxSwitchBtn');
    const userMenu = document.getElementById('nxUserMenu');
    const userMenuBtn = document.getElementById('nxUserMenuBtn');

    function closeSidebar() {
        if (!shell || !backdrop) return;
        shell.classList.remove('sidebar-open');
        backdrop.classList.remove('open');
    }

    function openSidebar() {
        if (!shell || !backdrop) return;
        shell.classList.add('sidebar-open');
        backdrop.classList.add('open');
    }

    if (openSidebarBtn) {
        openSidebarBtn.addEventListener('click', function () {
            openSidebar();
        });
    }

    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', function () {
            closeSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeSidebar();
        });
    }

    if (appSwitch && appSwitchBtn) {
        appSwitchBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            const isOpen = appSwitch.classList.toggle('open');
            appSwitchBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen && userMenu && userMenuBtn) {
                userMenu.classList.remove('open');
                userMenuBtn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('click', function (event) {
            if (!appSwitch.contains(event.target)) {
                appSwitch.classList.remove('open');
                appSwitchBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (userMenu && userMenuBtn) {
        userMenuBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            const isOpen = userMenu.classList.toggle('open');
            userMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen && appSwitch && appSwitchBtn) {
                appSwitch.classList.remove('open');
                appSwitchBtn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('click', function (event) {
            if (!userMenu.contains(event.target)) {
                userMenu.classList.remove('open');
                userMenuBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Expandable nav groups (Przedmioty sub-items)
    document.querySelectorAll('.nx-nav-expand-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var parent = btn.closest('.nx-nav-expandable');
            if (parent) {
                parent.classList.toggle('open');
            }
        });
    });
});
