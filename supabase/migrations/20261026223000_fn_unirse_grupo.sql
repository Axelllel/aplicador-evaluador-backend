-- BigBitByte | Manual de Base de Datos v5.3
-- Inscripcion por folio, sin cuenta de Supabase Auth para el alumno.
-- Migracion incremental posterior a 20261026214500_rls_fase2_tablas_restantes.sql
-- NO crea/modifica tablas, NO desactiva RLS, NO autoriza examenes.
BEGIN;

CREATE OR REPLACE FUNCTION public.fn_unirse_grupo(
    p_folio TEXT,
    p_nombre TEXT,
    p_telefono TEXT DEFAULT NULL
)
RETURNS TABLE (
    alumno_id BIGINT,
    grupo_id BIGINT,
    materia VARCHAR(150)
)
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $func$
DECLARE
    v_grupo_id BIGINT;
    v_materia VARCHAR(150);
    v_max_alumnos INTEGER;
    v_total BIGINT;
    v_alumno_id BIGINT;
    v_folio TEXT := upper(btrim(p_folio));
    v_nombre TEXT := btrim(p_nombre);
    v_telefono TEXT := nullif(btrim(p_telefono), '');
BEGIN
    -- Comprobaciones del lado de PostgreSQL, sin confiar en Laravel.
    IF v_folio IS NULL OR v_folio !~ '^[A-Z0-9]{4,20}$'
       OR v_nombre IS NULL OR char_length(v_nombre) NOT BETWEEN 2 AND 150
       OR (v_telefono IS NOT NULL AND char_length(v_telefono) > 20) THEN
       RAISE EXCEPTION 'DATOS_INVALIDOS' USING ERRCODE = 'P1003';
    END IF;

    -- El folio pertenece a un grupo real y activo; el docente debe tener
    -- paquete vigente. Bloquear el grupo SERIALIZA altas simultaneas para
    -- que el limite de alumnos no sea superado por carreras entre peticiones.
    SELECT g.id, g.materia, p.max_alumnos
      INTO v_grupo_id, v_materia, v_max_alumnos
      FROM public.grupo AS g
      JOIN public.docente AS d ON d.id = g.docente_id
      JOIN public.paquete AS p ON p.id = d.paquete_id
     WHERE g.folio_codigo = v_folio
       AND g.activo = TRUE
       AND d.estado_pago = 'activo'
       AND EXISTS (
           SELECT 1
             FROM public.pago AS pa
            WHERE pa.docente_id = d.id
              AND pa.paquete_id = d.paquete_id
              AND pa.estado = 'confirmado'
              AND pa.fecha_inicio_vigencia <= CURRENT_TIMESTAMP
              AND pa.fecha_fin_vigencia > CURRENT_TIMESTAMP
       )
     FOR UPDATE OF g;

    IF v_grupo_id IS NULL THEN
       -- No revelar si el folio era inexistente, el grupo estaba apagado,
       -- o la suscripcion expiro: evitar enumeracion del catalogo.
       RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    SELECT count(*) INTO v_total
      FROM public.alumno_grupo AS ag
     WHERE ag.grupo_id = v_grupo_id;

    IF v_max_alumnos <= 0 OR v_total >= v_max_alumnos THEN
       RAISE EXCEPTION 'LIMITE_ALUMNOS' USING ERRCODE = 'P1001';
    END IF;

    -- El Manual v5.3 NO incluye identificador unico de estudiante.
    -- No fusionar alumnos por nombre: compartir nombre no acredita identidad.
    INSERT INTO public.alumno (nombre, telefono)
    VALUES (v_nombre, v_telefono)
    RETURNING id INTO v_alumno_id;

    INSERT INTO public.alumno_grupo (alumno_id, grupo_id)
    VALUES (v_alumno_id, v_grupo_id);

    RETURN QUERY SELECT v_alumno_id, v_grupo_id, v_materia;
END;
$func$;

-- PostgreSQL concede EXECUTE a PUBLIC por defecto en nuevas funciones:
-- revocar explicitamente y permitir solo al backend central.
REVOKE ALL ON FUNCTION public.fn_unirse_grupo(TEXT, TEXT, TEXT)
  FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_unirse_grupo(TEXT, TEXT, TEXT)
  TO laravel_app;

COMMIT;
