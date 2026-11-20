-- IA asistida: registro privado de consumo, caché y propuestas; no publica notas.
BEGIN;
CREATE TABLE IF NOT EXISTS public.solicitud_ia (
    id uuid PRIMARY KEY,
    docente_id uuid NOT NULL REFERENCES public.docente(id) ON DELETE CASCADE,
    tipo text NOT NULL CHECK (tipo IN ('preguntas','calificacion')),
    huella text NOT NULL,
    modelo text NOT NULL,
    respuesta_id bigint REFERENCES public.respuesta_alumno(id) ON DELETE SET NULL,
    estado text NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','completada','error')),
    intentos integer NOT NULL DEFAULT 1,
    reserva_tokens integer NOT NULL,
    presupuesto_consumido bigint NOT NULL,
    tokens_consumidos integer,
    resultado jsonb,
    error_codigo text,
    creada_en timestamptz NOT NULL DEFAULT now(),
    actualizada_en timestamptz NOT NULL DEFAULT now(),
    fecha_consumo timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS solicitud_ia_cuota ON public.solicitud_ia(fecha_consumo,docente_id);
ALTER TABLE public.solicitud_ia ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.solicitud_ia FROM PUBLIC,anon,authenticated,laravel_app;
GRANT SELECT ON public.solicitud_ia TO laravel_app;
DROP POLICY IF EXISTS solicitud_ia_propietario ON public.solicitud_ia;
CREATE POLICY solicitud_ia_propietario ON public.solicitud_ia FOR SELECT TO laravel_app
    USING (docente_id=(SELECT public.app_docente_id()));

CREATE OR REPLACE FUNCTION public.fn_ia_reservar(
    p_id uuid,p_tipo text,p_huella text,p_modelo text,p_respuesta bigint,p_reserva integer,
    p_lim_docente integer,p_lim_global integer,p_lim_tokens integer,p_lim_minuto integer
) RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=''
AS $fn$
DECLARE
    v_docente uuid := public.app_docente_id();
    v_fila public.solicitud_ia%ROWTYPE;
    v_plan bigint;
BEGIN
    IF v_docente IS NULL OR NOT EXISTS(SELECT 1 FROM public.docente WHERE id=v_docente) THEN
        RAISE EXCEPTION 'IA_IDENTIDAD' USING ERRCODE='P2001';
    END IF;
    IF p_id IS NULL OR p_tipo IS NULL OR p_tipo NOT IN ('preguntas','calificacion')
       OR p_huella IS NULL OR p_huella !~ '^[a-f0-9]{64}$'
       OR p_modelo IS NULL OR length(p_modelo) NOT BETWEEN 1 AND 120
       OR p_reserva IS NULL OR p_reserva NOT BETWEEN 1 AND 50000
       OR p_lim_docente IS NULL OR p_lim_docente<1 OR p_lim_global IS NULL OR p_lim_global<1
       OR p_lim_tokens IS NULL OR p_lim_tokens<1 OR p_lim_minuto IS NULL OR p_lim_minuto<1 THEN
        RAISE EXCEPTION 'IA_DATOS' USING ERRCODE='P2002';
    END IF;
    SELECT d.paquete_id INTO v_plan FROM public.docente d
      WHERE d.id=v_docente AND d.estado_pago='activo'
        AND EXISTS(SELECT 1 FROM public.pago pg WHERE pg.docente_id=d.id AND pg.paquete_id=d.paquete_id
          AND pg.estado='confirmado' AND pg.fecha_inicio_vigencia<=now() AND pg.fecha_fin_vigencia>now());
    IF v_plan IS NULL THEN RAISE EXCEPTION 'IA_PLAN' USING ERRCODE='P2001'; END IF;
    IF p_tipo='calificacion' THEN
        IF p_respuesta IS NULL OR NOT EXISTS(
            SELECT 1 FROM public.respuesta_alumno r JOIN public.intento_examen i ON i.id=r.intento_id
            JOIN public.examen e ON e.id=i.examen_id JOIN public.grupo g ON g.id=e.grupo_id
            JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
            JOIN public.apartado a ON a.id=ep.apartado_id AND a.examen_id=e.id
            JOIN public.pregunta p ON p.id=ep.pregunta_id
            WHERE r.id=p_respuesta AND g.docente_id=v_docente AND i.estado='finalizado'
              AND p.tipo IN ('abierta','programacion') AND r.calificacion_final IS NULL
              AND btrim(coalesce(r.respuesta_texto,''))<>'' AND btrim(coalesce(ep.respuesta_modelo_snapshot,''))<>''
        ) THEN RAISE EXCEPTION 'IA_RESPUESTA_NO_DISPONIBLE' USING ERRCODE='P2003'; END IF;
    ELSIF p_respuesta IS NOT NULL THEN
        RAISE EXCEPTION 'IA_DATOS' USING ERRCODE='P2002';
    END IF;
    -- Operación corta: el candado se libera ANTES de llamar al proveedor.
    PERFORM pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended('bbb-ai-cuota-'||current_date::text,0));
    SELECT * INTO v_fila FROM public.solicitud_ia WHERE id=p_id FOR UPDATE;
    IF FOUND THEN
        IF v_fila.docente_id<>v_docente OR v_fila.tipo<>p_tipo OR v_fila.huella<>p_huella
           OR v_fila.modelo<>p_modelo OR v_fila.respuesta_id IS DISTINCT FROM p_respuesta THEN
            RAISE EXCEPTION 'IA_OPERACION_REUTILIZADA' USING ERRCODE='P2004';
        END IF;
        IF v_fila.estado='completada' THEN
            RETURN jsonb_build_object('cache',true,'resultado',v_fila.resultado,'tokens',v_fila.tokens_consumidos);
        END IF;
        IF v_fila.estado='pendiente' AND v_fila.actualizada_en>now()-interval '90 seconds' THEN
            RAISE EXCEPTION 'IA_EN_PROCESO' USING ERRCODE='P2004';
        END IF;
        IF v_fila.intentos>=3 THEN RAISE EXCEPTION 'IA_MAX_REINTENTOS' USING ERRCODE='P2005'; END IF;
    END IF;
    IF coalesce((SELECT sum(intentos) FROM public.solicitud_ia WHERE docente_id=v_docente AND fecha_consumo>=current_date),0)>=p_lim_docente
       OR coalesce((SELECT sum(intentos) FROM public.solicitud_ia WHERE fecha_consumo>=current_date),0)>=p_lim_global
       OR coalesce((SELECT sum(presupuesto_consumido) FROM public.solicitud_ia WHERE fecha_consumo>=current_date),0)+p_reserva>p_lim_tokens
       OR coalesce((SELECT sum(intentos) FROM public.solicitud_ia WHERE fecha_consumo>now()-interval '1 minute'),0)>=p_lim_minuto THEN
        RAISE EXCEPTION 'IA_CUOTA' USING ERRCODE='P2005';
    END IF;
    IF p_tipo='preguntas' AND (SELECT count(*) FROM public.pregunta WHERE docente_id=v_docente)>=
       (SELECT max_preguntas FROM public.paquete WHERE id=v_plan) THEN
        RAISE EXCEPTION 'IA_BANCO_LLENO' USING ERRCODE='P2007';
    END IF;
    INSERT INTO public.solicitud_ia(id,docente_id,tipo,huella,modelo,respuesta_id,reserva_tokens,presupuesto_consumido)
      VALUES(p_id,v_docente,p_tipo,p_huella,p_modelo,p_respuesta,p_reserva,p_reserva)
      ON CONFLICT(id) DO UPDATE SET estado='pendiente',intentos=solicitud_ia.intentos+1,
        presupuesto_consumido=solicitud_ia.presupuesto_consumido+p_reserva,reserva_tokens=p_reserva,
        actualizada_en=now(),fecha_consumo=now(),error_codigo=NULL
      RETURNING * INTO v_fila;
    RETURN jsonb_build_object('cache',false,'intento',v_fila.intentos);
END;
$fn$;

CREATE OR REPLACE FUNCTION public.fn_ia_completar(p_id uuid,p_intento integer,p_resultado jsonb,p_tokens integer,p_contexto jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=''
AS $fn$
DECLARE
    v_docente uuid := public.app_docente_id();
    v_fila public.solicitud_ia%ROWTYPE;
    v_contexto jsonb;
    v_nota numeric;
BEGIN
    SELECT * INTO v_fila FROM public.solicitud_ia WHERE id=p_id AND docente_id=v_docente FOR UPDATE;
    IF NOT FOUND OR v_fila.estado<>'pendiente' OR v_fila.intentos<>p_intento THEN
        RAISE EXCEPTION 'IA_OPERACION_NO_VIGENTE' USING ERRCODE='P2004';
    END IF;
    IF jsonb_typeof(p_resultado) IS DISTINCT FROM 'object' OR
       (p_tokens IS NOT NULL AND (p_tokens<0 OR p_tokens>1000000)) THEN
        RAISE EXCEPTION 'IA_DATOS' USING ERRCODE='P2002';
    END IF;
    IF v_fila.tipo='calificacion' THEN
        SELECT jsonb_build_object('tipo',p.tipo,'enunciado',p.enunciado,'respuesta_modelo',ep.respuesta_modelo_snapshot,'respuesta_alumno',r.respuesta_texto)
          INTO v_contexto FROM public.respuesta_alumno r
          JOIN public.intento_examen i ON i.id=r.intento_id JOIN public.examen e ON e.id=i.examen_id
          JOIN public.grupo g ON g.id=e.grupo_id JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
          JOIN public.apartado a ON a.id=ep.apartado_id AND a.examen_id=e.id JOIN public.pregunta p ON p.id=ep.pregunta_id
          WHERE r.id=v_fila.respuesta_id AND g.docente_id=v_docente AND i.estado='finalizado'
            AND p.tipo IN ('abierta','programacion') AND r.calificacion_final IS NULL FOR UPDATE OF r;
        IF NOT FOUND OR v_contexto IS DISTINCT FROM p_contexto THEN
            RAISE EXCEPTION 'IA_RESPUESTA_CAMBIO' USING ERRCODE='P2006';
        END IF;
        IF NOT(p_resultado ? 'puntaje') OR jsonb_typeof(p_resultado->'puntaje') NOT IN ('number','null')
           OR jsonb_typeof(p_resultado->'justificacion') IS DISTINCT FROM 'string'
           OR jsonb_typeof(p_resultado->'requiere_revision') IS DISTINCT FROM 'boolean'
           OR jsonb_typeof(p_resultado->'observaciones') IS DISTINCT FROM 'array' THEN
            RAISE EXCEPTION 'IA_DATOS' USING ERRCODE='P2002';
        END IF;
        v_nota := (p_resultado->>'puntaje')::numeric;
        IF v_nota<0 OR v_nota>100 OR (v_nota IS NULL AND NOT(p_resultado->>'requiere_revision')::boolean) THEN
            RAISE EXCEPTION 'IA_DATOS' USING ERRCODE='P2002';
        END IF;
        UPDATE public.respuesta_alumno SET puntaje_ia=v_nota,
            retroalimentacion_ia=p_resultado||jsonb_build_object('proveedor','groq','modelo',v_fila.modelo,'solicitud_id',v_fila.id,'generada_en',now(),
                'huella_contenido',md5(jsonb_build_array(p_contexto->>'tipo',p_contexto->>'enunciado',p_contexto->>'respuesta_modelo',p_contexto->>'respuesta_alumno')::text)),
            tokens_consumidos=p_tokens,score_confianza=NULL WHERE id=v_fila.respuesta_id;
        -- calificacion_final y revisada_manual solo cambian por revisión docente.
    END IF;
    UPDATE public.solicitud_ia SET estado='completada',resultado=p_resultado,tokens_consumidos=p_tokens,
        presupuesto_consumido=presupuesto_consumido-reserva_tokens+coalesce(p_tokens,reserva_tokens),actualizada_en=now()
        WHERE id=p_id;
    RETURN p_resultado;
END;
$fn$;

CREATE OR REPLACE FUNCTION public.fn_ia_fallar(p_id uuid,p_intento integer,p_error text)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path=''
AS $fn$
BEGIN
    UPDATE public.solicitud_ia SET estado='error',error_codigo=left(coalesce(p_error,'error'),40),actualizada_en=now()
      WHERE id=p_id AND docente_id=public.app_docente_id() AND estado='pendiente' AND intentos=p_intento;
END;
$fn$;
REVOKE ALL ON FUNCTION public.fn_ia_reservar(uuid,text,text,text,bigint,integer,integer,integer,integer,integer) FROM PUBLIC,anon,authenticated;
REVOKE ALL ON FUNCTION public.fn_ia_completar(uuid,integer,jsonb,integer,jsonb) FROM PUBLIC,anon,authenticated;
REVOKE ALL ON FUNCTION public.fn_ia_fallar(uuid,integer,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_ia_reservar(uuid,text,text,text,bigint,integer,integer,integer,integer,integer) TO laravel_app;
GRANT EXECUTE ON FUNCTION public.fn_ia_completar(uuid,integer,jsonb,integer,jsonb) TO laravel_app;
GRANT EXECUTE ON FUNCTION public.fn_ia_fallar(uuid,integer,text) TO laravel_app;
COMMIT;
