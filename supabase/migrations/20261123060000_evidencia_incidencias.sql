BEGIN;
ALTER TABLE public.incidencia_proctoring ADD COLUMN IF NOT EXISTS evidencia_detalle jsonb NOT NULL DEFAULT '{}'::jsonb;
CREATE OR REPLACE FUNCTION public.fn_registrar_incidencia_evidencia(p_token uuid,p_tipo text,p_severidad text,p_url text,p_detalle jsonb)
RETURNS bigint LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, public AS $$
DECLARE v_id bigint;
BEGIN
    IF p_detalle IS NULL OR jsonb_typeof(p_detalle) <> 'object' OR octet_length(p_detalle::text)>2048
       OR EXISTS(SELECT 1 FROM jsonb_object_keys(p_detalle) AS key WHERE key NOT IN ('tecnologia','rostros','duracion_segundos','confianza')) THEN
        RAISE EXCEPTION 'EVIDENCIA_INVALIDA' USING ERRCODE='22023';
    END IF;
    v_id:=public.fn_registrar_incidencia(p_token,p_tipo,p_severidad,p_url);
    UPDATE public.incidencia_proctoring SET evidencia_detalle=p_detalle WHERE id=v_id;
    RETURN v_id;
END $$;
REVOKE ALL ON FUNCTION public.fn_registrar_incidencia_evidencia(uuid,text,text,text,jsonb) FROM PUBLIC,anon,authenticated;
GRANT EXECUTE ON FUNCTION public.fn_registrar_incidencia_evidencia(uuid,text,text,text,jsonb) TO laravel_app;
COMMIT;
