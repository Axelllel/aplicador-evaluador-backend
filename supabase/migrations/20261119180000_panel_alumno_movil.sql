BEGIN;

-- El PIN se verifica en el backend. El cliente nunca envía un alumno_id confiable.
CREATE OR REPLACE FUNCTION public.fn_panel_alumno_movil(p_folio text, p_codigo_hash text)
RETURNS jsonb
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_alumno bigint;
    v_grupo bigint;
    v_nombre varchar;
    v_materia varchar;
    v_activo boolean;
    v_grupos jsonb;
    v_examenes jsonb;
    v_entregas jsonb := '[]'::jsonb;
    v_resultado jsonb;
    v_intento record;
BEGIN
    SELECT c.alumno_id INTO v_alumno
    FROM public.credencial_alumno c
    WHERE c.codigo_hash = p_codigo_hash AND c.revocado_en IS NULL;

    IF v_alumno IS NULL THEN
        RAISE EXCEPTION 'CREDENCIAL_NO_VALIDA' USING ERRCODE = 'P1004';
    END IF;

    SELECT g.id, g.materia, a.nombre, g.activo INTO v_grupo, v_materia, v_nombre, v_activo
    FROM public.alumno_grupo ag
    JOIN public.grupo g ON g.id = ag.grupo_id
    JOIN public.alumno a ON a.id = ag.alumno_id
    WHERE ag.alumno_id = v_alumno
      AND g.folio_codigo = pg_catalog.upper(pg_catalog.btrim(p_folio));

    IF v_grupo IS NULL THEN
        RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    SELECT coalesce(jsonb_agg(jsonb_build_object(
        'id', g.id, 'materia', g.materia, 'folio_codigo', g.folio_codigo,
        'activo', g.activo, 'docente', d.nombre
    ) ORDER BY g.id), '[]'::jsonb) INTO v_grupos
    FROM public.alumno_grupo ag
    JOIN public.grupo g ON g.id = ag.grupo_id
    JOIN public.docente d ON d.id = g.docente_id
    WHERE ag.alumno_id = v_alumno;

    SELECT coalesce(jsonb_agg(jsonb_build_object(
        'id', e.id, 'nombre', e.nombre, 'materia', v_materia,
        'fecha_apertura', e.fecha_apertura, 'fecha_cierre', e.fecha_cierre,
        'total_preguntas', datos.total, 'duracion_segundos', datos.duracion,
        'estado', CASE
            WHEN EXISTS (SELECT 1 FROM public.intento_examen i
                         WHERE i.examen_id = e.id AND i.alumno_id = v_alumno AND i.estado = 'finalizado')
                THEN 'completado'
            WHEN NOT v_activo OR e.fecha_cierre <= now() THEN 'cerrado'
            WHEN e.fecha_apertura > now() THEN 'proximamente'
            ELSE 'disponible' END
    ) ORDER BY e.fecha_apertura, e.id), '[]'::jsonb) INTO v_examenes
    FROM public.examen e
    CROSS JOIN LATERAL (
        SELECT count(*) AS total, coalesce(sum(ep.tiempo_segundos), 0) AS duracion
        FROM public.apartado a
        JOIN public.examen_pregunta ep ON ep.apartado_id = a.id
        WHERE a.examen_id = e.id
    ) datos
    WHERE e.grupo_id = v_grupo AND e.publicado
      AND e.plataforma_permitida IN ('movil', 'ambas');

    FOR v_intento IN
        SELECT i.id, i.token_sesion, i.fecha_entrega, e.nombre, e.escala, e.resultado_visible
        FROM public.intento_examen i
        JOIN public.examen e ON e.id = i.examen_id
        WHERE i.alumno_id = v_alumno AND e.grupo_id = v_grupo AND i.estado = 'finalizado'
        ORDER BY i.fecha_entrega DESC NULLS LAST, i.id DESC
    LOOP
        v_resultado := NULL;
        IF v_intento.resultado_visible <> 'oculto' THEN
            BEGIN
                v_resultado := public.fn_resultado_alumno(v_intento.token_sesion);
            EXCEPTION WHEN SQLSTATE 'P0001' THEN
                v_resultado := NULL;
            END;
        END IF;
        v_entregas := v_entregas || jsonb_build_array(jsonb_build_object(
            'id', v_intento.id, 'nombre', v_intento.nombre, 'materia', v_materia,
            'fecha_entrega', v_intento.fecha_entrega, 'escala', v_intento.escala,
            'estado', CASE WHEN v_intento.resultado_visible = 'oculto' THEN 'sin_publicar'
                           WHEN v_resultado IS NULL THEN 'en_revision'
                           ELSE v_resultado->>'estado' END,
            'calificacion', v_resultado->'calificacion'
        ));
    END LOOP;

    RETURN jsonb_build_object(
        'alumno', jsonb_build_object('id', v_alumno, 'nombre', v_nombre, 'grupo_id', v_grupo),
        'grupos', v_grupos, 'examenes', v_examenes, 'entregas', v_entregas
    );
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_panel_alumno_movil(text, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_panel_alumno_movil(text, text) TO laravel_app;

COMMIT;
