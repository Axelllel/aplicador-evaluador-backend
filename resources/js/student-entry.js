/**
 * Archivo: student-entry.js
 * Módulo: resources/js
 * Descripción: Interacciones del ingreso del alumno; usa API pública de Laravel.
 * No crea cuentas ni simula respuestas del servidor.
 */
const portal = document.getElementById('student-entry');
if (portal) {
    const $ = (id) => document.getElementById(id);
    let folio = '';
    let scanning = false;
    let stream = null;
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
        $('name-wrap').classList.toggle('has-error', !onFolio && !!message);
        $('folio').setAttribute('aria-invalid', String(onFolio && !!message));
        $('student-name').setAttribute('aria-invalid', String(!onFolio && !!message));
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
    $('back-button').addEventListener('click', () => { setStep(1); $('folio').focus(); });
    $('reset-button').addEventListener('click', () => {
        folio = '';
        $('folio').value = '';
        $('student-name').value = '';
        setStep(1);
    });
    $('student-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const nombre = $('student-name').value.trim().replace(/\s+/g, ' ');
        if (nombre.length < 2 || nombre.length > 150) {
            setMessage('Escribe un nombre válido de 2 a 150 caracteres.');
            return;
        }
        const button = $('join-button');
        button.disabled = true;
        button.textContent = 'Registrando…';
        setMessage('');
        try {
            const response = await fetch(portal.dataset.joinUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ folio_codigo: folio, nombre }),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || !json.data || json.data.estado !== 'inscrito') {
                const messages = {
                    404: 'No encontramos un grupo activo con ese folio. Verifica el código o consulta a tu docente.',
                    402: 'Este grupo alcanzó el límite de estudiantes. Consulta a tu docente.',
                    422: 'No pudimos validar tus datos. Revisa el folio y tu nombre.',
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
                    materia: json.data.materia, nombre,
                }));
            } catch (_) { /* El registro ya existe en el servidor. */ }
            $('success-title').textContent = `¡Todo listo, ${nombre.split(' ')[0]}!`;
            $('success-subject').textContent = json.data.materia || 'Grupo registrado';
            setStep(3);
        } catch (error) {
            setMessage(error instanceof TypeError
                ? 'No fue posible conectar con el servidor. Comprueba tu conexión e inténtalo nuevamente.'
                : (error.message || 'No fue posible completar el ingreso.'));
        } finally {
            button.disabled = false;
            button.textContent = 'Unirme al grupo →';
        }
    });

    $('help-button').addEventListener('click', () => $('help-dialog').showModal());
    const closeHelp = () => $('help-dialog').close();
    $('close-help').addEventListener('click', closeHelp);
    $('help-ok').addEventListener('click', closeHelp);

    const stopCamera = () => {
        scanning = false;
        if (stream) { stream.getTracks().forEach((track) => track.stop()); stream = null; }
        $('qr-video').srcObject = null;
    };
    const closeQr = () => { stopCamera(); $('qr-dialog').close(); };
    $('close-qr').addEventListener('click', closeQr);
    $('qr-cancel').addEventListener('click', closeQr);
    $('qr-dialog').addEventListener('close', stopCamera);
    $('scan-qr').addEventListener('click', async () => {
        $('qr-dialog').showModal();
        $('scanner-message').textContent = 'Preparando cámara…';
        if (!navigator.mediaDevices?.getUserMedia || !('BarcodeDetector' in window)) {
            $('scanner-message').textContent = 'Este navegador no permite escanear QR aquí. Puedes ingresar el folio manualmente.';
            return;
        }
        try {
            const formats = await window.BarcodeDetector.getSupportedFormats();
            if (!formats.includes('qr_code')) throw new Error('QR no compatible');
            const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            const video = $('qr-video');
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            scanning = true;
            $('scanner-message').textContent = 'Apunta al código QR de tu docente.';
            const scan = async () => {
                if (!scanning) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length > 0) {
                        let raw = codes[0].rawValue.trim();
                        // Un QR puede contener el folio directamente o en un enlace ?folio=...
                        try { const url = new URL(raw); raw = url.searchParams.get('folio') || raw; } catch (_) { /* Folio directo. */ }
                        closeQr();
                        if (await acceptFolio(raw)) return;
                    }
                } catch (_) { /* Otro fotograma. */ }
                if (scanning) requestAnimationFrame(scan);
            };
            requestAnimationFrame(scan);
        } catch (_) {
            stopCamera();
            $('qr-video').hidden = true;
            $('scanner-message').textContent = 'No fue posible usar la cámara. Ingresa el folio manualmente.';
        }
    });
}
