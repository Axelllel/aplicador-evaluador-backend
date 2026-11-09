BEGIN;

ALTER TABLE public.pago
    ADD COLUMN metodo_pago text,
    ADD COLUMN proveedor_pago text,
    ADD COLUMN referencia_externa text,
    ADD COLUMN clave_rastreo_spei text,
    ADD CONSTRAINT pago_metodo_valido
        CHECK (metodo_pago IN ('tarjeta', 'spei')),
    ADD CONSTRAINT pago_proveedor_no_vacio
        CHECK (btrim(proveedor_pago) <> ''),
    ADD CONSTRAINT pago_referencia_con_proveedor
        CHECK (referencia_externa IS NULL OR (
            proveedor_pago IS NOT NULL AND btrim(referencia_externa) <> ''
        )),
    ADD CONSTRAINT pago_clave_rastreo_solo_spei
        CHECK (clave_rastreo_spei IS NULL OR (
            metodo_pago IS NOT DISTINCT FROM 'spei' AND btrim(clave_rastreo_spei) <> ''
        ));

-- Cada proveedor identifica una operación con su propia referencia.
CREATE UNIQUE INDEX pago_operacion_externa_unica
    ON public.pago (proveedor_pago, referencia_externa)
    WHERE referencia_externa IS NOT NULL;

COMMIT;
