-- Lenguaje de las preguntas de programación (por ejemplo Python, SQL o «Libre»).
-- Es opcional para no afectar las preguntas existentes y solo aplica al tipo programacion.
BEGIN;

ALTER TABLE public.pregunta
    ADD COLUMN IF NOT EXISTS lenguaje VARCHAR(40);

ALTER TABLE public.pregunta
    DROP CONSTRAINT IF EXISTS pregunta_lenguaje_check;

ALTER TABLE public.pregunta
    ADD CONSTRAINT pregunta_lenguaje_check
    CHECK (lenguaje IS NULL OR (tipo = 'programacion' AND btrim(lenguaje) <> ''));

COMMENT ON COLUMN public.pregunta.lenguaje IS
    'Lenguaje esperado en la respuesta de una pregunta de programacion; «Libre» deja elegirlo al alumno.';

COMMIT;
