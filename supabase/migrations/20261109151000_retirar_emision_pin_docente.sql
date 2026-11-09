-- El sistema asigna el PIN al crear el alumno. Los PIN existentes siguen siendo válidos.
DROP FUNCTION IF EXISTS public.fn_asignar_credencial_alumno(bigint, bigint, text);
