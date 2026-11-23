/**
 * Archivo: selector-fecha.js
 * Módulo: resources/js
 * Descripción: Calendario con hora (Air Datepicker, MIT) para los campos [data-selector-fecha]. Bloquea fechas
 *              pasadas y limita el cierre a la ventana permitida. Sin JavaScript, el campo nativo sigue funcionando.
 * Autor: Leonardo Ballestero López
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Versión inicial
 */
import AirDatepicker from 'air-datepicker';
import localeEs from 'air-datepicker/locale/es';
import 'air-datepicker/air-datepicker.css';

// El paquete publica el idioma como CommonJS; según el empaquetador llega envuelto en «default».
const idioma = localeEs.default ?? localeEs;
const DIAS_MAXIMOS = 30;
const dosDigitos = numero => String(numero).padStart(2, '0');

/** Convierte «2026-11-23T10:00» o «2026-11-23 10:00» en Date local; devuelve null si no es válida. */
function leerFecha(texto) {
    const coincidencia = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec((texto || '').trim());
    if (!coincidencia) return null;
    const [, anio, mes, dia, hora, minuto] = coincidencia.map(Number);
    return new Date(anio, mes - 1, dia, hora, minuto);
}

/** Texto que se envía al servidor: «AAAA-MM-DD HH:mm». */
function escribirFecha(fecha) {
    return `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())} ${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`;
}

function crearSelector(campo, opciones = {}) {
    const inicial = leerFecha(campo.value);
    campo.type = 'text';
    campo.autocomplete = 'off';
    campo.placeholder = 'AAAA-MM-DD HH:mm';
    if (inicial) campo.value = escribirFecha(inicial);

    return new AirDatepicker(campo, {
        locale: idioma,
        timepicker: true,
        timeFormat: 'HH:mm',
        dateFormat: 'yyyy-MM-dd',
        minutesStep: 5,
        autoClose: false,
        isMobile: window.matchMedia('(max-width: 640px)').matches,
        selectedDates: inicial ? [inicial] : [],
        ...opciones,
    });
}

function iniciar() {
    const apertura = document.querySelector('[data-selector-fecha="apertura"]');
    const cierre = document.querySelector('[data-selector-fecha="cierre"]');
    if (!apertura && !cierre) return;

    let selectorCierre = null;

    /** El cierre no puede ser anterior a la apertura ni exceder la ventana máxima. */
    const acotarCierre = fechaApertura => {
        if (!selectorCierre) return;
        const desde = fechaApertura ?? new Date();
        const hasta = new Date(desde.getTime() + DIAS_MAXIMOS * 24 * 60 * 60 * 1000);
        selectorCierre.update({ minDate: desde, maxDate: hasta });
    };

    const selectorApertura = apertura && crearSelector(apertura, {
        minDate: new Date(),
        onSelect: ({ date }) => acotarCierre(Array.isArray(date) ? date[0] : date),
    });

    if (cierre) {
        const aperturaInicial = leerFecha(apertura?.value ?? '');
        const desde = aperturaInicial ?? new Date();
        selectorCierre = crearSelector(cierre, {
            minDate: desde,
            maxDate: new Date(desde.getTime() + DIAS_MAXIMOS * 24 * 60 * 60 * 1000),
        });
    }

    // Si el docente borra la apertura, el cierre vuelve a su límite por defecto.
    selectorApertura && apertura.addEventListener('change', () => {
        if (!apertura.value) acotarCierre(null);
    });
}

iniciar();
