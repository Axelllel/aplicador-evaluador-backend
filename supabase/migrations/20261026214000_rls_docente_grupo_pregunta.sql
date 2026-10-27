-- ================================================================
-- BigBitByte - Manual de Base de Datos v5.3
-- RLS fase 1: docente, grupo y pregunta.
-- Requiere migraciones previas: estructura, seguridad_roles,
-- fn_registrar_respuesta y restringir_acceso_directo.
-- Mantiene las 17 tablas y no modifica los registros existentes.
-- Las restantes 14 tablas requieren sus propias politicas.
-- ================================================================
BEGIN;

-- ID del docente validado por Supabase Auth e instalado exclusivamente
-- por Laravel dentro de su transaccion de base de datos.
-- No usar auth.uid(): laravel_app entra mediante conexion PostgreSQL.
CREATE OR REPLACE FUNCTION public.app_docente_id()
RETURNS uuid
LANGUAGE sql
STABLE
SET search_path = ''
AS $func$
    SELECT NULLIF(current_setting('app.docente_id', true), '')::uuid;
$func$;

REVOKE ALL ON FUNCTION public.app_docente_id() FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.app_docente_id() TO laravel_app;

-- No dejar al docente modificar su paquete o estado de pago.
REVOKE INSERT, UPDATE, DELETE ON public.docente FROM laravel_app;
GRANT INSERT (id, nombre, correo, telefono) ON public.docente TO laravel_app;
GRANT UPDATE (nombre, telefono) ON public.docente TO laravel_app;

ALTER TABLE public.docente ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.grupo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pregunta ENABLE ROW LEVEL SECURITY;

-- Cada docente puede consultar su ficha, crearla cuando exista un
-- registro/alta validado y actualizar solo las columnas permitidas.
CREATE POLICY bbb_docente_select ON public.docente
FOR SELECT TO laravel_app
USING (id = (SELECT public.app_docente_id()));

CREATE POLICY bbb_docente_insert ON public.docente
FOR INSERT TO laravel_app
WITH CHECK (id = (SELECT public.app_docente_id()));

CREATE POLICY bbb_docente_update ON public.docente
FOR UPDATE TO laravel_app
USING (id = (SELECT public.app_docente_id()))
WITH CHECK (id = (SELECT public.app_docente_id()));

-- Los grupos solo pueden pertenecer al docente autenticado.
CREATE POLICY bbb_grupo_docente ON public.grupo
FOR ALL TO laravel_app
USING (docente_id = (SELECT public.app_docente_id()))
WITH CHECK (docente_id = (SELECT public.app_docente_id()));

-- El banco de preguntas esta aislado por su docente propietario.
CREATE POLICY bbb_pregunta_docente ON public.pregunta
FOR ALL TO laravel_app
USING (docente_id = (SELECT public.app_docente_id()))
WITH CHECK (docente_id = (SELECT public.app_docente_id()));

COMMIT;
