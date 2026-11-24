-- Sustituir TU_CORREO por el correo real del docente que administrará el sistema.
-- No ejecutar hasta haber aplicado completar-requisitos.sql.
BEGIN;
DO $$
DECLARE v_id uuid;
BEGIN
  SELECT id INTO STRICT v_id FROM public.docente WHERE lower(correo)=lower('TU_CORREO');
  INSERT INTO public.administrador_sistema(usuario_id,activo) VALUES(v_id,true)
  ON CONFLICT(usuario_id) DO UPDATE SET activo=true;
END $$;
COMMIT;
-- Cerrar sesión y volver a entrar para obtener el rol autorizado.
