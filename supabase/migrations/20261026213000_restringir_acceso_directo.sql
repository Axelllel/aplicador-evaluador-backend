-- BIGBITBYTE - Seguridad del backend central Laravel
-- Migracion: impedir acceso directo desde roles cliente de Supabase
-- No modifica estructura, datos ni activa RLS.
-- Las aplicaciones web y movil deben utilizar exclusivamente la API de Laravel.

BEGIN;

-- Quitar permisos de acceso directo a las 17 tablas publicas.
-- PUBLIC evita que un permiso general vuelva a habilitar estas operaciones.
REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public
FROM PUBLIC, anon, authenticated;

-- Impedir el uso directo de secuencias por los clientes de Supabase.
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public
FROM PUBLIC, anon, authenticated;

-- Conservar los permisos explicitos del rol exclusivo del backend.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public
TO laravel_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public
TO laravel_app;

COMMIT;
