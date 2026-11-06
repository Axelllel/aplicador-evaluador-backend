-- BIGBITBYTE - Datos de ejemplo para desarrollo local
-- Contiene SOLO los tres paquetes (planes) de ejemplo. Los valores son ficticios.
--
-- Es idempotente: se puede ejecutar varias veces sin duplicar paquetes
-- (se identifican por nombre). `supabase db reset` lo ejecuta de forma automatica
-- (ver [db.seed] en config.toml). Para aplicarlo SIN reiniciar la base:
--
--   docker exec -i supabase_db_bigbitbyte-backend psql -U postgres -d postgres < supabase/seed.sql

INSERT INTO public.paquete (nombre, max_grupos, max_preguntas, max_alumnos, precio)
SELECT v.nombre, v.max_grupos, v.max_preguntas, v.max_alumnos, v.precio
FROM (
    VALUES
        ('Plan básico', 5, 200, 150, 199.00),
        ('Plan profesional', 15, 600, 450, 499.00),
        ('Plan institucional', 40, 2000, 1500, 1299.00)
) AS v (nombre, max_grupos, max_preguntas, max_alumnos, precio)
WHERE NOT EXISTS (
    SELECT 1 FROM public.paquete p WHERE p.nombre = v.nombre
);
