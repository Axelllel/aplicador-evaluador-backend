/** Interacciones aisladas del espacio administrativo; no modifica la autenticación. */
const admin = document.querySelector('[data-admin-shell]');
const login = document.querySelector('[data-admin-login]');
const root = admin || login;
if (root) {
    const theme = root.querySelector('[data-admin-theme]');
    const applyTheme = dark => {
        root.classList.toggle(admin ? 'tp-dark' : 'dark', dark);
        if (theme) {
            theme.querySelector('[data-theme-moon]').toggleAttribute('hidden', dark);
            theme.querySelector('[data-theme-sun]').toggleAttribute('hidden', !dark);
            theme.setAttribute('aria-label', dark ? theme.dataset.labelLight : theme.dataset.labelDark);
        }
        try { localStorage.setItem('bbb-theme', dark ? 'dark' : 'light'); } catch { /* El acceso funciona sin persistencia local. */ }
    };
    let dark = false;
    try { dark = localStorage.getItem('bbb-theme') === 'dark'; } catch { /* Tema claro por defecto. */ }
    applyTheme(dark);
    theme?.addEventListener('click', () => applyTheme(!root.classList.contains(admin ? 'tp-dark' : 'dark')));
}
if (admin) {
    const menu = admin.querySelector('[data-admin-menu]');
    const sidebar = document.getElementById('admin-sidebar');
    const workspace = document.getElementById('admin-workspace');
    const overlay = admin.querySelector('.ad-overlay');
    const desktop = matchMedia('(min-width: 1100px)');
    const toggleMenu = open => {
        admin.classList.toggle('ad-menu-open', open);
        menu.setAttribute('aria-expanded', String(open));
        overlay.hidden = !open || desktop.matches;
        sidebar.inert = !desktop.matches && !open;
        workspace.inert = open && !desktop.matches;
        if (open && !desktop.matches) sidebar.querySelector('[data-admin-close]')?.focus();
    };
    menu.addEventListener('click', () => toggleMenu(!admin.classList.contains('ad-menu-open')));
    admin.querySelectorAll('[data-admin-close]').forEach(button => button.addEventListener('click', () => {
        toggleMenu(false); menu.focus();
    }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && admin.classList.contains('ad-menu-open')) { toggleMenu(false); menu.focus(); }
        if (event.key === 'Tab' && admin.classList.contains('ad-menu-open') && !desktop.matches) {
            const items = [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter(item => item.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    desktop.addEventListener('change', () => toggleMenu(false));
    toggleMenu(false);
    admin.querySelectorAll('dialog[data-admin-dialog-open]').forEach(dialog => {
        dialog.showModal(); dialog.querySelector('[aria-invalid=true]')?.focus();
    });
}
