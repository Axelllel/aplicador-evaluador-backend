-- Compra de demostración: no procesa dinero ni almacena PAN/CVV.
BEGIN;

ALTER TABLE public.pago ADD COLUMN IF NOT EXISTS detalle_simulacion jsonb;

CREATE OR REPLACE FUNCTION public.fn_confirmar_pago_simulado(
    p_operacion uuid, p_grupos integer, p_alumnos integer, p_preguntas integer,
    p_metodo text, p_detalle jsonb
) RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $fn$
DECLARE
    v_docente uuid := public.app_docente_id();
    v_paquete bigint;
    v_pago public.pago%ROWTYPE;
    v_precio numeric(10,2);
    v_detalle jsonb;
    v_referencia text := 'SIM-' || p_operacion::text;
BEGIN
    IF v_docente IS NULL THEN
        RAISE EXCEPTION 'DOCENTE_NO_AUTENTICADO' USING ERRCODE = 'P1001';
    END IF;
    -- Mismo bloqueo que usan las altas de grupos y preguntas.
    PERFORM pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(v_docente::text, 0));
    PERFORM 1 FROM public.docente WHERE id = v_docente FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'DOCENTE_NO_DISPONIBLE' USING ERRCODE = 'P1001';
    END IF;
    IF p_operacion IS NULL OR p_grupos IS NULL OR p_alumnos IS NULL OR p_preguntas IS NULL
       OR p_grupos NOT BETWEEN 1 AND 10 OR p_alumnos NOT IN (15,20,25,30)
       OR p_preguntas NOT IN (100,150,200) OR p_metodo IS NULL OR p_metodo NOT IN ('tarjeta','spei') THEN
        RAISE EXCEPTION 'CAPACIDAD_O_METODO_INVALIDO' USING ERRCODE = 'P1002';
    END IF;
    IF jsonb_typeof(p_detalle) IS DISTINCT FROM 'object'
       OR length(btrim(coalesce(p_detalle->>'titular',''))) NOT BETWEEN 2 AND 150
       OR length(coalesce(p_detalle->>'correo_facturacion','')) NOT BETWEEN 3 AND 254
       OR coalesce(p_detalle->>'ultimos_cuatro','') !~ '^[0-9]{4}$'
       OR (p_metodo = 'spei' AND coalesce(p_detalle->>'banco','') NOT IN ('Banco de prueba A','Banco de prueba B')) THEN
        RAISE EXCEPTION 'DATOS_SIMULACION_INVALIDOS' USING ERRCODE = 'P1002';
    END IF;
    -- Lista permitida: nunca persistir datos adicionales enviados por un cliente.
    v_detalle := jsonb_build_object('grupos',p_grupos,'alumnos',p_alumnos,'preguntas',p_preguntas,
       'titular',p_detalle->>'titular','correo_facturacion',p_detalle->>'correo_facturacion',
       'ultimos_cuatro',p_detalle->>'ultimos_cuatro');
    IF p_metodo = 'spei' THEN
        v_detalle := v_detalle || jsonb_build_object('banco',p_detalle->>'banco');
    END IF;
    SELECT * INTO v_pago FROM public.pago
      WHERE proveedor_pago = 'simulador_bigbitbyte' AND referencia_externa = v_referencia;
    IF FOUND THEN
        IF v_pago.docente_id <> v_docente OR v_pago.metodo_pago <> p_metodo OR v_pago.detalle_simulacion IS DISTINCT FROM v_detalle THEN
            RAISE EXCEPTION 'OPERACION_REUTILIZADA' USING ERRCODE = 'P1003';
        END IF;
        RETURN jsonb_build_object('pago_id',v_pago.id,'paquete_id',v_pago.paquete_id,'referencia',v_referencia,
           'monto',v_pago.monto,'grupos',p_grupos,'alumnos',p_alumnos,'preguntas',p_preguntas,
           'vigente_hasta',v_pago.fecha_fin_vigencia,'simulado',true,'reutilizado',true);
    END IF;
    PERFORM 1 FROM public.grupo WHERE docente_id=v_docente ORDER BY id FOR UPDATE;
    IF (SELECT count(*) FROM public.grupo WHERE docente_id = v_docente AND activo) > p_grupos
       OR (SELECT count(*) FROM public.pregunta WHERE docente_id = v_docente) > p_preguntas
       OR EXISTS (SELECT 1 FROM public.alumno_grupo ag JOIN public.grupo g ON g.id=ag.grupo_id
           WHERE g.docente_id=v_docente AND g.activo GROUP BY g.id HAVING count(*) > p_alumnos) THEN
        RAISE EXCEPTION 'CAPACIDAD_MENOR_AL_USO_ACTUAL' USING ERRCODE = 'P1004';
    END IF;
    -- Precio autoritativo, misma fórmula en centavos que CotizacionPlanService.
    v_precio := (7900 + p_grupos * 2500 + p_grupos * p_alumnos * 20 + p_preguntas * 8)::numeric / 100;
    SELECT id INTO v_paquete FROM public.paquete
      WHERE nombre='Plan a medida (simulado)' AND max_grupos=p_grupos AND max_alumnos=p_alumnos
        AND max_preguntas=p_preguntas AND precio=v_precio ORDER BY id LIMIT 1;
    IF v_paquete IS NULL THEN
        INSERT INTO public.paquete(nombre,max_grupos,max_alumnos,max_preguntas,precio)
          VALUES ('Plan a medida (simulado)',p_grupos,p_alumnos,p_preguntas,v_precio) RETURNING id INTO v_paquete;
    END IF;
    INSERT INTO public.pago(docente_id,paquete_id,monto,estado,fecha_inicio_vigencia,fecha_fin_vigencia,
       renovacion_automatica,metodo_pago,proveedor_pago,referencia_externa,clave_rastreo_spei,detalle_simulacion)
      VALUES (v_docente,v_paquete,v_precio,'confirmado',now(),now()+interval '1 month',false,
       p_metodo,'simulador_bigbitbyte',v_referencia,CASE WHEN p_metodo='spei' THEN v_referencia END,v_detalle)
      RETURNING * INTO v_pago;
    UPDATE public.docente SET paquete_id=v_paquete,estado_pago='activo' WHERE id=v_docente;
    RETURN jsonb_build_object('pago_id',v_pago.id,'paquete_id',v_paquete,'referencia',v_referencia,
       'monto',v_precio,'grupos',p_grupos,'alumnos',p_alumnos,'preguntas',p_preguntas,
       'vigente_hasta',v_pago.fecha_fin_vigencia,'simulado',true,'reutilizado',false);
END;
$fn$;
REVOKE ALL ON FUNCTION public.fn_confirmar_pago_simulado(uuid,integer,integer,integer,text,jsonb) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.fn_confirmar_pago_simulado(uuid,integer,integer,integer,text,jsonb) TO laravel_app;
COMMIT;
