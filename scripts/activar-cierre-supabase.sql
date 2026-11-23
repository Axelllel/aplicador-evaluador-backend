-- Opcional para un Render gratuito que se suspende: cierre independiente en PostgreSQL.
-- Ejecutar DESPUÉS de completar-requisitos.sql, como administrador de BD.
-- Requiere habilitar pg_cron en el proyecto Supabase si aún no está disponible.
CREATE EXTENSION IF NOT EXISTS pg_cron;
SELECT cron.schedule(
  'bigbitbyte-cerrar-intentos',
  '* * * * *',
  'SELECT public.fn_cerrar_intentos_vencidos(100);'
);
SELECT jobid, jobname, schedule, active FROM cron.job WHERE jobname='bigbitbyte-cerrar-intentos';
-- La recepción de respuestas verifica el vencimiento exacto. La tarea materializa
-- el cierre y la cola de calificación al siguiente minuto, incluso sin visitas web.
