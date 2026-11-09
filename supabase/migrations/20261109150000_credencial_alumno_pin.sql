-- BigBitByte | Identidad persistente del alumno mediante PIN.
-- La tabla alumno conserva solo nombre; el telefono no se captura en el portal.
-- Aplicar en Supabase staging despues de respaldar y verificar el pre-chequeo.
BEGIN;

-- No descartar silenciosamente datos antiguos. La migracion falla y revierte
-- todo si telefono contiene algun dato real para que pueda revisarse primero.
DO $check$
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.alumno
        WHERE telefono IS NOT NULL AND pg_catalog.btrim(telefono) <> ''
    ) THEN
        RAISE EXCEPTION 'La migracion PIN fue detenida: alumno.telefono contiene datos. Revise/exporte esos valores y vuelva a aplicar.';
    END IF;
END;
$check$;

-- Retirar la antigua ruta que creaba alumnos sin una credencial persistente.
DROP FUNCTION IF EXISTS public.fn_unirse_grupo(text, text, text);
ALTER TABLE public.alumno DROP COLUMN IF EXISTS telefono;

CREATE TABLE public.credencial_alumno (
    alumno_id bigint PRIMARY KEY REFERENCES public.alumno(id) ON DELETE CASCADE,
    codigo_hash text NOT NULL UNIQUE CHECK (codigo_hash ~ '^[0-9a-f]{64}$'),
    creado_en timestamptz NOT NULL DEFAULT pg_catalog.now(),
    actualizado_en timestamptz NOT NULL DEFAULT pg_catalog.now(),
    revocado_en timestamptz
);

ALTER TABLE public.credencial_alumno ENABLE ROW LEVEL SECURITY;
REVOKE ALL PRIVILEGES ON public.credencial_alumno FROM PUBLIC, anon, authenticated, laravel_app;

-- Alta inicial: crea al alumno, lo vincula al grupo y registra solo el HMAC.
CREATE OR REPLACE FUNCTION public.fn_crear_alumno_con_pin(
    p_folio text,
    p_nombre text,
    p_codigo_hash text
)
RETURNS TABLE (alumno_id bigint, grupo_id bigint, materia varchar, nombre varchar)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_grupo_id bigint;
    v_materia varchar(150);
    v_max_alumnos integer;
    v_alumno_id bigint;
    v_nombre text := pg_catalog.btrim(p_nombre);
    v_folio text := pg_catalog.upper(pg_catalog.btrim(p_folio));
BEGIN
    IF v_folio IS NULL OR v_folio !~ '^[A-Z0-9]{4,20}$'
       OR v_nombre IS NULL OR pg_catalog.char_length(v_nombre) NOT BETWEEN 2 AND 150
       OR p_codigo_hash IS NULL OR p_codigo_hash !~ '^[0-9a-f]{64}$' THEN
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
            WHERE pa.docente_id = d.id AND pa.paquete_id = d.paquete_id
              AND pa.estado = 'confirmado'
              AND pa.fecha_inicio_vigencia <= pg_catalog.now()
              AND pa.fecha_fin_vigencia > pg_catalog.now()
       )
     FOR UPDATE OF g;

    IF v_grupo_id IS NULL THEN
        RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    IF v_max_alumnos <= 0 OR v_max_alumnos <= (
        SELECT pg_catalog.count(*) FROM public.alumno_grupo ag WHERE ag.grupo_id = v_grupo_id
    ) THEN
        RAISE EXCEPTION 'LIMITE_ALUMNOS' USING ERRCODE = 'P1001';
    END IF;

    INSERT INTO public.alumno(nombre) VALUES (v_nombre) RETURNING id INTO v_alumno_id;
    INSERT INTO public.credencial_alumno(alumno_id, codigo_hash)
    VALUES (v_alumno_id, p_codigo_hash);
    INSERT INTO public.alumno_grupo(alumno_id, grupo_id) VALUES (v_alumno_id, v_grupo_id);

    RETURN QUERY SELECT v_alumno_id, v_grupo_id, v_materia, v_nombre::varchar(150);
END;
$fn$;

-- Reingreso o alta en otro grupo: el PIN resuelve el alumno existente.
CREATE OR REPLACE FUNCTION public.fn_ingresar_alumno_con_pin(
    p_folio text,
    p_codigo_hash text
)
RETURNS TABLE (alumno_id bigint, grupo_id bigint, materia varchar, nombre varchar)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_grupo_id bigint;
    v_materia varchar(150);
    v_max_alumnos integer;
    v_alumno_id bigint;
    v_nombre varchar(150);
    v_folio text := pg_catalog.upper(pg_catalog.btrim(p_folio));
BEGIN
    IF v_folio IS NULL OR v_folio !~ '^[A-Z0-9]{4,20}$'
       OR p_codigo_hash IS NULL OR p_codigo_hash !~ '^[0-9a-f]{64}$' THEN
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
            WHERE pa.docente_id = d.id AND pa.paquete_id = d.paquete_id
              AND pa.estado = 'confirmado'
              AND pa.fecha_inicio_vigencia <= pg_catalog.now()
              AND pa.fecha_fin_vigencia > pg_catalog.now()
       )
     FOR UPDATE OF g;

    IF v_grupo_id IS NULL THEN
        RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    SELECT c.alumno_id, a.nombre INTO v_alumno_id, v_nombre
      FROM public.credencial_alumno c
      JOIN public.alumno a ON a.id = c.alumno_id
     WHERE c.codigo_hash = p_codigo_hash AND c.revocado_en IS NULL
     FOR UPDATE OF c;

    -- Mismo error para PIN inexistente/revocado: no revelar registros.
    IF v_alumno_id IS NULL THEN
        RAISE EXCEPTION 'CREDENCIAL_NO_VALIDA' USING ERRCODE = 'P1004';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM public.alumno_grupo ag
         WHERE ag.alumno_id = v_alumno_id AND ag.grupo_id = v_grupo_id
    ) THEN
        IF v_max_alumnos <= 0 OR v_max_alumnos <= (
            SELECT pg_catalog.count(*) FROM public.alumno_grupo ag WHERE ag.grupo_id = v_grupo_id
        ) THEN
            RAISE EXCEPTION 'LIMITE_ALUMNOS' USING ERRCODE = 'P1001';
        END IF;
        INSERT INTO public.alumno_grupo(alumno_id, grupo_id) VALUES (v_alumno_id, v_grupo_id);
    END IF;

    RETURN QUERY SELECT v_alumno_id, v_grupo_id, v_materia, v_nombre;
END;
$fn$;

-- Lista controlada para que el docente pueda asignar PIN a alumnos ya inscritos
-- sin que el sistema intente unir registros por nombre.
CREATE OR REPLACE FUNCTION public.fn_listar_alumnos_credencial(p_grupo_id bigint)
RETURNS TABLE (
    id bigint,
    nombre varchar,
    fecha_union timestamptz,
    tiene_codigo boolean,
    intentos_en_grupo bigint
)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_docente uuid := public.app_docente_id();
BEGIN
    IF v_docente IS NULL OR NOT EXISTS (
        SELECT 1 FROM public.grupo g WHERE g.id = p_grupo_id AND g.docente_id = v_docente
    ) THEN
        RAISE EXCEPTION 'GRUPO_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    RETURN QUERY
    SELECT a.id, a.nombre, ag.fecha_union,
           (c.alumno_id IS NOT NULL AND c.revocado_en IS NULL),
           pg_catalog.count(DISTINCT i.id)
      FROM public.alumno_grupo ag
      JOIN public.alumno a ON a.id = ag.alumno_id
      LEFT JOIN public.credencial_alumno c ON c.alumno_id = a.id
      LEFT JOIN public.examen e ON e.grupo_id = p_grupo_id
      LEFT JOIN public.intento_examen i ON i.examen_id = e.id AND i.alumno_id = a.id
     WHERE ag.grupo_id = p_grupo_id
     GROUP BY a.id, a.nombre, ag.fecha_union, c.alumno_id, c.revocado_en
     ORDER BY a.nombre, a.id;
END;
$fn$;

-- El docente puede emitir o renovar el PIN solo para un alumno de su grupo.
CREATE OR REPLACE FUNCTION public.fn_asignar_credencial_alumno(
    p_grupo_id bigint,
    p_alumno_id bigint,
    p_codigo_hash text
)
RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_docente uuid := public.app_docente_id();
BEGIN
    IF v_docente IS NULL OR p_codigo_hash IS NULL OR p_codigo_hash !~ '^[0-9a-f]{64}$'
       OR NOT EXISTS (
           SELECT 1 FROM public.alumno_grupo ag
           JOIN public.grupo g ON g.id = ag.grupo_id
           WHERE ag.grupo_id = p_grupo_id AND ag.alumno_id = p_alumno_id
             AND g.docente_id = v_docente
       ) THEN
        RAISE EXCEPTION 'ALUMNO_NO_DISPONIBLE' USING ERRCODE = 'P1005';
    END IF;

    INSERT INTO public.credencial_alumno(alumno_id, codigo_hash)
    VALUES (p_alumno_id, p_codigo_hash)
    ON CONFLICT (alumno_id) DO UPDATE
       SET codigo_hash = EXCLUDED.codigo_hash,
           actualizado_en = pg_catalog.now(),
           revocado_en = NULL;

    RETURN true;
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_crear_alumno_con_pin(text, text, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_crear_alumno_con_pin(text, text, text) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_ingresar_alumno_con_pin(text, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_ingresar_alumno_con_pin(text, text) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_listar_alumnos_credencial(bigint) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_listar_alumnos_credencial(bigint) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_asignar_credencial_alumno(bigint, bigint, text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_asignar_credencial_alumno(bigint, bigint, text) TO laravel_app;

COMMIT;
