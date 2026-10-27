-- Rol de conexión para el backend Laravel.
-- Sin privilegios de administrador ni BYPASSRLS.

CREATE ROLE laravel_app
WITH LOGIN
NOSUPERUSER
NOCREATEDB
NOCREATEROLE
NOREPLICATION
NOBYPASSRLS;

GRANT CONNECT ON DATABASE postgres TO laravel_app;
GRANT USAGE ON SCHEMA public TO laravel_app;

GRANT SELECT, INSERT, UPDATE, DELETE
ON ALL TABLES IN SCHEMA public
TO laravel_app;

GRANT USAGE, SELECT
ON ALL SEQUENCES IN SCHEMA public
TO laravel_app;