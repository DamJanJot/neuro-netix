document.addEventListener('DOMContentLoaded', function () {
    const shell = document.getElementById('nxShell');
    const backdrop = document.getElementById('nxBackdrop');
    const openSidebarBtn = document.getElementById('nxOpenSidebar');
    const closeSidebarBtn = document.getElementById('nxCloseSidebar');
    const appSwitch = document.getElementById('nxSwitch');
    const appSwitchBtn = document.getElementById('nxSwitchBtn');

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
        });

        document.addEventListener('click', function (event) {
            if (!appSwitch.contains(event.target)) {
                appSwitch.classList.remove('open');
                appSwitchBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
