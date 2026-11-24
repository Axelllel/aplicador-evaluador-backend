-- Calificación objetiva de columnas y recuperación explícita de entregas anteriores.
-- Ejecutar completo. No cambia notas revisadas ni envía consultas de IA históricas por sí solo.
BEGIN;
CREATE OR REPLACE FUNCTION public.fn_puntaje_relacion_columnas(p_respuesta text,p_modelo text,p_opciones jsonb)
RETURNS numeric LANGUAGE plpgsql IMMUTABLE SET search_path='' AS $fn$
DECLARE v_valores jsonb; v_par jsonb; v_indice bigint; v_lineas text[]; v_linea text;
 v_prefijo text; v_correcta text; v_elegida text; v_coincidencias integer;
 v_total integer; v_aciertos integer:=0; v_izquierdas text[]:=ARRAY[]::text[];
BEGIN
 IF jsonb_typeof(p_opciones) IS DISTINCT FROM 'array' THEN RETURN NULL; END IF;
 v_total:=jsonb_array_length(p_opciones);
 IF v_total<2 OR v_total>10 OR p_modelo IS NULL THEN RETURN NULL; END IF;
 SELECT array_agg(btrim(l)) INTO v_lineas FROM regexp_split_to_table(p_modelo,E'\r?\n') l WHERE btrim(l)<>'';
 IF cardinality(v_lineas) IS DISTINCT FROM v_total THEN RETURN NULL; END IF;
 BEGIN v_valores:=p_respuesta::jsonb; EXCEPTION WHEN invalid_text_representation THEN RETURN NULL; END;
 IF jsonb_typeof(v_valores) NOT IN ('array','object') OR v_valores IS NULL THEN RETURN NULL; END IF;
 IF jsonb_typeof(v_valores)='array' AND jsonb_array_length(v_valores)>v_total THEN RETURN NULL; END IF;
 FOR v_par,v_indice IN SELECT value,ordinality FROM jsonb_array_elements(p_opciones) WITH ORDINALITY LOOP
  IF jsonb_typeof(v_par->'izquierda') IS DISTINCT FROM 'string' THEN RETURN NULL; END IF;
  IF btrim(v_par->>'izquierda')=ANY(v_izquierdas) THEN RETURN NULL; END IF;
  v_izquierdas:=array_append(v_izquierdas,btrim(v_par->>'izquierda'));
  v_prefijo:=btrim(v_par->>'izquierda')||' = ';
  v_coincidencias:=0; v_correcta:=NULL;
  FOREACH v_linea IN ARRAY v_lineas LOOP
   IF left(v_linea,length(v_prefijo))=v_prefijo THEN
    v_coincidencias:=v_coincidencias+1; v_correcta:=btrim(substr(v_linea,length(v_prefijo)+1));
   END IF;
  END LOOP;
  IF v_coincidencias<>1 OR coalesce(v_correcta,'')='' THEN RETURN NULL; END IF;
  IF jsonb_typeof(v_valores)='array' THEN
   IF v_valores->(v_indice::integer-1) IS NOT NULL AND jsonb_typeof(v_valores->(v_indice::integer-1)) NOT IN ('string','null') THEN RETURN NULL; END IF;
   v_elegida:=v_valores->>(v_indice::integer-1);
  ELSE
   IF v_valores->(v_par->>'izquierda') IS NOT NULL AND jsonb_typeof(v_valores->(v_par->>'izquierda')) NOT IN ('string','null') THEN RETURN NULL; END IF;
   v_elegida:=v_valores->>(v_par->>'izquierda');
  END IF;
  IF lower(btrim(v_elegida))=lower(v_correcta) THEN v_aciertos:=v_aciertos+1; END IF;
 END LOOP;
 RETURN round(100.0*v_aciertos/v_total,2);
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_puntaje_relacion_columnas(text,text,jsonb) FROM PUBLIC,anon,authenticated,laravel_app;

CREATE OR REPLACE FUNCTION public.fn_autocalificar_intento(p_intento bigint)
RETURNS integer LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_docente uuid:=public.app_docente_id(); v_estado text; v_count integer:=0; v_row record; v_valor numeric;
BEGIN
 SELECT i.estado INTO v_estado FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
 JOIN public.grupo g ON g.id=e.grupo_id WHERE i.id=p_intento AND g.docente_id=v_docente;
 IF NOT FOUND OR v_estado<>'finalizado' THEN RAISE EXCEPTION 'Intento no disponible' USING ERRCODE='P0001'; END IF;
 FOR v_row IN SELECT r.id,r.respuesta_texto,ep.respuesta_modelo_snapshot,p.tipo,p.opciones
 FROM public.respuesta_alumno r JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id
 JOIN public.apartado a ON a.id=ep.apartado_id JOIN public.intento_examen i ON i.id=r.intento_id AND i.examen_id=a.examen_id
 JOIN public.pregunta p ON p.id=ep.pregunta_id
 WHERE r.intento_id=p_intento AND p.tipo IN ('opcion_multiple','relacion_columnas') AND r.calificacion_final IS NULL
 FOR UPDATE OF r LOOP
  IF v_row.tipo='opcion_multiple' THEN
   v_valor:=CASE WHEN lower(btrim(v_row.respuesta_texto))=lower(btrim(v_row.respuesta_modelo_snapshot)) THEN 100 ELSE 0 END;
  ELSE
   v_valor:=public.fn_puntaje_relacion_columnas(v_row.respuesta_texto,v_row.respuesta_modelo_snapshot,v_row.opciones);
  END IF;
  -- Un formato histórico desconocido requiere revisión, nunca una nota inventada.
  IF v_valor IS NULL THEN CONTINUE; END IF;
  UPDATE public.respuesta_alumno SET calificacion_final=v_valor,revisada_manual=false WHERE id=v_row.id;
  INSERT INTO public.historial_calificacion(respuesta_alumno_id,calificacion_anterior,calificacion_nueva,modificado_por,motivo)
  VALUES(v_row.id,NULL,v_valor,v_docente,CASE WHEN v_row.tipo='relacion_columnas'
   THEN 'Calificacion automatica proporcional por pares correctos' ELSE 'Calificacion automatica por coincidencia exacta' END);
  v_count:=v_count+1;
 END LOOP;
 RETURN v_count;
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_autocalificar_intento(bigint) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_autocalificar_intento(bigint) TO laravel_app;

CREATE OR REPLACE FUNCTION public.fn_preparar_calificacion_intento(p_intento bigint)
RETURNS integer LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $fn$
DECLARE v_estado text; v_count integer; v_ia integer;
BEGIN
 SELECT i.estado INTO v_estado FROM public.intento_examen i JOIN public.examen e ON e.id=i.examen_id
 JOIN public.grupo g ON g.id=e.grupo_id
 WHERE i.id=p_intento AND g.docente_id=public.app_docente_id() FOR UPDATE OF i;
 IF NOT FOUND OR v_estado<>'finalizado' THEN RAISE EXCEPTION 'Intento no disponible' USING ERRCODE='P0001'; END IF;
 INSERT INTO public.trabajo_calificacion(intento_id,tipo,clave) VALUES(p_intento,'objetivas','intento:'||p_intento)
 ON CONFLICT(clave) DO UPDATE SET estado='pendiente',intentos=0,disponible_en=clock_timestamp(),ultimo_error=NULL
 WHERE trabajo_calificacion.estado IN ('completo','fallido');
 GET DIAGNOSTICS v_count=ROW_COUNT;
 INSERT INTO public.trabajo_calificacion(intento_id,respuesta_id,tipo,clave)
 SELECT p_intento,r.id,'ia','respuesta:'||r.id FROM public.respuesta_alumno r
 JOIN public.examen_pregunta ep ON ep.id=r.examen_pregunta_id JOIN public.pregunta p ON p.id=ep.pregunta_id
 WHERE r.intento_id=p_intento AND p.tipo IN ('abierta','programacion') AND r.calificacion_final IS NULL
 AND (r.retroalimentacion_ia IS NULL OR r.retroalimentacion_ia->>'huella_contenido' IS DISTINCT FROM
 md5(jsonb_build_array(p.tipo,p.enunciado,ep.respuesta_modelo_snapshot,r.respuesta_texto)::text))
 ON CONFLICT(clave) DO UPDATE SET estado='pendiente',intentos=0,disponible_en=clock_timestamp(),ultimo_error=NULL
 WHERE trabajo_calificacion.estado IN ('completo','fallido');
 GET DIAGNOSTICS v_ia=ROW_COUNT;
 RETURN v_count+v_ia;
END; $fn$;
REVOKE ALL ON FUNCTION public.fn_preparar_calificacion_intento(bigint) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_preparar_calificacion_intento(bigint) TO laravel_app;
COMMIT;

SELECT to_regprocedure('public.fn_preparar_calificacion_intento(bigint)') IS NOT NULL AS recuperacion_calificacion,
 position('fn_puntaje_relacion_columnas' in pg_get_functiondef('public.fn_autocalificar_intento(bigint)'::regprocedure))>0 AS columnas_automaticas;
