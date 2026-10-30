-- BigBitByte: ampliacion de funciones, sin cambiar las 17 tablas existentes.
-- Aplicar SOLO tras revision y respaldo. Los roles y RLS deben estar instalados.
BEGIN;

-- El alumno solo inicia mediante un pase CIFRADO entregado por docente desde Laravel.
-- La funcion refuerza pertenencia/horario; Laravel verifica el pase antes de llamarla.
CREATE OR REPLACE FUNCTION public.fn_iniciar_intento(p_examen_id bigint, p_alumno_id bigint, p_plataforma text)
RETURNS TABLE(intento_id bigint, token_sesion uuid, fecha_inicio timestamptz)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_id bigint; v_token uuid; v_inicio timestamptz;
BEGIN
  IF p_plataforma NOT IN ('web','movil') THEN
    RAISE EXCEPTION 'Plataforma invalida' USING ERRCODE='P0001';
  END IF;
  -- Bloqueo serializado por alumno y examen; no puede crear doble intento.
  PERFORM pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(p_examen_id::text||':'||p_alumno_id::text, 0));
  IF NOT EXISTS (
    SELECT 1 FROM public.examen e
    JOIN public.grupo g ON g.id=e.grupo_id
    JOIN public.docente d ON d.id=g.docente_id
    JOIN public.pago pa ON pa.docente_id=d.id AND pa.paquete_id=d.paquete_id
    JOIN public.alumno_grupo ag ON ag.grupo_id=g.id
    WHERE e.id=p_examen_id AND ag.alumno_id=p_alumno_id
      AND e.publicado AND g.activo
      AND (e.plataforma_permitida='ambas' OR e.plataforma_permitida=p_plataforma)
      AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre
      AND d.estado_pago='activo' AND pa.estado='confirmado'
      AND CURRENT_TIMESTAMP >= pa.fecha_inicio_vigencia
      AND CURRENT_TIMESTAMP < pa.fecha_fin_vigencia
  ) THEN RAISE EXCEPTION 'Examen no disponible' USING ERRCODE='P0001'; END IF;
  IF EXISTS (SELECT 1 FROM public.intento_examen WHERE examen_id=p_examen_id AND alumno_id=p_alumno_id) THEN
    RAISE EXCEPTION 'El alumno ya tiene un intento; no se permite iniciar otro' USING ERRCODE='P0001';
  END IF;
  INSERT INTO public.intento_examen(examen_id, alumno_id, numero_intento, estado)
  VALUES (p_examen_id, p_alumno_id, 1, 'en_progreso')
  RETURNING id, intento_examen.token_sesion, intento_examen.fecha_inicio INTO v_id,v_token,v_inicio;
  RETURN QUERY SELECT v_id,v_token,v_inicio;
END;
$fn$;

-- Solo devuelve contenido del examen, NUNCA respuesta_modelo ni snapshot.
CREATE OR REPLACE FUNCTION public.fn_preguntas_intento(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_examen_id bigint; v_orden text; v_lista jsonb;
BEGIN
  SELECT i.examen_id,e.orden INTO v_examen_id,v_orden
  FROM public.intento_examen i
  JOIN public.examen e ON e.id=i.examen_id
  JOIN public.grupo g ON g.id=e.grupo_id
  JOIN public.alumno_grupo ag ON ag.grupo_id=g.id AND ag.alumno_id=i.alumno_id
  WHERE i.token_sesion=p_token AND i.estado='en_progreso'
    AND e.publicado AND g.activo
    AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre;
  IF v_examen_id IS NULL THEN RAISE EXCEPTION 'Sesion no disponible' USING ERRCODE='P0001'; END IF;
  SELECT COALESCE(pg_catalog.jsonb_agg(x.obj ORDER BY x.sort_key), '[]'::jsonb)
  INTO v_lista FROM (
    SELECT pg_catalog.jsonb_build_object(
      'examen_pregunta_id',ep.id, 'apartado_id',a.id,
      'tipo',p.tipo, 'enunciado',p.enunciado,
      'opciones',p.opciones, 'tiempo_segundos',ep.tiempo_segundos
    ) AS obj,
    CASE WHEN v_orden='aleatorio' THEN md5(ep.id::text || p_token::text)
         ELSE pg_catalog.lpad(ep.id::text,20,'0') END AS sort_key
    FROM public.examen_pregunta ep
    JOIN public.apartado a ON a.id=ep.apartado_id
    JOIN public.pregunta p ON p.id=ep.pregunta_id
    WHERE a.examen_id=v_examen_id
  ) x;
  RETURN pg_catalog.jsonb_build_object('examen_id',v_examen_id,'preguntas',v_lista);
END;
$fn$;

CREATE OR REPLACE FUNCTION public.fn_finalizar_intento(p_token uuid)
RETURNS TABLE(intento_id bigint, estado text, fecha_entrega timestamptz)
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_id bigint; v_estado text; v_entrega timestamptz;
BEGIN
  SELECT i.id,i.estado,i.fecha_entrega INTO v_id,v_estado,v_entrega
  FROM public.intento_examen i
  WHERE i.token_sesion=p_token FOR UPDATE;
  IF v_id IS NULL OR v_estado='anulado' THEN
    RAISE EXCEPTION 'Sesion no disponible' USING ERRCODE='P0001';
  END IF;
  IF v_estado='en_progreso' THEN
    UPDATE public.intento_examen i SET estado='finalizado', fecha_entrega=CURRENT_TIMESTAMP
    WHERE i.id=v_id RETURNING i.fecha_entrega INTO v_entrega;
  END IF;
  RETURN QUERY SELECT v_id,'finalizado'::text,v_entrega;
END;
$fn$;

CREATE OR REPLACE FUNCTION public.fn_registrar_incidencia(
  p_token uuid,p_tipo text,p_severidad text,p_evidencia text DEFAULT NULL)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_intento bigint; v_id bigint;
BEGIN
  IF p_tipo NOT IN ('cambio_pestana','salida_pantalla','conexion','otro')
     OR p_severidad NOT IN ('baja','media','alta')
     OR (p_evidencia IS NOT NULL AND (pg_catalog.length(p_evidencia)>2048 OR p_evidencia !~ '^https://')) THEN
    RAISE EXCEPTION 'Incidencia invalida' USING ERRCODE='P0001';
  END IF;
  SELECT i.id INTO v_intento FROM public.intento_examen i
  JOIN public.examen e ON e.id=i.examen_id
  JOIN public.grupo g ON g.id=e.grupo_id
  WHERE i.token_sesion=p_token AND i.estado='en_progreso'
    AND e.publicado AND g.activo AND CURRENT_TIMESTAMP BETWEEN e.fecha_apertura AND e.fecha_cierre
  FOR UPDATE OF i;
  IF v_intento IS NULL THEN RAISE EXCEPTION 'Sesion invalida' USING ERRCODE='P0001'; END IF;
  INSERT INTO public.incidencia_proctoring(intento_id,tipo_alerta,nivel_severidad,evidencia_url)
  VALUES (v_intento,p_tipo,p_severidad,p_evidencia) RETURNING id INTO v_id;
  RETURN v_id;
END;
$fn$;

-- Notas en escala 0..100 para cada pregunta. El resultado ponderado usa ep.ponderacion.
-- Solo puede calificar el docente DUEÑO y solo intentos FINALIZADOS.
CREATE OR REPLACE FUNCTION public.fn_calificar_respuesta(
  p_respuesta bigint,p_nota numeric,p_motivo text DEFAULT NULL)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_old numeric; v_docente uuid; v_estado text;
BEGIN
  v_docente := public.app_docente_id();
  IF v_docente IS NULL OR p_nota IS NULL OR p_nota<0 OR p_nota>100 THEN
    RAISE EXCEPTION 'Calificacion invalida' USING ERRCODE='P0001';
  END IF;
  SELECT r.calificacion_final,i.estado INTO v_old,v_estado
  FROM public.respuesta_alumno r
  JOIN public.intento_examen i ON i.id=r.intento_id
  JOIN public.examen e ON e.id=i.examen_id
  JOIN public.grupo g ON g.id=e.grupo_id
  JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
  JOIN public.apartado a ON a.id=ep.apartado_id AND a.examen_id=e.id
  WHERE r.id=p_respuesta AND g.docente_id=v_docente
  FOR UPDATE OF r;
  IF NOT FOUND OR v_estado<>'finalizado' THEN
    RAISE EXCEPTION 'Respuesta no disponible para calificar' USING ERRCODE='P0001';
  END IF;
  IF v_old IS NOT NULL AND (p_motivo IS NULL OR pg_catalog.btrim(p_motivo)='') THEN
    RAISE EXCEPTION 'Motivo requerido para modificar calificacion' USING ERRCODE='P0001';
  END IF;
  UPDATE public.respuesta_alumno SET calificacion_final=p_nota,revisada_manual=true WHERE id=p_respuesta;
  INSERT INTO public.historial_calificacion(respuesta_alumno_id,calificacion_anterior,calificacion_nueva,modificado_por,motivo)
  VALUES (p_respuesta,v_old,p_nota,v_docente,p_motivo);
  RETURN p_respuesta;
END;
$fn$;

-- Calificacion automatica basica: SOLO opcion multiple exacta. No simula IA.
CREATE OR REPLACE FUNCTION public.fn_autocalificar_intento(p_intento bigint)
RETURNS integer LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_docente uuid; v_estado text; v_count integer:=0; v_row record; v_valor numeric;
BEGIN
  v_docente := public.app_docente_id();
  SELECT i.estado INTO v_estado FROM public.intento_examen i
  JOIN public.examen e ON e.id=i.examen_id JOIN public.grupo g ON g.id=e.grupo_id
  WHERE i.id=p_intento AND g.docente_id=v_docente;
  IF NOT FOUND OR v_estado<>'finalizado' THEN
    RAISE EXCEPTION 'Intento no disponible' USING ERRCODE='P0001'; END IF;
  FOR v_row IN
    SELECT r.id, r.respuesta_texto, ep.respuesta_modelo_snapshot
    FROM public.respuesta_alumno r
    JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
    JOIN public.pregunta p ON p.id=ep.pregunta_id
    WHERE r.intento_id=p_intento AND p.tipo='opcion_multiple'
      AND r.calificacion_final IS NULL
    FOR UPDATE OF r
  LOOP
    v_valor := CASE WHEN pg_catalog.lower(pg_catalog.btrim(v_row.respuesta_texto)) =
                         pg_catalog.lower(pg_catalog.btrim(v_row.respuesta_modelo_snapshot))
                   THEN 100 ELSE 0 END;
    UPDATE public.respuesta_alumno SET calificacion_final=v_valor, revisada_manual=false
      WHERE id=v_row.id;
    INSERT INTO public.historial_calificacion(respuesta_alumno_id,calificacion_anterior,calificacion_nueva,modificado_por,motivo)
    VALUES (v_row.id,NULL,v_valor,v_docente,'Calificacion automatica por coincidencia exacta');
    v_count := v_count+1;
  END LOOP;
  RETURN v_count;
END;
$fn$;

-- Resultado visible para alumno SOLO segun la configuracion del docente.
CREATE OR REPLACE FUNCTION public.fn_resultado_alumno(p_token uuid)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $fn$
DECLARE v_exam bigint; v_intento bigint; v_estado text; v_visible text; v_escala text;
        v_total integer; v_calificadas integer; v_nota numeric;
BEGIN
  SELECT i.id,i.examen_id,i.estado,e.resultado_visible,e.escala
  INTO v_intento,v_exam,v_estado,v_visible,v_escala
  FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
  WHERE i.token_sesion=p_token;
  IF v_intento IS NULL OR v_estado<>'finalizado' OR v_visible='oculto' THEN
    RAISE EXCEPTION 'Resultado no disponible' USING ERRCODE='P0001';
  END IF;
  SELECT count(*) INTO v_total FROM public.examen_pregunta ep
    JOIN public.apartado a ON a.id=ep.apartado_id WHERE a.examen_id=v_exam;
  SELECT count(*),COALESCE(sum(r.calificacion_final * ep.ponderacion / 100),0)
    INTO v_calificadas,v_nota
  FROM public.respuesta_alumno r
  JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
  JOIN public.apartado a ON a.id=ep.apartado_id
  WHERE r.intento_id=v_intento AND a.examen_id=v_exam AND r.calificacion_final IS NOT NULL;
  IF v_visible='final' AND v_calificadas<>v_total THEN
    RAISE EXCEPTION 'Resultado final pendiente de revision' USING ERRCODE='P0001';
  END IF;
  IF v_escala='0-10' THEN v_nota:=v_nota/10; END IF;
  RETURN pg_catalog.jsonb_build_object('intento_id',v_intento,'escala',v_escala,
    'estado',CASE WHEN v_calificadas=v_total THEN 'final' ELSE 'aproximado' END,
    'calificacion',pg_catalog.round(v_nota,2),
    'calificadas',v_calificadas,'total_preguntas',v_total);
END;
$fn$;

REVOKE ALL ON FUNCTION public.fn_iniciar_intento(bigint,bigint,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_iniciar_intento(bigint,bigint,text) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_preguntas_intento(uuid) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_preguntas_intento(uuid) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_finalizar_intento(uuid) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_finalizar_intento(uuid) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_registrar_incidencia(uuid,text,text,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_registrar_incidencia(uuid,text,text,text) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_calificar_respuesta(bigint,numeric,text) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_calificar_respuesta(bigint,numeric,text) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_autocalificar_intento(bigint) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_autocalificar_intento(bigint) TO laravel_app;
REVOKE ALL ON FUNCTION public.fn_resultado_alumno(uuid) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_resultado_alumno(uuid) TO laravel_app;
COMMIT;
