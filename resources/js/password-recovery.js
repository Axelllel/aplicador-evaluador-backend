// El fragmento del enlace nunca se guarda en localStorage ni se envía como parte de una URL al servidor.
const params = new URLSearchParams(location.hash.slice(1));
if (params.get('type') === 'recovery' && location.pathname !== '/restablecer-contrasena') {
    const fragment = location.hash;
    history.replaceState(null, '', location.pathname + location.search);
    location.replace('/restablecer-contrasena' + fragment);
} else if (location.pathname === '/restablecer-contrasena') {
    const token = params.get('access_token');
    const bridge = document.querySelector('[data-recovery-bridge]');
    const invalid = document.querySelector('[data-recovery-error]');
    history.replaceState(null, '', location.pathname);
    if (token && params.get('type') === 'recovery' && bridge) {
        const progress = document.querySelector('[data-recovery-progress]');
        if (progress) progress.hidden = false;
        bridge.elements.access_token.value = token;
        bridge.submit();
    } else if (params.has('error') || params.has('access_token')) {
        if (invalid) invalid.hidden = false;
    }
}
const portal = document.querySelector('[data-recovery-root]');
if (portal) {
    const button = portal.querySelector('[data-recovery-theme]');
    let dark = false;
    try { dark = localStorage.getItem('bbb-theme') === 'dark'; } catch { /* El formulario funciona sin almacenamiento local. */ }
    const apply = () => {
        portal.classList.toggle('dark', dark);
        button.querySelector('span').textContent = dark ? '☼' : '☾';
    };
    apply();
    button.addEventListener('click', () => {
        dark = !dark;apply();
        try { localStorage.setItem('bbb-theme', dark ? 'dark' : 'light'); } catch { /* Preferencia opcional. */ }
    });
}
