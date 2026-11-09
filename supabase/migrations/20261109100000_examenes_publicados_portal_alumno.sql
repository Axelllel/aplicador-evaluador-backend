CREATE FUNCTION public.fn_examenes_portal_alumno(p_alumno_id bigint, p_grupo_id bigint)
RETURNS TABLE (
    examen_id bigint,
    nombre varchar,
    disponible boolean
)
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = ''
AS $fn$
    SELECT e.id, e.nombre, e.fecha_apertura <= now()
    FROM public.alumno_grupo ag
    JOIN public.grupo g ON g.id = ag.grupo_id
    JOIN public.examen e ON e.grupo_id = g.id
    WHERE ag.alumno_id = p_alumno_id
      AND g.id = p_grupo_id
      AND g.activo
      AND e.publicado
      AND e.plataforma_permitida IN ('web', 'ambas')
      AND e.fecha_cierre > now()
    ORDER BY e.fecha_apertura, e.id;
$fn$;

REVOKE ALL ON FUNCTION public.fn_examenes_portal_alumno(bigint, bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_examenes_portal_alumno(bigint, bigint) TO laravel_app;
