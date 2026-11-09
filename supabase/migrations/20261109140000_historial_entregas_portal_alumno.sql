BEGIN;

-- El backend obtiene p_alumno_id de la sesión. El token nunca se envía al navegador.
CREATE OR REPLACE FUNCTION public.fn_entregas_portal_alumno(p_alumno_id bigint, p_intento_id bigint)
RETURNS TABLE (
    intento_id bigint,
    examen_id bigint,
    grupo_id bigint,
    nombre varchar,
    materia varchar,
    resultado_visible varchar,
    fecha_inicio timestamptz,
    fecha_entrega timestamptz,
    token_sesion uuid
)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
    SELECT i.id, e.id, g.id, e.nombre, g.materia, e.resultado_visible, i.fecha_inicio, i.fecha_entrega, i.token_sesion
    FROM public.intento_examen i
    JOIN public.examen e ON e.id = i.examen_id
    JOIN public.grupo g ON g.id = e.grupo_id
    WHERE i.alumno_id = p_alumno_id
      AND i.estado = 'finalizado'
      AND (p_intento_id IS NULL OR i.id = p_intento_id)
    ORDER BY i.fecha_entrega DESC NULLS LAST, i.id DESC;
$fn$;

REVOKE ALL ON FUNCTION public.fn_entregas_portal_alumno(bigint, bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_entregas_portal_alumno(bigint, bigint) TO laravel_app;

COMMIT;
