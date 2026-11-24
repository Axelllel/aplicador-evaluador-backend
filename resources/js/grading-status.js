// Actualiza tareas pendientes sin interrumpir una revisión ni perder cambios del docente.
const pending = document.querySelector('[data-grading-pending]');
if (pending) {
    let editing = false;
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => { editing = true; });
        form.addEventListener('input', () => { editing = true; });
        form.addEventListener('focusin', event => {
            if (event.target.matches('input:not([type="hidden"]),textarea,select')) editing = true;
        });
    });
    // Una espera prolongada conserva la pantalla para revisar el estado del servicio.
    const key = `bbb-grading-watch:${location.pathname}`;
    let since = Date.now();
    try {
        const previous = Number(sessionStorage.getItem(key));
        if (previous > 0) since = previous;
        else sessionStorage.setItem(key, String(since));
    } catch { /* Sin almacenamiento local no se recarga automáticamente. */ editing = true; }
    setTimeout(() => {
        if (!editing && document.visibilityState === 'visible' && Date.now() - since < 300000) location.reload();
    }, 20000);
} else {
    try { sessionStorage.removeItem(`bbb-grading-watch:${location.pathname}`); } catch { /* Sin persistencia local. */ }
}
