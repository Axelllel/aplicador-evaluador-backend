-- ============================================================
-- BIGBITBYTE | Manual de Base de Datos v5.3 - seccion 5.5
-- Registro controlado de respuestas del alumno (fase 1).
-- Aplicacion: npx supabase migration up --local
-- NO activa RLS; la migracion RLS sera independiente y posterior.
-- NO modifica el esquema de las 17 tablas existentes.
-- ============================================================

BEGIN;

-- Las respuestas del alumno nunca se escriben mediante SQL directo
-- expuesto al cliente. Solo la API Laravel, usando laravel_app,
-- puede invocar esta funcion con un token temporal validado.
-- ADVERTENCIA: revocar INSERT/UPDATE directos sobre las tablas del
-- alumno al activar RLS. Esta migracion por si sola NO los revoca.
CREATE OR REPLACE FUNCTION public.fn_registrar_respuesta(
    p_token_sesion UUID,
    p_examen_pregunta_id BIGINT,
    p_respuesta_texto TEXT
)
RETURNS BIGINT
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $func$
DECLARE
    v_intento_id BIGINT;
    v_examen_id BIGINT;
    v_respuesta_id BIGINT;
BEGIN
    -- Bloquear el intento mientras se registra la respuesta.
    -- Evita una carrera con el cambio a estado 'finalizado'.
    -- El alumno debe seguir vinculado al grupo y el examen debe
    -- encontrarse publicado, activo y dentro de la ventana temporal.
    SELECT i.id, i.examen_id
      INTO v_intento_id, v_examen_id
      FROM public.intento_examen AS i
      JOIN public.examen AS e ON e.id = i.examen_id
      JOIN public.grupo AS g ON g.id = e.grupo_id
      JOIN public.alumno_grupo AS ag
        ON ag.alumno_id = i.alumno_id AND ag.grupo_id = g.id
     WHERE i.token_sesion = p_token_sesion
       AND i.estado = 'en_progreso'
       AND g.activo = TRUE
       AND e.publicado = TRUE
       AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre
     FOR UPDATE OF i;

    IF v_intento_id IS NULL THEN
        RAISE EXCEPTION 'Sesion invalida o examen no disponible'
          USING ERRCODE = 'P0001';
    END IF;

    -- La pregunta debe pertenecer al examen real del intento.
    -- Ademas, la pregunta debe ser propiedad del docente del grupo.
    IF NOT EXISTS (
        SELECT 1
          FROM public.examen_pregunta AS ep
          JOIN public.apartado AS a ON a.id = ep.apartado_id
          JOIN public.examen AS e ON e.id = a.examen_id
          JOIN public.grupo AS g ON g.id = e.grupo_id
          JOIN public.pregunta AS p ON p.id = ep.pregunta_id
         WHERE ep.id = p_examen_pregunta_id
           AND a.examen_id = v_examen_id
           AND p.docente_id = g.docente_id
    ) THEN
        RAISE EXCEPTION 'La pregunta no pertenece al examen del intento'
          USING ERRCODE = 'P0001';
    END IF;

    -- El UNIQUE(intento_id, examen_pregunta_id) del DDL v5.3
    -- garantiza una sola respuesta por pregunta e intento.
    -- Si ya hay calificacion o revision, no permitir sobrescribirla.
    INSERT INTO public.respuesta_alumno
        (intento_id, examen_pregunta_id, respuesta_texto)
    VALUES
        (v_intento_id, p_examen_pregunta_id, p_respuesta_texto)
    ON CONFLICT (intento_id, examen_pregunta_id)
    DO UPDATE SET respuesta_texto = EXCLUDED.respuesta_texto
      WHERE public.respuesta_alumno.revisada_manual = FALSE
        AND public.respuesta_alumno.puntaje_ia IS NULL
        AND public.respuesta_alumno.calificacion_final IS NULL
    RETURNING id INTO v_respuesta_id;

    IF v_respuesta_id IS NULL THEN
        RAISE EXCEPTION 'Esta respuesta ya fue evaluada y no se puede modificar'
          USING ERRCODE = 'P0001';
    END IF;

    RETURN v_respuesta_id;
END;
$func$;

-- Endurecer permisos: EXECUTE sobre SECURITY DEFINER nunca publico.
REVOKE ALL ON FUNCTION public.fn_registrar_respuesta(UUID, BIGINT, TEXT)
  FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_registrar_respuesta(UUID, BIGINT, TEXT)
  TO laravel_app;

COMMIT;
