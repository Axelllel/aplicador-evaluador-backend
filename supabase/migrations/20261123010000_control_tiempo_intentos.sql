-- BigBitByte | PR-BD-TI-01. Aplicar completo; no edita migraciones anteriores.
BEGIN;

ALTER TABLE public.intento_examen ADD COLUMN IF NOT EXISTS duracion_segundos bigint;
ALTER TABLE public.intento_examen ADD COLUMN IF NOT EXISTS control_por_pregunta boolean NOT NULL DEFAULT false;
CREATE TABLE IF NOT EXISTS public.tiempo_pregunta_intento (
  intento_id bigint NOT NULL REFERENCES public.intento_examen(id) ON DELETE CASCADE,
  examen_pregunta_id bigint NOT NULL REFERENCES public.examen_pregunta(id),
  primer_acceso timestamptz NOT NULL,
  fecha_limite timestamptz NOT NULL,
  PRIMARY KEY (intento_id, examen_pregunta_id),
  CHECK (fecha_limite >= primer_acceso)
);
CREATE TABLE IF NOT EXISTS public.cierre_intento (
  intento_id bigint PRIMARY KEY REFERENCES public.intento_examen(id),
  motivo text NOT NULL CHECK (motivo IN ('entrega_alumno','tiempo_agotado','cierre_examen')),
  fecha_limite timestamptz NOT NULL,
  registrado_en timestamptz NOT NULL DEFAULT clock_timestamp()
);
ALTER TABLE public.tiempo_pregunta_intento ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.cierre_intento ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.tiempo_pregunta_intento, public.cierre_intento FROM PUBLIC, anon, authenticated, laravel_app;

-- Congelar T al crear el intento. Publicar ya valida tiempos positivos.
CREATE OR REPLACE FUNCTION public.fn_fijar_duracion_intento() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $fn$
DECLARE v_total bigint;
BEGIN
  SELECT sum(ep.tiempo_segundos) INTO v_total FROM public.examen_pregunta ep
  JOIN public.apartado a ON a.id=ep.apartado_id WHERE a.examen_id=NEW.examen_id;
  IF v_total IS NULL OR v_total <= 0 OR EXISTS (
    SELECT 1 FROM public.examen_pregunta ep JOIN public.apartado a ON a.id=ep.apartado_id
    WHERE a.examen_id=NEW.examen_id AND (ep.tiempo_segundos IS NULL OR ep.tiempo_segundos<=0)
  ) THEN RAISE EXCEPTION 'El examen no tiene tiempos válidos' USING ERRCODE='P0001'; END IF;
  NEW.duracion_segundos := v_total;
  RETURN NEW;
END; $fn$;
DROP TRIGGER IF EXISTS fijar_duracion_intento ON public.intento_examen;
CREATE TRIGGER fijar_duracion_intento BEFORE INSERT ON public.intento_examen
FOR EACH ROW EXECUTE FUNCTION public.fn_fijar_duracion_intento();
UPDATE public.intento_examen i SET duracion_segundos=x.total FROM (
 SELECT a.examen_id,sum(ep.tiempo_segundos) AS total FROM public.apartado a
 JOIN public.examen_pregunta ep ON ep.apartado_id=a.id GROUP BY a.examen_id
) x WHERE i.examen_id=x.examen_id AND i.duracion_segundos IS NULL;

CREATE OR REPLACE FUNCTION public.fn_antes_del_limite(p_recepcion timestamptz,p_limite timestamptz)
RETURNS boolean LANGUAGE sql IMMUTABLE SET search_path='' AS $fn$
 SELECT coalesce(p_recepcion<p_limite,false)
$fn$;
REVOKE ALL ON FUNCTION public.fn_antes_del_limite(timestamptz,timestamptz) FROM PUBLIC,anon,authenticated,laravel_app;

-- Devuelve estado en vez de lanzar excepción tras cerrar: así el cierre no se revierte.
CREATE OR REPLACE FUNCTION public.fn_tiempo_intento(p_token uuid, p_pregunta bigint DEFAULT NULL)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $fn$
DECLARE v record; v_ahora timestamptz; v_limite timestamptz; v_pregunta_limite timestamptz;
        v_segundos integer; v_motivo text; v_acceso timestamptz; v_pregunta jsonb;
BEGIN
  SELECT i.*,e.fecha_cierre,e.fecha_apertura,e.publicado,g.activo,
    EXISTS(SELECT 1 FROM public.alumno_grupo ag WHERE ag.alumno_id=i.alumno_id AND ag.grupo_id=g.id) AS inscrito
  INTO v FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
  JOIN public.grupo g ON g.id=e.grupo_id WHERE i.token_sesion=p_token FOR UPDATE OF i;
  v_ahora := clock_timestamp();
  IF v.id IS NULL OR v.estado='anulado' OR NOT v.inscrito OR NOT v.activo OR NOT v.publicado
     OR v_ahora<v.fecha_apertura THEN
    RAISE EXCEPTION 'Sesion no disponible' USING ERRCODE='P0001';
  END IF;
  v_limite := least(v.fecha_cierre,v.fecha_inicio+v.duracion_segundos*interval '1 second');
  IF v.estado='en_progreso' AND NOT public.fn_antes_del_limite(v_ahora,v_limite) THEN
    v_motivo := CASE WHEN v.fecha_cierre<=v.fecha_inicio+v.duracion_segundos*interval '1 second'
      THEN 'cierre_examen' ELSE 'tiempo_agotado' END;
    UPDATE public.intento_examen SET estado='finalizado',fecha_entrega=v_limite WHERE id=v.id;
    INSERT INTO public.cierre_intento(intento_id,motivo,fecha_limite) VALUES(v.id,v_motivo,v_limite)
    ON CONFLICT DO NOTHING;
    v.estado := 'finalizado'; v.fecha_entrega := v_limite;
  END IF;
  IF p_pregunta IS NOT NULL THEN
    SELECT ep.tiempo_segundos,jsonb_build_object('examen_pregunta_id',ep.id,'apartado_id',a.id,'tipo',p.tipo,'enunciado',p.enunciado,'opciones',p.opciones,'tiempo_segundos',ep.tiempo_segundos) INTO v_segundos,v_pregunta FROM public.examen_pregunta ep
    JOIN public.apartado a ON a.id=ep.apartado_id JOIN public.pregunta p ON p.id=ep.pregunta_id
    JOIN public.examen e ON e.id=a.examen_id JOIN public.grupo g ON g.id=e.grupo_id
    WHERE ep.id=p_pregunta AND a.examen_id=v.examen_id AND p.docente_id=g.docente_id;
    IF v_segundos IS NULL THEN RAISE EXCEPTION 'Pregunta no disponible' USING ERRCODE='P0001'; END IF;
    IF v.estado='en_progreso' THEN
      UPDATE public.intento_examen SET control_por_pregunta=true WHERE id=v.id AND NOT control_por_pregunta;
      v.control_por_pregunta := true;
      INSERT INTO public.tiempo_pregunta_intento(intento_id,examen_pregunta_id,primer_acceso,fecha_limite)
      VALUES(v.id,p_pregunta,v_ahora,least(v_limite,v_ahora+v_segundos*interval '1 second'))
      ON CONFLICT DO NOTHING;
    END IF;
    SELECT t.primer_acceso,least(t.fecha_limite,v_limite) INTO v_acceso,v_pregunta_limite
      FROM public.tiempo_pregunta_intento t WHERE t.intento_id=v.id AND t.examen_pregunta_id=p_pregunta;
  END IF;
  RETURN jsonb_build_object('intento_id',v.id,'estado',v.estado,'control_por_pregunta',v.control_por_pregunta,'servidor_ahora',v_ahora,
    'fecha_limite',v_limite,'fecha_entrega',v.fecha_entrega,'examen_pregunta_id',p_pregunta,
    'primer_acceso',v_acceso,'pregunta_fecha_limite',v_pregunta_limite,'pregunta',CASE WHEN v_acceso IS NOT NULL THEN v_pregunta END,
    'puede_responder',v.estado='en_progreso' AND public.fn_antes_del_limite(v_ahora,v_limite)
      AND (p_pregunta IS NULL OR public.fn_antes_del_limite(v_ahora,v_pregunta_limite)));
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_registrar_respuesta_temporizada(p_token_sesion uuid,p_examen_pregunta_id bigint,p_respuesta_texto text)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $fn$
DECLARE v record; v_ahora timestamptz; v_limite timestamptz; v_respuesta bigint;
BEGIN
 SELECT i.*,e.fecha_cierre,e.fecha_apertura,e.publicado,g.activo,
   EXISTS(SELECT 1 FROM public.alumno_grupo ag WHERE ag.alumno_id=i.alumno_id AND ag.grupo_id=g.id) AS inscrito
 INTO v FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
 JOIN public.grupo g ON g.id=e.grupo_id WHERE i.token_sesion=p_token_sesion FOR UPDATE OF i;
 v_ahora := clock_timestamp();
 IF v.id IS NULL OR v.estado<>'en_progreso' OR NOT v.publicado OR NOT v.activo OR NOT v.inscrito OR v_ahora<v.fecha_apertura
 THEN RAISE EXCEPTION 'Sesion no disponible' USING ERRCODE='P0001'; END IF;
 IF NOT public.fn_antes_del_limite(v_ahora,least(v.fecha_cierre,v.fecha_inicio+v.duracion_segundos*interval '1 second'))
 THEN RAISE EXCEPTION 'Tiempo agotado' USING ERRCODE='P1010'; END IF;
 SELECT t.fecha_limite INTO v_limite FROM public.tiempo_pregunta_intento t
 JOIN public.examen_pregunta ep ON ep.id=t.examen_pregunta_id
 JOIN public.apartado a ON a.id=ep.apartado_id AND a.examen_id=v.examen_id
 WHERE t.intento_id=v.id AND t.examen_pregunta_id=p_examen_pregunta_id;
 IF v_limite IS NULL THEN RAISE EXCEPTION 'Abre la pregunta antes de responder' USING ERRCODE='P1012'; END IF;
 IF NOT public.fn_antes_del_limite(v_ahora,v_limite) THEN RAISE EXCEPTION 'Tiempo de pregunta agotado' USING ERRCODE='P1011'; END IF;
 IF p_respuesta_texto IS NULL OR length(p_respuesta_texto)>20000 OR btrim(p_respuesta_texto)=''
 THEN RAISE EXCEPTION 'Respuesta invalida' USING ERRCODE='P0001'; END IF;
 INSERT INTO public.respuesta_alumno(intento_id,examen_pregunta_id,respuesta_texto)
 VALUES(v.id,p_examen_pregunta_id,p_respuesta_texto)
 ON CONFLICT (intento_id,examen_pregunta_id) DO UPDATE SET respuesta_texto=EXCLUDED.respuesta_texto
 WHERE public.respuesta_alumno.revisada_manual=false AND public.respuesta_alumno.puntaje_ia IS NULL
   AND public.respuesta_alumno.calificacion_final IS NULL RETURNING id INTO v_respuesta;
 IF v_respuesta IS NULL THEN RAISE EXCEPTION 'Respuesta ya evaluada' USING ERRCODE='P0001'; END IF;
 RETURN v_respuesta;
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_finalizar_intento(p_token uuid)
RETURNS TABLE(intento_id bigint,estado text,fecha_entrega timestamptz)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $fn$
DECLARE v jsonb; v_entrega timestamptz;
BEGIN
 v := public.fn_tiempo_intento(p_token,NULL);
 IF v->>'estado'='en_progreso' THEN
   v_entrega := clock_timestamp();
   -- Una entrega que cruza el límite mientras espera se registra como vencimiento.
   IF v_entrega >= (v->>'fecha_limite')::timestamptz THEN
     v := public.fn_tiempo_intento(p_token,NULL);
   ELSE
     UPDATE public.intento_examen SET estado='finalizado',fecha_entrega=v_entrega WHERE id=(v->>'intento_id')::bigint;
     INSERT INTO public.cierre_intento(intento_id,motivo,fecha_limite)
     VALUES((v->>'intento_id')::bigint,'entrega_alumno',(v->>'fecha_limite')::timestamptz) ON CONFLICT DO NOTHING;
     v := v || jsonb_build_object('fecha_entrega',v_entrega);
   END IF;
 END IF;
 RETURN QUERY SELECT (v->>'intento_id')::bigint,'finalizado'::text,(v->>'fecha_entrega')::timestamptz;
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_cerrar_intentos_vencidos(p_lote integer DEFAULT 100)
RETURNS integer LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $fn$
DECLARE v record; v_cantidad integer := 0; v_limite timestamptz;
BEGIN
 IF p_lote NOT BETWEEN 1 AND 1000 THEN RAISE EXCEPTION 'Lote invalido'; END IF;
 FOR v IN SELECT i.id,i.fecha_inicio,i.duracion_segundos,e.fecha_cierre FROM public.intento_examen i
 JOIN public.examen e ON e.id=i.examen_id WHERE i.estado='en_progreso'
 AND clock_timestamp()>=least(e.fecha_cierre,i.fecha_inicio+i.duracion_segundos*interval '1 second')
 ORDER BY i.id LIMIT p_lote FOR UPDATE OF i SKIP LOCKED LOOP
   v_limite := least(v.fecha_cierre,v.fecha_inicio+v.duracion_segundos*interval '1 second');
   UPDATE public.intento_examen SET estado='finalizado',fecha_entrega=v_limite WHERE id=v.id;
   INSERT INTO public.cierre_intento(intento_id,motivo,fecha_limite) VALUES(v.id,
    CASE WHEN v.fecha_cierre<=v.fecha_inicio+v.duracion_segundos*interval '1 second' THEN 'cierre_examen' ELSE 'tiempo_agotado' END,v_limite)
   ON CONFLICT DO NOTHING;
   v_cantidad := v_cantidad+1;
 END LOOP;
 RETURN v_cantidad;
END; $fn$;
DROP POLICY IF EXISTS cierre_intento_docente ON public.cierre_intento;
CREATE POLICY cierre_intento_docente ON public.cierre_intento FOR SELECT TO laravel_app USING (
 EXISTS(SELECT 1 FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id JOIN public.grupo g ON g.id=e.grupo_id
 WHERE i.id=cierre_intento.intento_id AND g.docente_id=nullif(current_setting('app.docente_id',true),'')::uuid)
);
GRANT SELECT ON public.cierre_intento TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_registrar_respuesta_temporizada(uuid,bigint,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_registrar_respuesta_temporizada(uuid,bigint,text) TO laravel_app;
CREATE INDEX IF NOT EXISTS idx_intento_abierto_tiempo ON public.intento_examen(fecha_inicio,id) WHERE estado='en_progreso';
REVOKE ALL ON FUNCTION public.fn_fijar_duracion_intento(),public.fn_tiempo_intento(uuid,bigint),public.fn_cerrar_intentos_vencidos(integer) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_tiempo_intento(uuid,bigint),public.fn_cerrar_intentos_vencidos(integer) TO laravel_app;
-- Recepción común para web y móvil; el SDK puede reportar eventos de cámara explícitos.
CREATE OR REPLACE FUNCTION public.fn_registrar_incidencia(p_token uuid,p_tipo text,p_severidad text,p_evidencia text DEFAULT NULL)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_intento bigint; v_id bigint;
BEGIN
 IF p_tipo NOT IN ('cambio_pestana','salida_pantalla','conexion','rostro_no_detectado','multiples_rostros','camara_desactivada','movimiento','otro')
 OR p_severidad NOT IN ('baja','media','alta') OR (p_evidencia IS NOT NULL AND (length(p_evidencia)>2048 OR p_evidencia !~ '^https://'))
 THEN RAISE EXCEPTION 'Incidencia inválida' USING ERRCODE='P0001'; END IF;
 SELECT i.id INTO v_intento FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
 JOIN public.grupo g ON g.id=e.grupo_id JOIN public.alumno_grupo ag ON ag.grupo_id=g.id AND ag.alumno_id=i.alumno_id
 WHERE i.token_sesion=p_token AND i.estado='en_progreso' AND g.activo AND e.publicado
 AND clock_timestamp()>=e.fecha_apertura AND public.fn_antes_del_limite(clock_timestamp(),least(e.fecha_cierre,i.fecha_inicio+i.duracion_segundos*interval '1 second'))
 FOR UPDATE OF i;
 IF v_intento IS NULL THEN RAISE EXCEPTION 'Sesión no disponible' USING ERRCODE='P0001'; END IF;
 INSERT INTO public.incidencia_proctoring(intento_id,tipo_alerta,nivel_severidad,evidencia_url)
 VALUES(v_intento,p_tipo,p_severidad,p_evidencia) RETURNING id INTO v_id;
 RETURN v_id;
END; $fn$;


-- Activación explícita del contrato v2: un intento no puede degradarse a v1 para omitir su límite por pregunta.
CREATE OR REPLACE FUNCTION public.fn_activar_tiempo_por_pregunta(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 PERFORM 1 FROM public.intento_examen WHERE token_sesion=p_token FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Sesión no disponible' USING ERRCODE='P0001'; END IF;
 UPDATE public.intento_examen SET control_por_pregunta=true WHERE token_sesion=p_token AND estado='en_progreso';
 RETURN public.fn_tiempo_intento(p_token,NULL);
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_registrar_respuesta_compatible(p_token uuid,p_pregunta bigint,p_texto text)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v record; v_ahora timestamptz;
BEGIN
 SELECT i.*,e.fecha_cierre INTO v FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
 WHERE i.token_sesion=p_token FOR UPDATE OF i;
 v_ahora := clock_timestamp();
 IF v.id IS NULL OR v.estado<>'en_progreso' THEN RAISE EXCEPTION 'Sesión no disponible' USING ERRCODE='P0001'; END IF;
 IF v.control_por_pregunta THEN RETURN public.fn_registrar_respuesta_temporizada(p_token,p_pregunta,p_texto); END IF;
 IF NOT public.fn_antes_del_limite(v_ahora,least(v.fecha_cierre,v.fecha_inicio+v.duracion_segundos*interval '1 second'))
 THEN RAISE EXCEPTION 'Tiempo agotado' USING ERRCODE='P1010'; END IF;
 RETURN public.fn_registrar_respuesta(p_token,p_pregunta,p_texto);
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_activar_tiempo_por_pregunta(uuid),public.fn_registrar_respuesta_compatible(uuid,bigint,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_activar_tiempo_por_pregunta(uuid),public.fn_registrar_respuesta_compatible(uuid,bigint,text) TO laravel_app;

COMMIT;
