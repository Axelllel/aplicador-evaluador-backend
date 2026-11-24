// Mantiene el guardado individual y reúne la revisión en una única petición cuando el docente lo elige.
const batch = document.querySelector('[data-review-batch]');
const answers = [...document.querySelectorAll('form[data-review-answer]')];
if (batch && answers.length) {
    batch.hidden = false;
    document.querySelector('[data-review-batch-footer]').hidden = false;
    const changed = form => {
        const value = form.elements.calificacion.value;
        return value !== '' && Number(value) !== Number(form.dataset.reviewOriginal);
    };
    answers.forEach(form => {
        const reason = form.querySelector('[data-review-reason]');
        if (!reason) return;
        const update = () => {
            const required = changed(form);
            reason.hidden = !required && !reason.querySelector('[aria-invalid="true"]');
            form.elements.motivo.required = required;
            if (!required) form.elements.motivo.setCustomValidity('');
        };
        form.elements.calificacion.addEventListener('input', update);
        update();
    });
    batch.addEventListener('submit', event => {
        batch.querySelectorAll('[data-review-collected]').forEach(input => input.remove());
        const message = batch.querySelector('[data-review-message]');
        message.hidden = true;
        let count = 0;
        for (const form of answers) {
            const grade = form.elements.calificacion;
            if (grade.value === '') continue;
            const reason = form.elements.motivo;
            if (!grade.checkValidity() || (reason?.required && reason.value.trim() === '')) {
                event.preventDefault();
                const target = !grade.checkValidity() ? grade : reason;
                if (target === reason) reason.setCustomValidity('Escribe el motivo para corregir esta calificación.');
                target.reportValidity();target.focus();
                return;
            }
            count++;
            for (const [key,value] of Object.entries({calificacion:grade.value,motivo:reason?.value || ''})) {
                const input = document.createElement('input');
                input.type='hidden';input.name=`respuestas[${form.dataset.reviewAnswer}][${key}]`;input.value=value;
                input.dataset.reviewCollected='';batch.append(input);
            }
        }
        if (!count) {
            event.preventDefault();message.textContent='Escribe al menos una calificación para guardar la revisión.';message.hidden=false;
        }
    });
    answers.forEach(form => form.elements.motivo?.addEventListener('input', () => form.elements.motivo.setCustomValidity('')));
}
