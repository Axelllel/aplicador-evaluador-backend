-- BIGBITBYTE - SOLO DESARROLLO LOCAL: activa a un docente para poder probar el panel completo.
--
-- Hace tres cosas en una sola transaccion:
--   1. Asigna el paquete indicado al docente (docente.paquete_id).
--   2. Pone docente.estado_pago = 'activo'.
--   3. Crea un pago 'confirmado' vigente por :dias dias (si no existe ya uno vigente de ese paquete).
--
-- El estado de pago y los pagos solo los puede escribir un rol privilegiado, por eso se ejecuta como
-- usuario `postgres` dentro del contenedor de la base local de Supabase. Es idempotente.
-- NUNCA lo ejecutes contra una base de produccion.
--
-- Uso (desde la raiz del proyecto, en Ubuntu/WSL):
--
--   docker exec -i supabase_db_bigbitbyte-backend psql -U postgres -d postgres \
--       -v correo=docente.prueba@example.com -v paquete="Plan básico" -v dias=30 \
--       < scripts/dev-activar-docente.sql
--
-- Variables (psql):
--   correo   obligatoria. Correo con el que se registro el docente.
--   paquete  opcional. Nombre del paquete; por omision 'Plan básico'.
--   dias     opcional. Dias de vigencia del pago; por omision 30.

\set ON_ERROR_STOP on

\if :{?correo}
\else
    \echo 'Falta la variable correo. Ejemplo: -v correo=docente.prueba@example.com'
    \quit
\endif

\if :{?paquete}
\else
    \set paquete 'Plan básico'
\endif

\if :{?dias}
\else
    \set dias 30
\endif

SELECT id AS docente_id FROM public.docente WHERE correo = :'correo' \gset
\if :{?docente_id}
\else
    \echo 'No existe ningun docente con ese correo. Registralo primero con POST /api/v1/auth/register.'
    \quit
\endif

SELECT id AS paquete_id, precio AS paquete_precio FROM public.paquete WHERE nombre = :'paquete' ORDER BY id LIMIT 1 \gset
\if :{?paquete_id}
\else
    \echo 'No existe ese paquete. Aplica primero supabase/seed.sql.'
    \quit
\endif

BEGIN;

UPDATE public.docente
SET paquete_id = :paquete_id, estado_pago = 'activo'
WHERE id = :'docente_id';

INSERT INTO public.pago (docente_id, paquete_id, monto, estado, fecha_inicio_vigencia, fecha_fin_vigencia)
SELECT :'docente_id', :paquete_id, :paquete_precio, 'confirmado',
       CURRENT_TIMESTAMP - INTERVAL '1 minute',
       CURRENT_TIMESTAMP + (:dias || ' days')::interval
WHERE NOT EXISTS (
    SELECT 1
    FROM public.pago pg
    WHERE pg.docente_id = :'docente_id'
      AND pg.paquete_id = :paquete_id
      AND pg.estado = 'confirmado'
      AND pg.fecha_inicio_vigencia <= CURRENT_TIMESTAMP
      AND pg.fecha_fin_vigencia > CURRENT_TIMESTAMP
);

COMMIT;

SELECT d.correo, d.estado_pago, p.nombre AS paquete,
       (SELECT max(pg.fecha_fin_vigencia) FROM public.pago pg
         WHERE pg.docente_id = d.id AND pg.estado = 'confirmado') AS vigente_hasta
FROM public.docente d
JOIN public.paquete p ON p.id = d.paquete_id
WHERE d.id = :'docente_id';
