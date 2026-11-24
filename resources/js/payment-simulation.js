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
    // El CVV/CID se adapta al formato; el servidor conserva la validación definitiva.
    function updateSecurityCode() {
        const number = (form.querySelector('[name="numero_tarjeta"]')?.value || '').replace(/[ -]/g, '');
        const code = form.querySelector('[name="cvv"]');
        if (!code) return;
        const digits = /^3[47][0-9]{13}$/.test(number) ? 4 : 3;
        code.pattern = `[0-9]{${digits}}`;
        code.title = `Código de seguridad de ${digits} dígitos`;
    }
    form.querySelector('[name="numero_tarjeta"]')?.addEventListener('input', updateSecurityCode);
    updateSecurityCode();
    form.querySelectorAll('[name="metodo_pago"]').forEach(input => input.addEventListener('change', updateMethod));
    updateMethod();
});
