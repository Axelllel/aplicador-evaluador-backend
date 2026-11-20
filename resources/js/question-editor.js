/** Editor progresivo de preguntas. El formulario original funciona sin JavaScript. */
function initializeQuestionEditor(form) {
    const config = JSON.parse(form.querySelector('[data-editor-config]').textContent);
    const text = config.text;
    const previous = config.old || {};
    const type = form.elements.tipo;
    const prompt = form.elements.enunciado;
    const controls = form.querySelector('[data-editor-controls]');
    const status = form.querySelector('[data-editor-status]');
    const fallback = form.querySelector('[data-editor-fallback]');
    const legacyOptions = form.elements.opciones;
    const legacyReference = form.elements.respuesta_modelo;
    let sequence = 0;
    const createRow = (value = '') => ({ id: ++sequence, value: typeof value === 'string' ? value : '' });
    let options = (Array.isArray(previous.opciones_lista) ? previous.opciones_lista :
        (legacyOptions.value || '').split(/\r?\n/).map(value => value.trim()).filter(Boolean)).map(createRow);
    if (options.length < 2) options = [createRow(), createRow(), createRow(), createRow()];
    let correct = previous.correcta !== undefined && previous.correcta !== null && previous.correcta !== '' ? options[Number(previous.correcta)]?.id :
        options.find(row => row.value === legacyReference.value.trim())?.id;
    let pairs = Array.isArray(previous.pares_lista) ? previous.pares_lista.map(row => ({ id: ++sequence, left: typeof row?.izquierda === 'string' ? row.izquierda : '', right: typeof row?.derecha === 'string' ? row.derecha : '' })) : [];
    if (!pairs.length && type.value === 'relacion_columnas') {
        pairs = legacyOptions.value.split(/\r?\n/).filter(Boolean).map(line => {
            const separator = line.indexOf('|');
            return { id: ++sequence, left: separator < 0 ? line.trim() : line.slice(0, separator).trim(), right: separator < 0 ? '' : line.slice(separator + 1).trim() };
        });
    }
    if (pairs.length < 2) pairs = [{ id: ++sequence, left: '', right: '' }, { id: ++sequence, left: '', right: '' }];
    let reference = legacyReference.value;

    function element(tag, className, content) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (content !== undefined) node.textContent = content;
        return node;
    }
    function announce(message) { status.textContent = message; }
    function button(label, action, className = 'tp-editor-button') {
        const node = element('button', className, label);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    }
    function preview() {
        form.querySelector('[data-preview-type]').textContent = config.types[type.value] || text.choose_type;
        form.querySelector('[data-preview-prompt]').textContent = prompt.value.trim() || text.preview_empty;
        const area = form.querySelector('[data-preview-answers]');
        area.replaceChildren();
        if (type.value === 'opcion_multiple') options.forEach((row, index) => {
            const option = element('div', 'tp-preview-option');
            option.append(element('span', 'tp-preview-letter', String.fromCharCode(65 + index)), element('span', '', row.value.trim() || text.preview_option));
            area.append(option);
        });
        else if (type.value === 'relacion_columnas') pairs.forEach(row => {
            const pair = element('div', 'tp-preview-pair');
            pair.append(element('span', '', row.left || text.left), element('span', '', '→'), element('span', '', row.right || text.right));
            area.append(pair);
        });
        else if (type.value) area.append(element('div', 'tp-preview-written', text.preview_reference));
    }
    function rowInput(labelText, value, name, update, id, maxLength = 2000) {
        const wrapper = element('div', 'tp-option-input');
        const label = element('label', '', labelText);
        label.htmlFor = id;
        const input = element('input');
        Object.assign(input, { id, name, type: 'text', value, required: true, maxLength });
        input.addEventListener('input', () => { update(input.value); input.setCustomValidity(''); announce(''); preview(); });
        const error = element('p', 'tp-field-error');
        error.id = `${id}-error`;
        input.setAttribute('aria-describedby', error.id);
        input.addEventListener('invalid', () => { error.textContent = input.validationMessage; input.setAttribute('aria-invalid', 'true'); });
        input.addEventListener('input', () => { error.textContent = ''; input.removeAttribute('aria-invalid'); });
        const fieldKey = name.replace(/\[([^\]]+)\]/g, '.$1');
        if (config.errors?.[fieldKey]) { error.textContent = config.errors[fieldKey][0]; input.setAttribute('aria-invalid', 'true'); }
        wrapper.append(label, input, error);
        return { wrapper, input };
    }
    function render() {
        controls.replaceChildren();
        const fieldset = element('fieldset', 'tp-answer-fieldset');
        const legend = element('legend', 'tp-editor-legend');
        const hint = element('p', 'tp-editor-hint');
        const list = element('div', 'tp-answer-list');
        const multiple = type.value === 'opcion_multiple';
        const matching = type.value === 'relacion_columnas';
        if (!type.value) { preview(); return; }
        legend.textContent = matching ? text.pairs : multiple ? text.answers : text.reference;
        hint.textContent = matching ? text.pairs_hint : multiple ? text.multiple_hint : type.value === 'programacion' ? text.code_hint : text.reference_hint;
        hint.id = 'editor-answer-hint';
        fieldset.setAttribute('aria-describedby', hint.id);
        fieldset.append(legend, hint, list);
        const fieldError = config.errors?.[multiple ? 'opciones_lista' : 'pares_lista'] || config.errors?.correcta;
        if (fieldError) fieldset.append(element('p', 'tp-field-error', fieldError[0]));
        if (multiple || matching) {
            const rows = multiple ? options : pairs;
            rows.forEach((row, index) => {
                const wrapper = element('div', matching ? 'tp-pair-row' : 'tp-option-row');
                if (multiple) {
                    const radioLabel = element('label', 'tp-correct-choice');
                    const radio = element('input');
                    Object.assign(radio, { type: 'radio', name: 'correcta', value: String(index), checked: row.id === correct, required: true });
                    radio.setAttribute('aria-label', `${text.correct}: ${text.option} ${index + 1}`);
                    radio.addEventListener('change', () => { correct = row.id; announce(''); });
                    radioLabel.append(radio, element('span', '', text.correct));
                    wrapper.append(radioLabel, rowInput(`${text.option} ${String.fromCharCode(65 + index)}`, row.value, `opciones_lista[${index}]`, value => { row.value = value; }, `editor-option-${row.id}`).wrapper);
                } else {
                    wrapper.append(rowInput(`${text.left} ${index + 1}`, row.left, `pares_lista[${index}][izquierda]`, value => { row.left = value; }, `editor-left-${row.id}`).wrapper,
                        rowInput(`${text.right} ${index + 1}`, row.right, `pares_lista[${index}][derecha]`, value => { row.right = value; }, `editor-right-${row.id}`).wrapper);
                }
                const remove = button('×', () => {
                    if (rows.length <= 2) { announce(text.min_rows); return; }
                    if (multiple) { options = options.filter(option => option.id !== row.id); if (correct === row.id) correct = undefined; }
                    else pairs = pairs.filter(pair => pair.id !== row.id);
                    render();
                    controls.querySelectorAll('.tp-answer-list input[type="text"]')[Math.min(index, rows.length - 2)]?.focus();
                    announce(text.removed);
                }, 'tp-remove-row');
                remove.setAttribute('aria-label', `${text.remove} ${multiple ? text.option : text.left} ${index + 1}`);
                remove.disabled = rows.length <= 2;
                wrapper.append(remove);
                list.append(wrapper);
            });
            const add = button(multiple ? text.add_option : text.add_pair, () => {
                if (rows.length >= 10) { announce(text.limit); return; }
                if (multiple) options.push(createRow()); else pairs.push({ id: ++sequence, left: '', right: '' });
                render();
                const lastRow = controls.querySelector('.tp-answer-list').lastElementChild;
                lastRow.querySelector('input[type="text"]')?.focus();
                announce(text.added);
            });
            add.disabled = rows.length >= 10;
            fieldset.append(add);
        } else {
            const label = element('label', 'tp-detail-label', text.reference);
            label.htmlFor = 'editor-reference';
            const area = element('textarea', type.value === 'programacion' ? 'tp-reference-code' : '');
            Object.assign(area, { id: label.htmlFor, name: 'respuesta_modelo', rows: 6, value: reference, required: true, maxLength: 20000 });
            area.addEventListener('input', () => { reference = area.value; });
            list.append(label, area);
        }
        controls.append(fieldset);
        preview();
    }
    const marker = element('input');
    Object.assign(marker, { type: 'hidden', name: 'editor_estructurado', value: '1' });
    form.append(marker);
    // Enable enhancement only after parsing the saved input successfully.
    fallback.hidden = true;
    fallback.querySelectorAll('input,textarea,select').forEach(input => { input.disabled = true; });
    controls.hidden = false;
    type.addEventListener('change', () => { render(); announce(''); });
    prompt.addEventListener('input', preview);
    const submit = form.querySelector('[type=submit]');
    let saving = false;
    form.addEventListener('submit', event => {
        if (type.value === 'opcion_multiple') {
            const values = options.map(option => option.value.trim());
            if (new Set(values).size !== values.length) {
                event.preventDefault(); announce(text.duplicate);
                const firstDuplicate = values.findIndex((value, index) => values.indexOf(value) !== index);
                controls.querySelectorAll('input[type="text"]')[firstDuplicate]?.focus();
                return;
            }
        }
        if (saving) { event.preventDefault(); return; }
        if (!event.defaultPrevented) {
            saving = true;
            submit.disabled = true;
            form.setAttribute('aria-busy', 'true');
        }
    });
    window.addEventListener('pageshow', () => { saving = false; submit.disabled = false; form.removeAttribute('aria-busy'); });
    render();
    form.querySelector('[data-error-summary]')?.focus();
}

export function initializeQuestionEditors(root = document) {
    root.querySelectorAll('[data-question-editor]').forEach(form => {
        try { initializeQuestionEditor(form); }
        catch (error) { console.error('No se pudo abrir el editor de preguntas.', error); }
    });
    root.querySelectorAll('[data-open-editor]').forEach(link => link.addEventListener('click', () => {
        const composer = document.getElementById('nueva-pregunta');
        if (composer) {
            composer.open = true;
            composer.querySelector('select')?.focus({ preventScroll: true });
        }
    }));
    if (location.hash === '#nueva-pregunta') {
        const composer = document.getElementById('nueva-pregunta');
        if (composer) composer.open = true;
    }
}

initializeQuestionEditors();
