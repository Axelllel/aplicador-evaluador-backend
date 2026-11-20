/** El servidor vuelve a validar todos los campos; el precio no se envía desde el navegador. */
document.querySelectorAll('[data-payment-simulation]').forEach(form => {
    function updateMethod() {
        const method = form.querySelector('[name="metodo_pago"]:checked')?.value;
        form.querySelectorAll('[data-payment-fields]').forEach(fields => {
            const active = fields.dataset.paymentFields === method;
            fields.hidden = !active;
            fields.disabled = !active;
            fields.querySelectorAll('[data-payment-required]').forEach(input => { input.required = active; });
        });
    }
    form.querySelectorAll('[name="metodo_pago"]').forEach(input => input.addEventListener('change', updateMethod));
    updateMethod();
});
