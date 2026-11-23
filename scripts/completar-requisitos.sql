-- BigBitByte | Integración de requisitos. Ejecutar COMPLETO en el proyecto correcto.
-- Requiere las migraciones anteriores y respaldo. Cambia el contrato de preguntas a v2.
-- Este archivo reúne las cuatro migraciones en una transacción; no ejecutar ambos métodos.
BEGIN;

-- Archivo fuente: 20261123010000_control_tiempo_intentos.sql
-- BigBitByte | PR-BD-TI-01. Aplicar completo; no edita migraciones anteriores.

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


-- Archivo fuente: 20261123020000_recuperacion_pin_controlada.sql
-- BigBitByte | PR-BD-PIN-01. Los operadores se dan de alta únicamente por administración de BD.

ALTER TABLE public.credencial_alumno ADD COLUMN IF NOT EXISTS version bigint NOT NULL DEFAULT 1;
CREATE TABLE IF NOT EXISTS public.operador_soporte (
 usuario_id uuid PRIMARY KEY REFERENCES auth.users(id),
 funcion text NOT NULL CHECK(funcion IN ('verificador','autorizador')), activo boolean NOT NULL DEFAULT true
);
CREATE TABLE IF NOT EXISTS public.recuperacion_pin (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
 alumno_id bigint NOT NULL REFERENCES public.alumno(id),
 folio_caso text NOT NULL UNIQUE, referencia_identidad text NOT NULL, referencia_canal text NOT NULL,
 motivo text NOT NULL, solicitado_por uuid NOT NULL REFERENCES auth.users(id), solicitado_en timestamptz NOT NULL DEFAULT clock_timestamp(),
 autorizado_por uuid REFERENCES auth.users(id), autorizado_en timestamptz,
 CHECK(autorizado_por IS NULL OR autorizado_por<>solicitado_por)
);
CREATE TABLE IF NOT EXISTS public.evento_credencial_alumno (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY, alumno_id bigint NOT NULL REFERENCES public.alumno(id),
 caso_id bigint REFERENCES public.recuperacion_pin(id), tipo text NOT NULL CHECK(tipo IN ('revocacion','emision')),
 operador_id uuid NOT NULL REFERENCES auth.users(id), registrado_en timestamptz NOT NULL DEFAULT clock_timestamp()
);
-- Reserva permanente: un PIN revocado tampoco puede asignarse a otro alumno en el futuro.
CREATE TABLE IF NOT EXISTS public.codigo_alumno_reservado (codigo_hash text PRIMARY KEY CHECK(codigo_hash ~ '^[0-9a-f]{64}$'));
INSERT INTO public.codigo_alumno_reservado SELECT codigo_hash FROM public.credencial_alumno ON CONFLICT DO NOTHING;
ALTER TABLE public.operador_soporte ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.recuperacion_pin ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.evento_credencial_alumno ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.codigo_alumno_reservado ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.operador_soporte,public.recuperacion_pin,public.evento_credencial_alumno,public.codigo_alumno_reservado FROM PUBLIC,anon,authenticated,laravel_app;
CREATE OR REPLACE FUNCTION public.fn_reservar_codigo_alumno() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 IF TG_OP='INSERT' OR NEW.codigo_hash<>OLD.codigo_hash THEN
  INSERT INTO public.codigo_alumno_reservado VALUES(NEW.codigo_hash);
 END IF;
 RETURN NEW;
END; $fn$;
DROP TRIGGER IF EXISTS reservar_codigo_alumno ON public.credencial_alumno;
CREATE TRIGGER reservar_codigo_alumno BEFORE INSERT OR UPDATE OF codigo_hash ON public.credencial_alumno
FOR EACH ROW EXECUTE FUNCTION public.fn_reservar_codigo_alumno();

CREATE OR REPLACE FUNCTION public.fn_solicitar_recuperacion_pin(p_alumno bigint,p_caso text,p_identidad text,p_canal text,p_motivo text)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_actor uuid := nullif(current_setting('app.docente_id',true),'')::uuid; v_id bigint;
BEGIN
 IF NOT EXISTS(SELECT 1 FROM public.operador_soporte WHERE usuario_id=v_actor AND activo AND funcion='verificador')
 THEN RAISE EXCEPTION 'Operador no autorizado' USING ERRCODE='42501'; END IF;
 IF p_caso IS NULL OR length(btrim(p_caso)) NOT BETWEEN 4 AND 100 OR p_identidad IS NULL OR length(btrim(p_identidad)) NOT BETWEEN 4 AND 200
 OR p_canal IS NULL OR length(btrim(p_canal)) NOT BETWEEN 4 AND 200 OR p_motivo IS NULL OR length(btrim(p_motivo)) NOT BETWEEN 10 AND 1000
 OR NOT EXISTS(SELECT 1 FROM public.alumno_grupo WHERE alumno_id=p_alumno)
 THEN RAISE EXCEPTION 'Verificación incompleta' USING ERRCODE='P1003'; END IF;
 INSERT INTO public.recuperacion_pin(alumno_id,folio_caso,referencia_identidad,referencia_canal,motivo,solicitado_por)
 VALUES(p_alumno,btrim(p_caso),btrim(p_identidad),btrim(p_canal),btrim(p_motivo),v_actor) RETURNING id INTO v_id;
 RETURN v_id;
END; $fn$;
CREATE OR REPLACE FUNCTION public.fn_autorizar_recuperacion_pin(p_caso bigint,p_hash text)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_actor uuid := nullif(current_setting('app.docente_id',true),'')::uuid; v_caso record;
BEGIN
 IF NOT EXISTS(SELECT 1 FROM public.operador_soporte WHERE usuario_id=v_actor AND activo AND funcion='autorizador')
 THEN RAISE EXCEPTION 'Operador no autorizado' USING ERRCODE='42501'; END IF;
 SELECT * INTO v_caso FROM public.recuperacion_pin WHERE id=p_caso FOR UPDATE;
 IF v_caso.id IS NULL OR v_caso.solicitado_por=v_actor OR v_caso.autorizado_en IS NOT NULL
 THEN RAISE EXCEPTION 'Caso no disponible' USING ERRCODE='P1003'; END IF;
 -- Serializa con las respuestas y los cierres antes de revocar los secretos.
 PERFORM 1 FROM public.intento_examen WHERE alumno_id=v_caso.alumno_id ORDER BY id FOR UPDATE;
 PERFORM 1 FROM public.credencial_alumno WHERE alumno_id=v_caso.alumno_id FOR UPDATE;
 INSERT INTO public.evento_credencial_alumno(alumno_id,caso_id,tipo,operador_id)
 SELECT v_caso.alumno_id,p_caso,'revocacion',v_actor WHERE EXISTS(SELECT 1 FROM public.credencial_alumno WHERE alumno_id=v_caso.alumno_id);
 IF EXISTS(SELECT 1 FROM public.credencial_alumno WHERE alumno_id=v_caso.alumno_id) THEN
   UPDATE public.credencial_alumno SET codigo_hash=p_hash,version=version+1,
     actualizado_en=clock_timestamp(),revocado_en=NULL WHERE alumno_id=v_caso.alumno_id;
 ELSE
   INSERT INTO public.credencial_alumno(alumno_id,codigo_hash,version) VALUES(v_caso.alumno_id,p_hash,2);
 END IF;
 UPDATE public.intento_examen SET token_sesion=gen_random_uuid() WHERE alumno_id=v_caso.alumno_id;
 INSERT INTO public.evento_credencial_alumno(alumno_id,caso_id,tipo,operador_id) VALUES(v_caso.alumno_id,p_caso,'emision',v_actor);
 UPDATE public.recuperacion_pin SET autorizado_por=v_actor,autorizado_en=clock_timestamp() WHERE id=p_caso;
 RETURN v_caso.alumno_id;
END; $fn$;
-- Solo el servidor invoca estas funciones; la versión no sustituye la autenticación por PIN.
CREATE OR REPLACE FUNCTION public.fn_version_credencial_alumno(p_alumno bigint)
RETURNS bigint LANGUAGE sql STABLE SECURITY DEFINER SET search_path='' AS $fn$
 SELECT version FROM public.credencial_alumno WHERE alumno_id=p_alumno AND revocado_en IS NULL
$fn$;
REVOKE ALL ON FUNCTION public.fn_reservar_codigo_alumno(),public.fn_solicitar_recuperacion_pin(bigint,text,text,text,text),public.fn_autorizar_recuperacion_pin(bigint,text),public.fn_version_credencial_alumno(bigint) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_solicitar_recuperacion_pin(bigint,text,text,text,text),public.fn_autorizar_recuperacion_pin(bigint,text),public.fn_version_credencial_alumno(bigint) TO laravel_app;


-- Autenticación y versión salen de la misma transacción; no se consulta la versión después de aceptar el PIN.
CREATE OR REPLACE FUNCTION public.fn_ingresar_alumno_sesion_pin(p_folio text,p_hash text)
RETURNS TABLE(alumno_id bigint,grupo_id bigint,materia varchar,nombre varchar,credencial_version bigint)
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_version bigint;
BEGIN
 SELECT c.version INTO v_version FROM public.credencial_alumno c WHERE c.codigo_hash=p_hash AND c.revocado_en IS NULL FOR SHARE;
 IF v_version IS NULL THEN RAISE EXCEPTION 'Credencial inválida' USING ERRCODE='P1004'; END IF;
 RETURN QUERY SELECT x.alumno_id,x.grupo_id,x.materia,x.nombre,v_version FROM public.fn_ingresar_alumno_con_pin(p_folio,p_hash) x;
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_iniciar_intento_web(p_alumno bigint,p_grupo bigint,p_examen bigint,p_version bigint)
RETURNS TABLE(intento_id bigint,token_sesion uuid,fecha_inicio timestamptz,reanudado boolean)
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_intento public.intento_examen%ROWTYPE; v_version bigint;
BEGIN
 PERFORM pg_advisory_xact_lock(hashtextextended(p_examen::text||':'||p_alumno::text,0));
 SELECT i.* INTO v_intento FROM public.intento_examen i WHERE i.alumno_id=p_alumno AND i.examen_id=p_examen FOR UPDATE;
 SELECT c.version INTO v_version FROM public.credencial_alumno c WHERE c.alumno_id=p_alumno AND c.revocado_en IS NULL FOR SHARE;
 IF v_version IS NULL OR p_version IS NULL OR p_version<>v_version THEN RAISE EXCEPTION 'Credencial inválida' USING ERRCODE='P1004'; END IF;
 IF NOT EXISTS(SELECT 1 FROM public.examen e JOIN public.grupo g ON g.id=e.grupo_id JOIN public.docente d ON d.id=g.docente_id
 JOIN public.alumno_grupo ag ON ag.grupo_id=g.id AND ag.alumno_id=p_alumno
 WHERE e.id=p_examen AND g.id=p_grupo AND e.publicado AND g.activo AND e.plataforma_permitida IN ('web','ambas')
 AND clock_timestamp()>=e.fecha_apertura AND clock_timestamp()<e.fecha_cierre AND d.estado_pago='activo'
 AND EXISTS(SELECT 1 FROM public.pago pa WHERE pa.docente_id=d.id AND pa.paquete_id=d.paquete_id AND pa.estado='confirmado'
 AND clock_timestamp()>=pa.fecha_inicio_vigencia AND clock_timestamp()<pa.fecha_fin_vigencia))
 THEN RAISE EXCEPTION 'Examen no disponible' USING ERRCODE='P1002'; END IF;
 IF v_intento.id IS NOT NULL THEN
   IF v_intento.estado<>'en_progreso' THEN RAISE EXCEPTION 'Intento cerrado' USING ERRCODE='P1006'; END IF;
   RETURN QUERY SELECT v_intento.id,v_intento.token_sesion,v_intento.fecha_inicio,true;
 ELSE
   RETURN QUERY SELECT i.intento_id,i.token_sesion,i.fecha_inicio,false FROM public.fn_iniciar_intento(p_examen,p_alumno,'web') i;
 END IF;
END; $fn$;

CREATE OR REPLACE FUNCTION public.fn_iniciar_intento_pase(p_examen bigint,p_alumno bigint,p_plataforma text,p_version bigint DEFAULT NULL)
RETURNS TABLE(intento_id bigint,token_sesion uuid,fecha_inicio timestamptz)
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_version bigint;
BEGIN
 PERFORM pg_advisory_xact_lock(hashtextextended(p_examen::text||':'||p_alumno::text,0));
 PERFORM 1 FROM public.intento_examen WHERE alumno_id=p_alumno AND examen_id=p_examen FOR UPDATE;
 SELECT c.version INTO v_version FROM public.credencial_alumno c WHERE c.alumno_id=p_alumno AND c.revocado_en IS NULL FOR SHARE;
 IF v_version IS NULL OR (p_version IS NULL AND v_version<>1) OR (p_version IS NOT NULL AND p_version<>v_version)
 THEN RAISE EXCEPTION 'Pase revocado' USING ERRCODE='P1004'; END IF;
 RETURN QUERY SELECT i.intento_id,i.token_sesion,i.fecha_inicio FROM public.fn_iniciar_intento(p_examen,p_alumno,p_plataforma) i;
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_ingresar_alumno_sesion_pin(text,text),public.fn_iniciar_intento_web(bigint,bigint,bigint,bigint),public.fn_iniciar_intento_pase(bigint,bigint,text,bigint) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_ingresar_alumno_sesion_pin(text,text),public.fn_iniciar_intento_web(bigint,bigint,bigint,bigint),public.fn_iniciar_intento_pase(bigint,bigint,text,bigint) TO laravel_app;

CREATE OR REPLACE FUNCTION public.fn_iniciar_intento_movil(
    p_folio text, p_codigo_hash text, p_examen_id bigint
)
RETURNS TABLE (intento_id bigint, token_sesion uuid, fecha_inicio timestamptz, reanudado boolean)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE
    v_alumno bigint;
    v_intento public.intento_examen%ROWTYPE;
    v_existente boolean;
BEGIN
    SELECT c.alumno_id INTO v_alumno
    FROM public.credencial_alumno c
    WHERE c.codigo_hash = p_codigo_hash AND c.revocado_en IS NULL;

    IF v_alumno IS NULL THEN
        RAISE EXCEPTION 'CREDENCIAL_NO_VALIDA' USING ERRCODE = 'P1004';
    END IF;

    -- Se comparte el bloqueo con fn_iniciar_intento: reintentos de red y otras
    -- plataformas no crean intentos duplicados ni reinician la fecha original.
    PERFORM pg_catalog.pg_advisory_xact_lock(
        pg_catalog.hashtextextended(p_examen_id::text || ':' || v_alumno::text, 0)
    );

    IF NOT EXISTS (
        SELECT 1 FROM public.examen e
        JOIN public.grupo g ON g.id = e.grupo_id
        JOIN public.alumno_grupo ag ON ag.grupo_id = g.id AND ag.alumno_id = v_alumno
        JOIN public.docente d ON d.id = g.docente_id
        WHERE e.id = p_examen_id
          AND g.folio_codigo = pg_catalog.upper(pg_catalog.btrim(p_folio))
          AND e.publicado AND g.activo
          AND e.plataforma_permitida IN ('movil', 'ambas')
          AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre
          AND d.estado_pago = 'activo'
          AND EXISTS (
              SELECT 1 FROM public.pago pa
              WHERE pa.docente_id = d.id AND pa.paquete_id = d.paquete_id
                AND pa.estado = 'confirmado'
                AND CURRENT_TIMESTAMP >= pa.fecha_inicio_vigencia
                AND CURRENT_TIMESTAMP < pa.fecha_fin_vigencia
          )
    ) THEN
        RAISE EXCEPTION 'EXAMEN_NO_DISPONIBLE' USING ERRCODE = 'P1002';
    END IF;

    SELECT i.* INTO v_intento
    FROM public.intento_examen i
    WHERE i.examen_id = p_examen_id AND i.alumno_id = v_alumno
    FOR UPDATE;

    v_existente := FOUND;
    -- Revalidar el PIN después de bloquear el intento, con orden compatible con recuperación.
    PERFORM 1 FROM public.credencial_alumno c WHERE c.alumno_id=v_alumno AND c.codigo_hash=p_codigo_hash AND c.revocado_en IS NULL FOR SHARE;
    IF NOT FOUND THEN RAISE EXCEPTION 'CREDENCIAL_NO_VALIDA' USING ERRCODE='P1004'; END IF;
    IF v_existente THEN
        IF v_intento.estado <> 'en_progreso' THEN
            RAISE EXCEPTION 'INTENTO_CERRADO' USING ERRCODE = 'P1006';
        END IF;
        RETURN QUERY SELECT v_intento.id, v_intento.token_sesion, v_intento.fecha_inicio, true;
    ELSE
        RETURN QUERY
        SELECT i.intento_id, i.token_sesion, i.fecha_inicio, false
        FROM public.fn_iniciar_intento(p_examen_id, v_alumno, 'movil') i;
    END IF;
END;
$fn$;



-- Archivo fuente: 20261123030000_calificacion_en_segundo_plano.sql
-- Entrega atómica + bandeja de trabajo duradera; no hace llamadas HTTP dentro de una transacción.

CREATE TABLE IF NOT EXISTS public.trabajo_calificacion (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
 intento_id bigint NOT NULL REFERENCES public.intento_examen(id) ON DELETE CASCADE,
 respuesta_id bigint UNIQUE REFERENCES public.respuesta_alumno(id) ON DELETE CASCADE,
 tipo text NOT NULL CHECK(tipo IN ('objetivas','ia')), clave text NOT NULL UNIQUE,
 estado text NOT NULL DEFAULT 'pendiente' CHECK(estado IN ('pendiente','procesando','completo','fallido')),
 intentos integer NOT NULL DEFAULT 0, disponible_en timestamptz NOT NULL DEFAULT clock_timestamp(),
 reserva uuid, reservado_hasta timestamptz, ultimo_error text,
 CHECK((tipo='ia' AND respuesta_id IS NOT NULL) OR (tipo='objetivas' AND respuesta_id IS NULL))
);
ALTER TABLE public.trabajo_calificacion ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.trabajo_calificacion FROM PUBLIC,anon,authenticated,laravel_app;
CREATE INDEX IF NOT EXISTS idx_trabajo_calificacion_pendiente ON public.trabajo_calificacion(disponible_en,id) WHERE estado IN ('pendiente','procesando');
CREATE OR REPLACE FUNCTION public.fn_encolar_calificacion() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 IF NEW.estado='finalizado' AND OLD.estado IS DISTINCT FROM NEW.estado THEN
  INSERT INTO public.trabajo_calificacion(intento_id,tipo,clave) VALUES(NEW.id,'objetivas','intento:'||NEW.id) ON CONFLICT DO NOTHING;
  INSERT INTO public.trabajo_calificacion(intento_id,respuesta_id,tipo,clave)
  SELECT NEW.id,r.id,'ia','respuesta:'||r.id FROM public.respuesta_alumno r
  JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id JOIN public.pregunta p ON p.id=ep.pregunta_id
  WHERE r.intento_id=NEW.id AND p.tipo IN ('abierta','programacion') AND r.calificacion_final IS NULL
  ON CONFLICT DO NOTHING;
 END IF;
 RETURN NEW;
END; $fn$;
DROP TRIGGER IF EXISTS encolar_calificacion ON public.intento_examen;
CREATE TRIGGER encolar_calificacion AFTER UPDATE OF estado ON public.intento_examen
FOR EACH ROW EXECUTE FUNCTION public.fn_encolar_calificacion();
-- No se cobran consultas retroactivas automáticamente para intentos históricos.
CREATE OR REPLACE FUNCTION public.fn_tomar_trabajo_calificacion(p_ia boolean DEFAULT false)
RETURNS TABLE(trabajo_id bigint,reserva uuid,docente_id uuid,intento_id bigint,respuesta_id bigint,tipo text)
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v record; v_reserva uuid := gen_random_uuid();
BEGIN
 UPDATE public.trabajo_calificacion SET estado='fallido',ultimo_error='reserva_agotada'
 WHERE estado='procesando' AND intentos>=5 AND reservado_hasta<clock_timestamp();
 SELECT t.id INTO v FROM public.trabajo_calificacion t
 WHERE (t.tipo='objetivas' OR p_ia) AND t.intentos<5 AND t.disponible_en<=clock_timestamp()
 AND (t.estado='pendiente' OR (t.estado='procesando' AND t.reservado_hasta<clock_timestamp()))
 ORDER BY CASE WHEN t.tipo='objetivas' THEN 0 ELSE 1 END,t.id LIMIT 1 FOR UPDATE SKIP LOCKED;
 IF v.id IS NULL THEN RETURN; END IF;
 UPDATE public.trabajo_calificacion t SET estado='procesando',reserva=v_reserva,
 reservado_hasta=clock_timestamp()+interval '2 minutes',intentos=t.intentos+1 WHERE t.id=v.id;
 RETURN QUERY SELECT t.id,v_reserva,g.docente_id,t.intento_id,t.respuesta_id,t.tipo
 FROM public.trabajo_calificacion t JOIN public.intento_examen i ON i.id=t.intento_id
 JOIN public.examen e ON e.id=i.examen_id JOIN public.grupo g ON g.id=e.grupo_id WHERE t.id=v.id;
END; $fn$;
CREATE OR REPLACE FUNCTION public.fn_resolver_trabajo_calificacion(p_id bigint,p_reserva uuid,p_error text DEFAULT NULL)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 UPDATE public.trabajo_calificacion SET estado=CASE WHEN p_error IS NULL THEN 'completo' WHEN intentos>=5 THEN 'fallido' ELSE 'pendiente' END,
 disponible_en=clock_timestamp()+CASE WHEN p_error='limite' THEN interval '1 day' ELSE interval '5 minutes' END,
 ultimo_error=p_error,reservado_hasta=NULL
 WHERE id=p_id AND reserva=p_reserva AND estado='procesando';
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_encolar_calificacion(),public.fn_tomar_trabajo_calificacion(boolean),public.fn_resolver_trabajo_calificacion(bigint,uuid,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_tomar_trabajo_calificacion(boolean),public.fn_resolver_trabajo_calificacion(bigint,uuid,text) TO laravel_app;

DROP POLICY IF EXISTS trabajo_calificacion_docente ON public.trabajo_calificacion;
CREATE POLICY trabajo_calificacion_docente ON public.trabajo_calificacion FOR SELECT TO laravel_app USING (
 EXISTS(SELECT 1 FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id JOIN public.grupo g ON g.id=e.grupo_id
 WHERE i.id=trabajo_calificacion.intento_id AND g.docente_id=nullif(current_setting('app.docente_id',true),'')::uuid)
);
GRANT SELECT ON public.trabajo_calificacion TO laravel_app;

-- Solo muestra propuestas vigentes cuando el docente elige resultado aproximado.
CREATE OR REPLACE FUNCTION public.fn_resultado_alumno(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v record; v_total integer; v_final integer; v_estimadas integer; v_nota numeric;
BEGIN
 SELECT i.id,e.id AS examen_id,e.escala,e.resultado_visible INTO v FROM public.intento_examen i
 JOIN public.examen e ON e.id=i.examen_id WHERE i.token_sesion=p_token AND i.estado='finalizado';
 IF v.id IS NULL OR v.resultado_visible='oculto' THEN RAISE EXCEPTION 'Resultado no disponible' USING ERRCODE='P0001'; END IF;
 SELECT count(*),count(*) FILTER(WHERE r.id IS NULL OR r.calificacion_final IS NOT NULL),
 count(*) FILTER(WHERE r.id IS NULL OR r.calificacion_final IS NOT NULL OR
   (r.puntaje_ia IS NOT NULL AND r.retroalimentacion_ia->>'huella_contenido'=md5(jsonb_build_array(p.tipo,p.enunciado,ep.respuesta_modelo_snapshot,r.respuesta_texto)::text))),
 coalesce(sum(coalesce(r.calificacion_final,
   CASE WHEN v.resultado_visible='aproximado' AND r.retroalimentacion_ia->>'huella_contenido'=md5(jsonb_build_array(p.tipo,p.enunciado,ep.respuesta_modelo_snapshot,r.respuesta_texto)::text)
   THEN r.puntaje_ia END,0)*ep.ponderacion/100),0)
 INTO v_total,v_final,v_estimadas,v_nota FROM public.apartado a
 JOIN public.examen_pregunta ep ON ep.apartado_id=a.id JOIN public.pregunta p ON p.id=ep.pregunta_id
 LEFT JOIN public.respuesta_alumno r ON r.intento_id=v.id AND r.examen_pregunta_id=ep.id WHERE a.examen_id=v.examen_id;
 IF (v.resultado_visible='final' AND v_final<>v_total) OR (v_final<>v_total AND v_estimadas<>v_total)
 THEN RAISE EXCEPTION 'Resultado pendiente de revisión' USING ERRCODE='P0001'; END IF;
 RETURN jsonb_build_object('intento_id',v.id,'escala',v.escala,'estado',CASE WHEN v_final=v_total THEN 'final' ELSE 'aproximado' END,
 'calificacion',round(CASE WHEN v.escala='0-10' THEN v_nota/10 ELSE v_nota END,2),'calificadas',v_final,'total_preguntas',v_total);
END; $fn$;

-- Archivo fuente: 20261123040000_administracion_controlada.sql
-- Administración global explícita; no se concede por parámetros de URL o metadatos editables.

ALTER TABLE public.docente ADD COLUMN IF NOT EXISTS suspendido_en timestamptz;
CREATE TABLE IF NOT EXISTS public.administrador_sistema (
 usuario_id uuid PRIMARY KEY REFERENCES auth.users(id), activo boolean NOT NULL DEFAULT true
);
CREATE TABLE IF NOT EXISTS public.evento_administracion (
 id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
 operador_id uuid NOT NULL REFERENCES auth.users(id), accion text NOT NULL, recurso text NOT NULL,
 anterior text, nuevo text NOT NULL, motivo text NOT NULL, registrado_en timestamptz NOT NULL DEFAULT clock_timestamp()
);
ALTER TABLE public.administrador_sistema ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.evento_administracion ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.administrador_sistema,public.evento_administracion FROM PUBLIC,anon,authenticated,laravel_app;
CREATE OR REPLACE FUNCTION public.fn_es_administrador()
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path='' AS $fn$
 SELECT EXISTS(SELECT 1 FROM public.administrador_sistema WHERE usuario_id=nullif(current_setting('app.docente_id',true),'')::uuid AND activo)
$fn$;
CREATE OR REPLACE FUNCTION public.fn_admin_docentes()
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 IF NOT public.fn_es_administrador() THEN RAISE EXCEPTION 'No autorizado' USING ERRCODE='42501'; END IF;
 RETURN coalesce((SELECT jsonb_agg(x ORDER BY x.nombre,x.id) FROM (
 SELECT d.id,d.nombre,d.correo,d.estado_pago,d.paquete_id,d.suspendido_en FROM public.docente d
 ) x),'[]'::jsonb);
END; $fn$;
CREATE OR REPLACE FUNCTION public.fn_admin_pagos()
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
BEGIN
 IF NOT public.fn_es_administrador() THEN RAISE EXCEPTION 'No autorizado' USING ERRCODE='42501'; END IF;
 RETURN coalesce((SELECT jsonb_agg(x ORDER BY x.id DESC) FROM (
 SELECT pa.id,pa.docente_id,d.nombre,pa.monto,pa.estado,pa.fecha,pa.paquete_id,pa.fecha_inicio_vigencia,pa.fecha_fin_vigencia
 FROM public.pago pa JOIN public.docente d ON d.id=pa.docente_id
 ) x),'[]'::jsonb);
END; $fn$;
CREATE OR REPLACE FUNCTION public.fn_admin_estado_docente(p_docente uuid,p_suspender boolean,p_motivo text)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_anterior timestamptz; v_actor uuid := nullif(current_setting('app.docente_id',true),'')::uuid;
BEGIN
 IF NOT public.fn_es_administrador() THEN RAISE EXCEPTION 'No autorizado' USING ERRCODE='42501'; END IF;
 IF p_docente=v_actor OR p_motivo IS NULL OR length(btrim(p_motivo)) NOT BETWEEN 10 AND 1000 OR p_suspender IS NULL
 THEN RAISE EXCEPTION 'Operación inválida' USING ERRCODE='P1003'; END IF;
 SELECT suspendido_en INTO v_anterior FROM public.docente WHERE id=p_docente FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Docente no disponible' USING ERRCODE='P1002'; END IF;
 IF (v_anterior IS NOT NULL)=p_suspender THEN RETURN; END IF;
 UPDATE public.docente d SET suspendido_en=CASE WHEN p_suspender THEN clock_timestamp() END,
 estado_pago=CASE WHEN NOT p_suspender AND EXISTS(SELECT 1 FROM public.pago pa WHERE pa.docente_id=d.id AND pa.paquete_id=d.paquete_id AND pa.estado='confirmado' AND clock_timestamp()>=pa.fecha_inicio_vigencia AND clock_timestamp()<pa.fecha_fin_vigencia) THEN 'activo' ELSE 'inactivo' END
 WHERE d.id=p_docente;
 INSERT INTO public.evento_administracion(operador_id,accion,recurso,anterior,nuevo,motivo)
 VALUES(v_actor,'estado_docente',p_docente::text,CASE WHEN v_anterior IS NULL THEN 'habilitado' ELSE 'suspendido' END,CASE WHEN p_suspender THEN 'suspendido' ELSE 'habilitado' END,btrim(p_motivo));
END; $fn$;
CREATE OR REPLACE FUNCTION public.fn_admin_estado_pago(p_pago bigint,p_estado text,p_motivo text)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v record; v_actor uuid := nullif(current_setting('app.docente_id',true),'')::uuid;
BEGIN
 IF NOT public.fn_es_administrador() THEN RAISE EXCEPTION 'No autorizado' USING ERRCODE='42501'; END IF;
 IF p_estado NOT IN ('pendiente','confirmado','rechazado') OR p_estado IS NULL OR p_motivo IS NULL OR length(btrim(p_motivo)) NOT BETWEEN 10 AND 1000
 THEN RAISE EXCEPTION 'Operación inválida' USING ERRCODE='P1003'; END IF;
 -- Orden de bloqueo consistente con pagos de la aplicación: docente antes de pago.
 SELECT pa.docente_id INTO v FROM public.pago pa WHERE pa.id=p_pago;
 IF v.docente_id IS NULL THEN RAISE EXCEPTION 'Pago no disponible' USING ERRCODE='P1002'; END IF;
 PERFORM 1 FROM public.docente WHERE id=v.docente_id FOR UPDATE;
 SELECT pa.* INTO v FROM public.pago pa WHERE pa.id=p_pago FOR UPDATE;
 IF v.estado=p_estado THEN RETURN; END IF;
 UPDATE public.pago SET estado=p_estado WHERE id=p_pago;
 UPDATE public.docente d SET estado_pago=CASE WHEN d.suspendido_en IS NULL AND EXISTS(
   SELECT 1 FROM public.pago pa WHERE pa.docente_id=d.id AND pa.paquete_id=d.paquete_id AND pa.estado='confirmado'
   AND clock_timestamp()>=pa.fecha_inicio_vigencia AND clock_timestamp()<pa.fecha_fin_vigencia) THEN 'activo' ELSE 'inactivo' END WHERE d.id=v.docente_id;
 INSERT INTO public.evento_administracion(operador_id,accion,recurso,anterior,nuevo,motivo)
 VALUES(v_actor,'estado_pago',p_pago::text,v.estado,p_estado,btrim(p_motivo));
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_es_administrador(),public.fn_admin_docentes(),public.fn_admin_pagos(),public.fn_admin_estado_docente(uuid,boolean,text),public.fn_admin_estado_pago(bigint,text,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_es_administrador(),public.fn_admin_docentes(),public.fn_admin_pagos(),public.fn_admin_estado_docente(uuid,boolean,text),public.fn_admin_estado_pago(bigint,text,text) TO laravel_app;

-- Archivo fuente: 20261123050000_revocacion_sesiones_docente.sql
-- Revocación inmediata en el gateway; Supabase conserva la responsabilidad de validar JWT.

CREATE TABLE IF NOT EXISTS public.sesion_docente_revocada (
    token_hash text PRIMARY KEY CHECK (token_hash ~ '^[a-f0-9]{64}$'),
    docente_id uuid NOT NULL,
    session_id uuid,
    expira_en timestamptz NOT NULL,
    revocada_en timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE INDEX IF NOT EXISTS sesion_docente_revocada_session_idx
    ON public.sesion_docente_revocada(session_id) WHERE session_id IS NOT NULL;
ALTER TABLE public.sesion_docente_revocada ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON public.sesion_docente_revocada FROM PUBLIC, anon, authenticated, laravel_app;

CREATE OR REPLACE FUNCTION public.fn_revocar_sesion_docente(p_hash text, p_session uuid, p_expira timestamptz)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, public AS $$
DECLARE v_docente uuid := nullif(current_setting('app.docente_id',true),'')::uuid;
BEGIN
    IF v_docente IS NULL THEN RAISE EXCEPTION 'NO_AUTORIZADO' USING ERRCODE='42501'; END IF;
    INSERT INTO public.sesion_docente_revocada(token_hash,docente_id,session_id,expira_en)
    VALUES(p_hash,v_docente,p_session,p_expira) ON CONFLICT(token_hash) DO NOTHING;
END $$;

-- Una sesión revocada también invalida JWT posteriores emitidos desde su refresh token.
-- No borrar por expira_en mientras la sesión de Supabase pueda continuar vigente.
CREATE OR REPLACE FUNCTION public.fn_token_docente_revocado(p_hash text, p_session uuid)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, public AS $$
    SELECT EXISTS(SELECT 1 FROM public.sesion_docente_revocada
        WHERE token_hash=p_hash OR (p_session IS NOT NULL AND session_id=p_session));
$$;
REVOKE ALL ON FUNCTION public.fn_revocar_sesion_docente(text,uuid,timestamptz), public.fn_token_docente_revocado(text,uuid) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_revocar_sesion_docente(text,uuid,timestamptz), public.fn_token_docente_revocado(text,uuid) TO laravel_app;

COMMIT;

SELECT
    to_regprocedure('public.fn_token_docente_revocado(text,uuid)') IS NOT NULL AS revocacion_sesiones,
  to_regprocedure('public.fn_registrar_respuesta_temporizada(uuid,bigint,text)') IS NOT NULL AS guardado_temporizado,
  to_regprocedure('public.fn_ingresar_alumno_sesion_pin(text,text)') IS NOT NULL AS sesion_pin_atomica,
  to_regprocedure('public.fn_iniciar_intento_web(bigint,bigint,bigint,bigint)') IS NOT NULL AS reanudacion_web,
  to_regprocedure('public.fn_autorizar_recuperacion_pin(bigint,text)') IS NOT NULL AS recuperacion_pin,
  to_regprocedure('public.fn_tomar_trabajo_calificacion(boolean)') IS NOT NULL AS cola_calificacion,
  to_regprocedure('public.fn_admin_docentes()') IS NOT NULL AS administracion;
