/*
 * Comportamientos de la interfaz del panel, sin dependencias externas:
 * menú lateral, menú de cuenta, ventanas modales,
 * avisos que se ocultan a los 5 s y copia del folio (Manual COD-WEB, secciones 6.16 a 6.18).
 */

const MILISEGUNDOS_DE_AVISO = 5000;

// Se usa el atributo hidden y no una clase de Tailwind para que gane siempre sobre display: flex.
function ocultarAviso(aviso) {
    aviso.hidden = true;
}

function programarOcultado(aviso) {
    const milisegundos = Number(aviso.dataset.autoocultar);

    if (!milisegundos) {
        return;
    }

    window.clearTimeout(aviso.temporizador);
    aviso.temporizador = window.setTimeout(() => ocultarAviso(aviso), milisegundos);
}

function iniciarAvisos() {
    document.querySelectorAll('[data-autoocultar]').forEach((aviso) => {
        programarOcultado(aviso);

        // Quien lee o usa el aviso con el puntero o el teclado gana tiempo para terminar.
        aviso.addEventListener('mouseenter', () => window.clearTimeout(aviso.temporizador));
        aviso.addEventListener('mouseleave', () => programarOcultado(aviso));
        aviso.addEventListener('focusin', () => window.clearTimeout(aviso.temporizador));
        aviso.addEventListener('focusout', () => programarOcultado(aviso));
    });
}

function mostrarAvisos(contenedor) {
    contenedor.querySelectorAll('[data-autoocultar]').forEach((aviso) => {
        aviso.hidden = false;
        programarOcultado(aviso);
    });
}

function alternarMenuLateral(forzarCierre = false) {
    const panel = document.getElementById('teacher-panel');
    const menu = document.getElementById('menu-lateral');
    const fondo = document.getElementById('menu-fondo');
    const boton = document.querySelector('button[data-menu-alternar]');

    if (!panel || !menu || !fondo || !boton) {
        return;
    }

    const estabaAbierto = panel.classList.contains('tp-menu-abierto');
    const debeAbrir = !forzarCierre && !estabaAbierto;

    if (estabaAbierto && !debeAbrir && (menu.contains(document.activeElement) || document.activeElement === document.body)) {
        boton.focus();
    }

    panel.classList.toggle('tp-menu-abierto', debeAbrir);
    menu.inert = !debeAbrir;
    fondo.classList.toggle('hidden', !debeAbrir);
    boton.setAttribute('aria-expanded', String(debeAbrir));
}

function alternarMenuDeCuenta(forzarCierre = false) {
    const menu = document.getElementById('menu-cuenta');
    const boton = document.querySelector('[data-cuenta-alternar]');

    if (!menu || !boton) {
        return;
    }

    const debeAbrir = forzarCierre ? false : menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !debeAbrir);
    boton.setAttribute('aria-expanded', String(debeAbrir));
}

async function copiarFolio(boton) {
    try {
        await navigator.clipboard.writeText(boton.dataset.copiar);
    } catch (error) {
        return;
    }

    const aviso = document.getElementById(boton.dataset.copiarAviso);

    if (aviso) {
        aviso.hidden = false;
        programarOcultado(aviso);
    }
}

document.addEventListener('click', (evento) => {
    const objetivo = evento.target;

    if (!(objetivo instanceof Element)) {
        return;
    }

    if (objetivo.closest('[data-menu-alternar]')) {
        alternarMenuLateral();

        return;
    }

    if (objetivo.closest('[data-cuenta-alternar]')) {
        alternarMenuDeCuenta();

        return;
    }

    if (!objetivo.closest('#menu-cuenta')) {
        alternarMenuDeCuenta(true);
    }

    const abrirModal = objetivo.closest('[data-modal-abrir]');

    if (abrirModal) {
        document.getElementById(abrirModal.dataset.modalAbrir)?.showModal();

        return;
    }

    const cerrarModal = objetivo.closest('[data-modal-cerrar]');

    if (cerrarModal) {
        cerrarModal.closest('dialog')?.close();

        return;
    }

    // Un clic sobre el fondo oscuro llega al propio <dialog>.
    if (objetivo instanceof HTMLDialogElement) {
        objetivo.close();

        return;
    }

    const cerrarAlerta = objetivo.closest('[data-alerta-cerrar]');

    if (cerrarAlerta) {
        cerrarAlerta.closest('[data-alerta]')?.remove();

        return;
    }

    const reiniciarAvisos = objetivo.closest('[data-reiniciar-avisos]');

    if (reiniciarAvisos) {
        const contenedor = document.getElementById(reiniciarAvisos.dataset.reiniciarAvisos);

        if (contenedor) {
            mostrarAvisos(contenedor);
        }

        return;
    }

    const copiar = objetivo.closest('[data-copiar]');

    if (copiar) {
        copiarFolio(copiar);
    }
});

document.addEventListener('keydown', (evento) => {
    if (evento.key !== 'Escape') {
        return;
    }

    alternarMenuDeCuenta(true);
    alternarMenuLateral(true);
});

iniciarAvisos();
