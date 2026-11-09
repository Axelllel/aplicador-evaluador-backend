-- Validacion publica de folio sin registrar alumnos ni exponer datos del grupo.
-- Ejecutar en Supabase staging antes de desplegar la interfaz.
CREATE OR REPLACE FUNCTION public.fn_validar_folio_grupo(p_folio text)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.grupo AS g
    WHERE g.folio_codigo = upper(btrim(p_folio))
      AND g.activo = true
  );
$$;
REVOKE ALL ON FUNCTION public.fn_validar_folio_grupo(text) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.fn_validar_folio_grupo(text) FROM anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_validar_folio_grupo(text) TO laravel_app;
