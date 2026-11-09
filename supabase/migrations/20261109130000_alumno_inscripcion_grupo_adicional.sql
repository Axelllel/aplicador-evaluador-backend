BEGIN;

-- Laravel proporciona el alumno de la sesión. El navegador solo aporta el folio.
CREATE OR REPLACE FUNCTION public.fn_unirse_otro_grupo(p_folio text, p_alumno_id bigint)
RETURNS TABLE (alumno_id bigint, grupo_id bigint, materia varchar)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_folio text := upper(btrim(p_folio));
    v_grupo_id bigint;
    v_materia varchar;
    v_max_alumnos integer;
BEGIN
    IF v_folio IS NULL OR v_folio !~ '^[A-Z0-9]{4,20}$'
       OR NOT EXISTS (SELECT 1 FROM public.alumno a WHERE a.id = p_alumno_id) THEN
        RAISE EXCEPTION 'DATOS_INVALIDOS' USING ERRCODE = 'P1003';
    END IF;

    SELECT g.id, g.materia, p.max_alumnos
    INTO v_grupo_id, v_materia, v_max_alumnos
    FROM public.grupo g
    JOIN public.docente d ON d.id = g.docente_id
    JOIN public.paquete p ON p.id = d.paquete_id
    WHERE g.folio_codigo = v_folio
      AND g.activo
      AND d.estado_pago = 'activo'
      AND EXISTS (
          SELECT 1 FROM public.pago pa
          WHERE pa.docente_id = d.id AND pa.paquete_id = d.paquete_id AND pa.estado = 'confirmado'
            AND CURRENT_TIMESTAMP >= pa.fecha_inicio_vigencia
            AND CURRENT_TIMESTAMP < pa.fecha_fin_vigencia
      )
    FOR UPDATE OF g;

    IF v_grupo_id IS NULL THEN
        RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM public.alumno_grupo ag WHERE ag.alumno_id = p_alumno_id AND ag.grupo_id = v_grupo_id
    ) THEN
        IF v_max_alumnos <= (
            SELECT count(*) FROM public.alumno_grupo ag WHERE ag.grupo_id = v_grupo_id
        ) THEN
            RAISE EXCEPTION 'LIMITE_ALUMNOS' USING ERRCODE = 'P1001';
        END IF;

        INSERT INTO public.alumno_grupo (alumno_id, grupo_id) VALUES (p_alumno_id, v_grupo_id);
    END IF;

    RETURN QUERY SELECT p_alumno_id, v_grupo_id, v_materia;
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_unirse_otro_grupo(text, bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_unirse_otro_grupo(text, bigint) TO laravel_app;

COMMIT;
