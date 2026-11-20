/** Selección por tipo y cálculo previo; la API determina el tipo real y valida al guardar. */
document.querySelectorAll('[data-exam-composer]').forEach(form => {
    const question = form.querySelector('[name="pregunta_id"]');
    const filter = form.querySelector('[data-composition-filter]');
    const weight = form.querySelector('[name="ponderacion"]');
    const preview = form.querySelector('[data-composition-preview]');
    const total = form.querySelector('[data-composition-total]');
    const text = JSON.parse(form.querySelector('[data-composition-text]').textContent);
    const format = new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const current = Math.round(Number(form.dataset.currentWeight) * 100);
    function render() {
        const selected = question.selectedOptions[0];
        preview.textContent = selected?.dataset.questionPrompt || '';
        preview.hidden = !preview.textContent;
        const pending = weight.value === '' ? 0 : Math.round(Number(weight.value) * 100);
        const sum = current + (Number.isFinite(pending) ? pending : 0);
        const balance = sum > 10000 ? text.exceso : sum === 10000 ? text.completa_previa : text.falta;
        total.textContent = text.total_previo.replace(':valor', format.format(sum / 100)) + ' ' + balance.replace(':valor', format.format(Math.abs(10000 - sum) / 100));
    }
    filter.addEventListener('change', () => {
        [...question.options].forEach(option => {
            const mismatch = option.value !== '' && filter.value !== '' && option.dataset.questionType !== filter.value;
            option.hidden = mismatch;
            option.disabled = mismatch;
        });
        if (question.selectedOptions[0]?.disabled) question.value = '';
        form.querySelector('[data-composition-empty]').hidden = [...question.options].some(option => option.value !== '' && !option.disabled);
        render();
    });
    question.addEventListener('change', render);
    weight.addEventListener('input', render);
    render();
});
