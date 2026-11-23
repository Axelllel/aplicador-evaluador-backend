-- BigBitByte: inicio móvil con identidad persistente, sin sesión web ni pase docente.
-- Requiere las migraciones del flujo de examen y de credencial_alumno.
BEGIN;

CREATE OR REPLACE FUNCTION public.fn_iniciar_intento_movil(
    p_folio text, p_codigo_hash text, p_examen_id bigint
)
RETURNS TABLE (intento_id bigint, token_sesion uuid, fecha_inicio timestamptz, reanudado boolean)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_alumno bigint;
    v_intento public.intento_examen%ROWTYPE;
BEGIN
    SELECT c.alumno_id INTO v_alumno
    FROM public.credencial_alumno c
    WHERE c.codigo_hash = p_codigo_hash AND c.revocado_en IS NULL;

    IF v_alumno IS NULL THEN
        RAISE EXCEPTION 'CREDENCIAL_NO_VALIDA' USING ERRCODE = 'P1004';
    END IF;

    -- Se comparte el bloqueo con fn_iniciar_intento: reintentos de red y otras
    -- plataformas no crean intentos duplicados ni reinician la fecha original.
    PERFORM pg_catalog.pg_advisory_xact_lock(
        pg_catalog.hashtextextended(p_examen_id::text || ':' || v_alumno::text, 0)
    );

    IF NOT EXISTS (
        SELECT 1 FROM public.examen e
        JOIN public.grupo g ON g.id = e.grupo_id
        JOIN public.alumno_grupo ag ON ag.grupo_id = g.id AND ag.alumno_id = v_alumno
        JOIN public.docente d ON d.id = g.docente_id
        WHERE e.id = p_examen_id
          AND g.folio_codigo = pg_catalog.upper(pg_catalog.btrim(p_folio))
          AND e.publicado AND g.activo
          AND e.plataforma_permitida IN ('movil', 'ambas')
          AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre
          AND d.estado_pago = 'activo'
          AND EXISTS (
              SELECT 1 FROM public.pago pa
              WHERE pa.docente_id = d.id AND pa.paquete_id = d.paquete_id
                AND pa.estado = 'confirmado'
                AND CURRENT_TIMESTAMP >= pa.fecha_inicio_vigencia
                AND CURRENT_TIMESTAMP < pa.fecha_fin_vigencia
          )
    ) THEN
        RAISE EXCEPTION 'EXAMEN_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    SELECT i.* INTO v_intento
    FROM public.intento_examen i
    WHERE i.examen_id = p_examen_id AND i.alumno_id = v_alumno
    FOR UPDATE;

    IF FOUND THEN
        IF v_intento.estado <> 'en_progreso' THEN
            RAISE EXCEPTION 'INTENTO_CERRADO' USING ERRCODE = 'P1006';
        END IF;
        RETURN QUERY SELECT v_intento.id, v_intento.token_sesion, v_intento.fecha_inicio, true;
    ELSE
        RETURN QUERY
        SELECT i.intento_id, i.token_sesion, i.fecha_inicio, false
        FROM public.fn_iniciar_intento(p_examen_id, v_alumno, 'movil') i;
    END IF;
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_iniciar_intento_movil(text, text, bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_iniciar_intento_movil(text, text, bigint) TO laravel_app;

COMMIT;
