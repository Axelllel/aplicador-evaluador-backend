BEGIN;

CREATE OR REPLACE FUNCTION public.fn_examenes_portal_alumno_detalles(p_alumno_id bigint, p_grupo_id bigint)
RETURNS TABLE (
    examen_id bigint,
    nombre varchar,
    disponible boolean,
    fecha_apertura timestamptz,
    fecha_cierre timestamptz,
    total_preguntas bigint,
    duracion_segundos bigint
)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
    SELECT listado.examen_id, listado.nombre, listado.disponible,
           e.fecha_apertura, e.fecha_cierre, datos.total, datos.duracion
    FROM public.fn_examenes_portal_alumno(p_alumno_id, p_grupo_id) listado
    JOIN public.examen e ON e.id = listado.examen_id
    CROSS JOIN LATERAL (
        SELECT count(*) AS total, coalesce(sum(greatest(ep.tiempo_segundos, 0)), 0) AS duracion
        FROM public.apartado a
        JOIN public.examen_pregunta ep ON ep.apartado_id = a.id
        WHERE a.examen_id = e.id
    ) datos
    ORDER BY e.fecha_apertura, e.id;
$fn$;

REVOKE ALL ON FUNCTION public.fn_examenes_portal_alumno_detalles(bigint, bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_examenes_portal_alumno_detalles(bigint, bigint) TO laravel_app;

COMMIT;
