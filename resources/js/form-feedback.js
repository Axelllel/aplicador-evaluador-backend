/** Feedback de envío sin solicitudes adicionales ni cambios al contrato del formulario. */
const pendingForms = new Map();
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.method.toLowerCase() !== 'post' || form.target === '_blank' || form.hasAttribute('data-no-busy')) return;
    if (pendingForms.has(form)) { event.preventDefault(); return; }
    const button = event.submitter || form.querySelector('[type="submit"]');
    if (!button || button.disabled) return;
    const message = form.dataset.submitMessage || document.body.dataset.submitMessage;
    if (!message) return;
    const children = [...button.childNodes];
    const spinner = document.createElement('span');
    spinner.className = 'tp-submit-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode(message));
    button.setAttribute('aria-disabled', 'true');
    form.setAttribute('aria-busy', 'true');
    const status = document.createElement('p');
    status.className = 'sr-only';
    status.setAttribute('role', 'status');
    status.textContent = message;
    form.append(status);
    pendingForms.set(form, { button, children, status });
});
window.addEventListener('pageshow', () => {
    pendingForms.forEach(({ button, children, status }, form) => {
        button.replaceChildren(...children);
        button.removeAttribute('aria-disabled');
        form.removeAttribute('aria-busy');
        status.remove();
    });
    pendingForms.clear();
});
document.querySelector('[data-focus-notice]')?.focus();

document.querySelectorAll('[data-guest-theme]').forEach(guest => {
    try { guest.classList.toggle('tp-dark', localStorage.getItem('bbb-theme') === 'dark'); }
    catch (_) { /* La pantalla funciona sin almacenamiento local. */ }
});
