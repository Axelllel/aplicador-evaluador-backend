-- Revocación inmediata en el gateway; Supabase conserva la responsabilidad de validar JWT.
BEGIN;
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
