/**
 * Archivo: student-entry.js
 * Módulo: resources/js
 * Descripción: Interacciones del ingreso del alumno; usa API pública de Laravel.
 * No crea cuentas ni simula respuestas del servidor.
 */
import { createStudentQrScanner } from './student-qr';

const portal = document.getElementById('student-entry');
if (portal) {
    const $ = (id) => document.getElementById(id);
    let folio = '';
    let entryMode = 'nuevo';
    const steps = [$('entry-step-one'), $('entry-step-two'), $('entry-step-success')];
    // Mensajes visibles junto al campo correspondiente (folio o nombre).
    const setMessage = (message) => {
        const onFolio = !$('entry-step-one').hidden;
        for (const [id, active] of [['folio-feedback', onFolio], ['name-feedback', !onFolio]]) {
            const element = $(id);
            element.textContent = active ? message : '';
            element.hidden = !active || !message;
        }
        $('folio-wrap').classList.toggle('has-error', onFolio && !!message);
        $('name-wrap').classList.toggle('has-error', !onFolio && entryMode === 'nuevo' && !!message);
        $('code-wrap').classList.toggle('has-error', !onFolio && entryMode === 'existente' && !!message);
        $('folio').setAttribute('aria-invalid', String(onFolio && !!message));
        $('student-name').setAttribute('aria-invalid', String(!onFolio && entryMode === 'nuevo' && !!message));
        $('student-code').setAttribute('aria-invalid', String(!onFolio && entryMode === 'existente' && !!message));
    };
    const setStep = (step) => {
        steps.forEach((node, index) => { node.hidden = index !== step - 1; });
        $('step-indicator-1').classList.toggle('complete', step >= 2);
        $('step-indicator-1').classList.toggle('active', step === 1);
        $('step-indicator-2').classList.toggle('active', step === 2);
        $('step-indicator-line').classList.toggle('complete', step >= 2);
        setMessage('');
    };
    const saveTheme = (dark) => {
        portal.classList.toggle('dark', dark);
        $('toggle-theme').textContent = dark ? '☀' : '☾';
        $('toggle-theme').setAttribute('aria-label', dark ? 'Activar modo claro' : 'Activar modo oscuro');
        try { localStorage.setItem('bbb-theme', dark ? 'dark' : 'light'); } catch (_) { /* Opcional. */ }
    };
    try { saveTheme(localStorage.getItem('bbb-theme') === 'dark'); } catch (_) { saveTheme(false); }
    $('toggle-theme').addEventListener('click', () => saveTheme(!portal.classList.contains('dark')));

    const acceptFolio = async (value) => {
        const code = value.trim().toUpperCase();
        if (!/^[A-Z0-9]{4,20}$/.test(code)) {
            setMessage('El folio debe tener entre 4 y 20 letras o números, sin guiones.');
            return false;
        }
        const button = $('folio-form').querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Verificando…';
        setMessage('');
        try {
            const response = await fetch(portal.dataset.validateUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ folio_codigo: code }),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || json.data?.valido !== true) {
                const messages = {
                    404: 'No encontramos un grupo activo con ese folio. Verifica el código o consulta a tu docente.',
                    422: 'El folio no tiene un formato válido.',
                    429: 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
                    419: 'Tu sesión expiró. Actualiza la página e inténtalo nuevamente.',
                    503: 'No fue posible verificar el grupo. Inténtalo más tarde.',
                };
                throw new Error(messages[response.status] || 'No pudimos verificar el folio. Inténtalo nuevamente.');
            }
            folio = code;
            $('folio').value = code;
            $('folio-preview').textContent = code;
            setStep(2);
            $('student-name').focus();
            return true;
        } catch (error) {
            setMessage(error instanceof TypeError
                ? 'No se pudo conectar con el servidor. Revisa tu conexión.'
                : (error.message || 'No se pudo validar el grupo.'));
            return false;
        } finally {
            button.disabled = false;
            button.textContent = 'Continuar →';
        }
    };
    $('folio-form').addEventListener('submit', (event) => {
        event.preventDefault();
        void acceptFolio($('folio').value);
    });
    const setMode = (mode) => {
        entryMode = mode;
        const isNew = mode === 'nuevo';
        $('new-student-fields').hidden = !isNew;
        $('returning-student-fields').hidden = isNew;
        $('mode-new').classList.toggle('active', isNew);
        $('mode-returning').classList.toggle('active', !isNew);
        $('mode-new').setAttribute('aria-pressed', String(isNew));
        $('mode-returning').setAttribute('aria-pressed', String(!isNew));
        $('join-button').textContent = isNew ? 'Crear perfil y unirme →' : 'Ingresar al grupo →';
        $('transition-status').textContent = isNew
            ? 'Te mostraremos tu código personal al terminar. Guárdalo.'
            : 'Tu código recupera el mismo perfil y tus resultados.';
        setMessage('');
    };
    $('mode-new').addEventListener('click', () => setMode('nuevo'));
    $('mode-returning').addEventListener('click', () => setMode('existente'));
    $('back-button').addEventListener('click', () => { setStep(1); $('folio').focus(); });
    $('reset-button').addEventListener('click', () => {
        folio = '';
        $('folio').value = '';
        if (!$('student-name').readOnly) $('student-name').value = '';
        $('student-code').value = '';
        $('success-code-box').hidden = true;
        $('copy-code').textContent = 'Copiar código';
        setMode('nuevo');
        setStep(1);
    });
    $('student-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const nombre = $('student-name').value.trim().replace(/\s+/g, ' ');
        const codigo_acceso = $('student-code').value.trim().toUpperCase();
        if (entryMode === 'nuevo' && (nombre.length < 2 || nombre.length > 150)) {
            setMessage('Escribe un nombre válido de 2 a 150 caracteres.');
            return;
        }
        if (entryMode === 'existente' && !/^[A-Z]{4}[0-9]{2}$/.test(codigo_acceso)) {
            setMessage('Escribe tu código con 4 letras y 2 números.');
            return;
        }
        const button = $('join-button');
        button.disabled = true;
        button.textContent = entryMode === 'nuevo' ? 'Creando perfil…' : 'Verificando código…';
        setMessage('');
        try {
            const payload = { folio_codigo: folio };
            if (entryMode === 'nuevo') payload.nombre = nombre;
            else payload.codigo_acceso = codigo_acceso;
            const response = await fetch(portal.dataset.joinUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(payload),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || !json.data || json.data.estado !== 'inscrito') {
                const messages = {
                    404: 'No encontramos un grupo activo con ese folio. Verifica el código o consulta a tu docente.',
                    402: 'Este grupo alcanzó el límite de estudiantes. Consulta a tu docente.',
                    401: 'No reconocimos la combinación de folio y código. Verifícala o consulta a tu docente.',
                    422: 'No pudimos validar tus datos. Revisa el folio, nombre o código.',
                    429: 'Demasiados intentos. Espera un minuto antes de volver a intentar.',
                    419: 'Tu sesión expiró. Actualiza la página e inténtalo nuevamente.',
                };
                // Un folio no disponible se corrige desde el primer paso, sin registrar al alumno.
                if (response.status === 404) {
                    setStep(1);
                    $('folio').focus();
                }
                throw new Error(messages[response.status]
                    || (typeof json.message === 'string' ? json.message : 'No fue posible registrar al estudiante.'));
            }
            // Etiquetas para el navegador; la autorización real se guarda en sesión Laravel.
            try {
                sessionStorage.setItem('bbb-student', JSON.stringify({
                    alumno_id: json.data.alumno_id, grupo_id: json.data.grupo_id,
                    materia: json.data.materia, nombre: json.data.nombre,
                }));
            } catch (_) { /* El registro ya existe en el servidor. */ }
            const nombreAlumno = json.data.nombre || nombre;
            $('success-title').textContent = `¡Todo listo, ${nombreAlumno.split(' ')[0]}!`;
            $('success-description').textContent = json.data.modo === 'reingreso'
                ? 'Reconocimos tu perfil y vinculamos el grupo.'
                : 'Tu perfil y el grupo quedaron registrados.';
            $('success-subject').textContent = json.data.materia || 'Grupo registrado';
            $('success-code-box').hidden = !json.data.codigo_acceso;
            $('success-code').textContent = json.data.codigo_acceso || '';
            if (json.data.home_url) $('success-home').href = json.data.home_url;
            setStep(3);
        } catch (error) {
            setMessage(error instanceof TypeError
                ? 'No fue posible conectar con el servidor. Comprueba tu conexión e inténtalo nuevamente.'
                : (error.message || 'No fue posible completar el ingreso.'));
        } finally {
            button.disabled = false;
            button.textContent = entryMode === 'nuevo' ? 'Crear perfil y unirme →' : 'Ingresar al grupo →';
        }
    });

    $('copy-code').addEventListener('click', async () => {
        const codigo = $('success-code').textContent;
        try {
            await navigator.clipboard.writeText(codigo);
            $('copy-code').textContent = 'Copiado';
        } catch (_) {
            $('copy-code').textContent = codigo;
        }
    });

    $('help-button').addEventListener('click', () => $('help-dialog').showModal());
    const closeHelp = () => $('help-dialog').close();
    $('close-help').addEventListener('click', closeHelp);
    $('help-ok').addEventListener('click', closeHelp);

    const scanner = createStudentQrScanner($('qr-video'));
    const closeQr = () => $('qr-dialog').close();
    $('close-qr').addEventListener('click', closeQr);
    $('qr-cancel').addEventListener('click', closeQr);
    $('qr-dialog').addEventListener('close', scanner.stop);
    $('scan-qr').addEventListener('click', () => {
        $('qr-dialog').showModal();
        void scanner.start({
            onStatus: message => { $('scanner-message').textContent = message; },
            onCode: code => {
                closeQr();
                void acceptFolio(code);
            },
        });
    });
}
