-- =====================================================================
-- BIGBITBYTE | MANUAL DE BASE DE DATOS v5.3
-- RLS - FASE 2: LAS OTRAS 14 TABLAS DEL MODELO
-- Requiere las migraciones hasta 20261026214000 (fase 1).
-- No altera columnas, claves ni registros. En producción, revisar y
-- probar en staging antes de ejecutar.
-- =====================================================================
BEGIN;

-- Cerrar acceso directo de clientes a estas tablas (defensa adicional
-- a la migracion 20261026213000_restringir_acceso_directo.sql).
REVOKE ALL PRIVILEGES ON
  public.paquete, public.pago, public.alumno, public.alumno_grupo,
  public.examen, public.apartado, public.examen_pregunta,
  public.intento_examen, public.respuesta_alumno,
  public.incidencia_proctoring, public.historial_calificacion,
  public.evaluacion, public.rubro_evaluacion, public.calificacion_rubro
FROM PUBLIC, anon, authenticated;

-- El backend no debe ejecutar INSERT/UPDATE/DELETE directo sobre datos
-- sensibles que requieren funciones o procedimientos especializados.
-- La funcion SECURITY DEFINER fn_registrar_respuesta sigue siendo
-- ejecutable por laravel_app; su propietario conserva acceso interno.
REVOKE INSERT, UPDATE, DELETE ON
  public.paquete, public.pago, public.alumno, public.alumno_grupo,
  public.intento_examen, public.respuesta_alumno,
  public.incidencia_proctoring, public.historial_calificacion
FROM laravel_app;

-- Se permite quitar a un alumno de un grupo propio. El alta de alumnos,
-- vinculacion, apertura/cierre de intentos, incidencias, correccion y
-- auditoria requeriran funciones o endpoints revisados posteriormente.
GRANT DELETE ON public.alumno_grupo TO laravel_app;

-- Las 14 tablas; no usar FORCE: fn_registrar_respuesta es una funcion
-- SECURITY DEFINER, cuyo propietario necesita poder aplicar sus validaciones.
ALTER TABLE public.paquete ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pago ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alumno ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alumno_grupo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.examen ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.apartado ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.examen_pregunta ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.intento_examen ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.respuesta_alumno ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.incidencia_proctoring ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.historial_calificacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.evaluacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.rubro_evaluacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.calificacion_rubro ENABLE ROW LEVEL SECURITY;

-- 1/14 PAQUETE: catalogo compartido, solo lectura por la API Laravel.
CREATE POLICY bbb_paquete_select ON public.paquete
FOR SELECT TO laravel_app USING (true);

-- 2/14 PAGO: docente puede consultar sus pagos, no crearlos o aprobarlos.
CREATE POLICY bbb_pago_select ON public.pago
FOR SELECT TO laravel_app
USING (docente_id = (SELECT public.app_docente_id()));

-- 3/14 ALUMNO: un mismo alumno puede figurar en varios grupos;
-- solo se muestra al docente con al menos una vinculacion propia.
-- Sin INSERT/UPDATE/DELETE directos por ahora.
CREATE POLICY bbb_alumno_select ON public.alumno
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.alumno_grupo ag
  JOIN public.grupo g ON g.id = ag.grupo_id
  WHERE ag.alumno_id = public.alumno.id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 4/14 ALUMNO_GRUPO: cada docente solo ve/desvincula los suyos.
CREATE POLICY bbb_alumno_grupo_select ON public.alumno_grupo
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.alumno_grupo.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
));
CREATE POLICY bbb_alumno_grupo_delete ON public.alumno_grupo
FOR DELETE TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.alumno_grupo.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 5/14 EXAMEN: propiedad derivada del grupo.
CREATE POLICY bbb_examen_docente ON public.examen
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.examen.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.examen.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 6/14 APARTADO: propiedad derivada del examen y grupo.
CREATE POLICY bbb_apartado_docente ON public.apartado
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.examen e
  JOIN public.grupo g ON g.id = e.grupo_id
  WHERE e.id = public.apartado.examen_id
    AND g.docente_id = (SELECT public.app_docente_id())
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.examen e
  JOIN public.grupo g ON g.id = e.grupo_id
  WHERE e.id = public.apartado.examen_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 7/14 EXAMEN_PREGUNTA: tanto el examen como la pregunta deben
-- ser propiedad del mismo docente; evita mezclar bancos de preguntas.
CREATE POLICY bbb_examen_pregunta_docente ON public.examen_pregunta
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.apartado a
  JOIN public.examen e ON e.id = a.examen_id
  JOIN public.grupo g ON g.id = e.grupo_id
  JOIN public.pregunta p ON p.id = public.examen_pregunta.pregunta_id
  WHERE a.id = public.examen_pregunta.apartado_id
    AND g.docente_id = (SELECT public.app_docente_id())
    AND p.docente_id = g.docente_id
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.apartado a
  JOIN public.examen e ON e.id = a.examen_id
  JOIN public.grupo g ON g.id = e.grupo_id
  JOIN public.pregunta p ON p.id = public.examen_pregunta.pregunta_id
  WHERE a.id = public.examen_pregunta.apartado_id
    AND g.docente_id = (SELECT public.app_docente_id())
    AND p.docente_id = g.docente_id
));

-- 8/14 INTENTO_EXAMEN: docente puede consultar intentos propios.
-- Alta, entrega y anulacion necesitaran flujo validado; sin escritura
-- directa desde laravel_app ni desde clientes.
CREATE POLICY bbb_intento_examen_select ON public.intento_examen
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.examen e
  JOIN public.grupo g ON g.id = e.grupo_id
  WHERE e.id = public.intento_examen.examen_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 9/14 RESPUESTA_ALUMNO: solo leer respuestas del examen propio.
-- La escritura del alumno se hace SOLO via fn_registrar_respuesta.
-- No se concede actualizacion de notas hasta crear auditoria segura.
CREATE POLICY bbb_respuesta_alumno_select ON public.respuesta_alumno
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.intento_examen i
  JOIN public.examen e ON e.id = i.examen_id
  JOIN public.grupo g ON g.id = e.grupo_id
  JOIN public.examen_pregunta ep
    ON ep.id = public.respuesta_alumno.examen_pregunta_id
  JOIN public.apartado a ON a.id = ep.apartado_id
  WHERE i.id = public.respuesta_alumno.intento_id
    AND a.examen_id = e.id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 10/14 INCIDENCIA_PROCTORING: solo lectura docente; alta alumno
-- por funcion autenticada con token pendiente de implementar.
CREATE POLICY bbb_incidencia_proctoring_select ON public.incidencia_proctoring
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.intento_examen i
  JOIN public.examen e ON e.id = i.examen_id
  JOIN public.grupo g ON g.id = e.grupo_id
  WHERE i.id = public.incidencia_proctoring.intento_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 11/14 HISTORIAL_CALIFICACION: solo lectura y solo del grupo propio.
-- Insercion se habilitara con trigger de auditoria/control de calificacion.
CREATE POLICY bbb_historial_calificacion_select ON public.historial_calificacion
FOR SELECT TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.respuesta_alumno r
  JOIN public.intento_examen i ON i.id = r.intento_id
  JOIN public.examen e ON e.id = i.examen_id
  JOIN public.grupo g ON g.id = e.grupo_id
  WHERE r.id = public.historial_calificacion.respuesta_alumno_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 12/14 EVALUACION: propiedad del grupo.
CREATE POLICY bbb_evaluacion_docente ON public.evaluacion
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.evaluacion.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.grupo g
  WHERE g.id = public.evaluacion.grupo_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 13/14 RUBRO_EVALUACION: propiedad derivada de evaluacion.
CREATE POLICY bbb_rubro_evaluacion_docente ON public.rubro_evaluacion
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.evaluacion ev
  JOIN public.grupo g ON g.id = ev.grupo_id
  WHERE ev.id = public.rubro_evaluacion.evaluacion_id
    AND g.docente_id = (SELECT public.app_docente_id())
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.evaluacion ev
  JOIN public.grupo g ON g.id = ev.grupo_id
  WHERE ev.id = public.rubro_evaluacion.evaluacion_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

-- 14/14 CALIFICACION_RUBRO: grupo propio y alumno perteneciente a ese
-- grupo, para impedir calificar alumnos ajenos a la evaluacion.
CREATE POLICY bbb_calificacion_rubro_docente ON public.calificacion_rubro
FOR ALL TO laravel_app
USING (EXISTS (
  SELECT 1 FROM public.rubro_evaluacion r
  JOIN public.evaluacion ev ON ev.id = r.evaluacion_id
  JOIN public.grupo g ON g.id = ev.grupo_id
  JOIN public.alumno_grupo ag
    ON ag.grupo_id = g.id
   AND ag.alumno_id = public.calificacion_rubro.alumno_id
  WHERE r.id = public.calificacion_rubro.rubro_id
    AND g.docente_id = (SELECT public.app_docente_id())
))
WITH CHECK (EXISTS (
  SELECT 1 FROM public.rubro_evaluacion r
  JOIN public.evaluacion ev ON ev.id = r.evaluacion_id
  JOIN public.grupo g ON g.id = ev.grupo_id
  JOIN public.alumno_grupo ag
    ON ag.grupo_id = g.id
   AND ag.alumno_id = public.calificacion_rubro.alumno_id
  WHERE r.id = public.calificacion_rubro.rubro_id
    AND g.docente_id = (SELECT public.app_docente_id())
));

COMMIT;
-- IMPORTANTE: RLS no reemplaza las Policies/validaciones del backend.
-- Aun faltan ingreso alumno por folio/QR, creacion de intento, cierre,
-- devolucion segura de preguntas (sin respuestas modelo), auditoria de notas,
-- pago/super_admin y pruebas de acceso cruzado + escritura en staging.
