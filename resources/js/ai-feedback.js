document.querySelectorAll('[data-ai-generator]').forEach(form => {
    form.addEventListener('input', event => {
        if (event.target.name !== 'operacion_id' && typeof crypto.randomUUID === 'function') {
            form.elements.operacion_id.value = crypto.randomUUID();
        }
    });
});
function openGenerator(focus = false) {
    const generator = document.getElementById('generar-con-ia');
    if (!generator) return;
    generator.open = true;
    if (focus) generator.querySelector('[name="tema"]')?.focus({ preventScroll: true });
}
document.querySelectorAll('[data-open-ai]').forEach(link => link.addEventListener('click', () => openGenerator(true)));
if (location.hash === '#generar-con-ia') openGenerator();
