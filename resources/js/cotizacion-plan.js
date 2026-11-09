const formulario = document.querySelector('[data-configurador-plan]');

if (formulario) {
    const tarifas = JSON.parse(formulario.dataset.configuradorPlan);
    const campoGrupos = formulario.elements.namedItem('grupos');
    const campoAlumnos = formulario.elements.namedItem('alumnos');
    const campoPreguntas = formulario.elements.namedItem('preguntas');
    const precio = formulario.querySelector('[data-precio-plan]');
    const cupo = formulario.querySelector('[data-cupo-plan]');
    const moneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    const actualizarCotizacion = () => {
        const grupos = Number(campoGrupos.value);
        const alumnosPorGrupo = Number(campoAlumnos.value);
        const preguntas = Number(campoPreguntas.value);
        const cupoTotal = grupos * alumnosPorGrupo;
        const centavos = tarifas.base + grupos * tarifas.grupo + cupoTotal * tarifas.alumno + preguntas * tarifas.pregunta;

        cupo.value = cupoTotal;
        precio.value = moneda.format(centavos / 100);
    };

    formulario.addEventListener('change', actualizarCotizacion);
    actualizarCotizacion();
}
