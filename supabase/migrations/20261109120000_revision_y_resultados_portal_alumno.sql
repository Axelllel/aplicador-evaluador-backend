BEGIN;

CREATE OR REPLACE FUNCTION public.fn_preguntas_portal_alumno(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_preguntas jsonb;
    v_datos jsonb;
BEGIN
    v_preguntas := public.fn_preguntas_intento(p_token);

    SELECT jsonb_build_object('nombre', e.nombre, 'respuestas', coalesce((
        SELECT jsonb_object_agg(r.examen_pregunta_id, r.respuesta_texto)
        FROM public.respuesta_alumno r
        JOIN public.examen_pregunta ep ON ep.id = r.examen_pregunta_id
        JOIN public.apartado a ON a.id = ep.apartado_id AND a.examen_id = e.id
        WHERE r.intento_id = i.id
    ), '{}'::jsonb))
    INTO v_datos
    FROM public.intento_examen i
    JOIN public.examen e ON e.id = i.examen_id
    WHERE i.token_sesion = p_token AND i.estado = 'en_progreso';

    RETURN v_preguntas || v_datos;
END;
$fn$;

-- Una pregunta sin respuesta cuenta como cero y no requiere revisión del docente.
CREATE OR REPLACE FUNCTION public.fn_resultado_alumno(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_examen bigint;
    v_intento bigint;
    v_visible text;
    v_escala text;
    v_total integer;
    v_calificadas integer;
    v_nota numeric;
BEGIN
    SELECT i.id, e.id, e.resultado_visible, e.escala
    INTO v_intento, v_examen, v_visible, v_escala
    FROM public.intento_examen i
    JOIN public.examen e ON e.id = i.examen_id
    WHERE i.token_sesion = p_token AND i.estado = 'finalizado';

    IF v_intento IS NULL OR v_visible = 'oculto' THEN
        RAISE EXCEPTION 'Resultado no disponible' USING ERRCODE = 'P0001';
    END IF;

    SELECT count(*),
           count(*) FILTER (WHERE r.id IS NULL OR r.calificacion_final IS NOT NULL),
           coalesce(sum(r.calificacion_final * ep.ponderacion / 100), 0)
    INTO v_total, v_calificadas, v_nota
    FROM public.apartado a
    JOIN public.examen_pregunta ep ON ep.apartado_id = a.id
    LEFT JOIN public.respuesta_alumno r ON r.intento_id = v_intento AND r.examen_pregunta_id = ep.id
    WHERE a.examen_id = v_examen;

    IF v_visible = 'final' AND v_calificadas <> v_total THEN
        RAISE EXCEPTION 'Resultado final pendiente de revision' USING ERRCODE = 'P0001';
    END IF;

    RETURN jsonb_build_object(
        'intento_id', v_intento, 'escala', v_escala,
        'estado', CASE WHEN v_calificadas = v_total THEN 'final' ELSE 'aproximado' END,
        'calificacion', round(CASE WHEN v_escala = '0-10' THEN v_nota / 10 ELSE v_nota END, 2),
        'calificadas', v_calificadas, 'total_preguntas', v_total
    );
END;
$fn$;

CREATE OR REPLACE FUNCTION public.fn_revision_portal_alumno(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_intento bigint;
    v_examen bigint;
    v_nombre text;
    v_entrega timestamptz;
    v_nota jsonb;
    v_preguntas jsonb;
    v_total integer;
    v_revisadas integer;
BEGIN
    SELECT i.id, e.id, e.nombre, i.fecha_entrega
    INTO v_intento, v_examen, v_nombre, v_entrega
    FROM public.intento_examen i
    JOIN public.examen e ON e.id = i.examen_id
    WHERE i.token_sesion = p_token AND i.estado = 'finalizado';

    IF v_intento IS NULL THEN
        RAISE EXCEPTION 'Intento no disponible' USING ERRCODE = 'P0001';
    END IF;

    BEGIN
        v_nota := public.fn_resultado_alumno(p_token);
    EXCEPTION WHEN SQLSTATE 'P0001' THEN
        v_nota := NULL;
    END;

    SELECT count(*),
           count(*) FILTER (WHERE r.id IS NULL OR r.calificacion_final IS NOT NULL),
           coalesce(jsonb_agg(
               jsonb_build_object('tipo', p.tipo, 'enunciado', p.enunciado)
               || CASE WHEN v_nota IS NOT NULL THEN
                   jsonb_build_object('respondida', r.id IS NOT NULL,
                       'puntaje', CASE WHEN r.id IS NULL THEN 0 ELSE r.calificacion_final END)
                  ELSE '{}'::jsonb END
               ORDER BY CASE WHEN e.orden = 'aleatorio' THEN md5(ep.id::text || p_token::text)
                             ELSE lpad(ep.id::text, 20, '0') END
           ), '[]'::jsonb)
    INTO v_total, v_revisadas, v_preguntas
    FROM public.apartado a
    JOIN public.examen e ON e.id = a.examen_id
    JOIN public.examen_pregunta ep ON ep.apartado_id = a.id
    JOIN public.pregunta p ON p.id = ep.pregunta_id
    LEFT JOIN public.respuesta_alumno r ON r.intento_id = v_intento AND r.examen_pregunta_id = ep.id
    WHERE a.examen_id = v_examen;

    RETURN coalesce(v_nota, jsonb_build_object('estado', 'pendiente'))
        || jsonb_build_object('nombre', v_nombre, 'fecha_entrega', v_entrega,
             'preguntas', v_preguntas, 'total_preguntas', v_total, 'calificadas', v_revisadas);
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_preguntas_portal_alumno(uuid), public.fn_revision_portal_alumno(uuid), public.fn_resultado_alumno(uuid)
    FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_preguntas_portal_alumno(uuid), public.fn_revision_portal_alumno(uuid), public.fn_resultado_alumno(uuid)
    TO laravel_app;

COMMIT;
