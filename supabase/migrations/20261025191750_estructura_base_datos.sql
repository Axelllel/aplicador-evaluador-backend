-- ============================================================
-- BIGBITBYTE
-- SISTEMA APLICADOR / EVALUADOR DE EXAMENES
-- BASE DE DATOS - VERSION 5.3
-- ESQUEMA INICIAL: 17 TABLAS
-- POSTGRESQL / SUPABASE
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;


-- ============================================================
-- 1. PAQUETE
-- ============================================================

CREATE TABLE paquete (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    max_grupos INTEGER NOT NULL,
    max_preguntas INTEGER NOT NULL,
    max_alumnos INTEGER NOT NULL,
    precio NUMERIC(10,2) NOT NULL
);


-- ============================================================
-- 2. DOCENTE
-- ============================================================

CREATE TABLE docente (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(150) UNIQUE NOT NULL,
    telefono VARCHAR(20),
    paquete_id BIGINT REFERENCES paquete(id) ON DELETE RESTRICT,
    estado_pago VARCHAR(20) NOT NULL DEFAULT 'inactivo'
);


-- ============================================================
-- 3. PAGO
-- ============================================================

CREATE TABLE pago (
    id BIGSERIAL PRIMARY KEY,
    docente_id UUID NOT NULL REFERENCES docente(id) ON DELETE RESTRICT,
    paquete_id BIGINT NOT NULL REFERENCES paquete(id) ON DELETE RESTRICT,
    fecha TIMESTAMPTZ NOT NULL DEFAULT now(),
    monto NUMERIC(10,2) NOT NULL,
    estado VARCHAR(20) NOT NULL,
    fecha_inicio_vigencia TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_fin_vigencia TIMESTAMPTZ NOT NULL,
    renovacion_automatica BOOLEAN NOT NULL DEFAULT false,

    CHECK (fecha_fin_vigencia > fecha_inicio_vigencia)
);


-- ============================================================
-- 4. GRUPO
-- ============================================================

CREATE TABLE grupo (
    id BIGSERIAL PRIMARY KEY,
    docente_id UUID NOT NULL REFERENCES docente(id) ON DELETE CASCADE,
    materia VARCHAR(150) NOT NULL,
    folio_codigo VARCHAR(20) UNIQUE NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT true
);


-- ============================================================
-- 5. ALUMNO
-- ============================================================

CREATE TABLE alumno (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    telefono VARCHAR(20)
);


-- ============================================================
-- 6. ALUMNO_GRUPO
-- ============================================================

CREATE TABLE alumno_grupo (
    alumno_id BIGINT NOT NULL REFERENCES alumno(id) ON DELETE CASCADE,
    grupo_id BIGINT NOT NULL REFERENCES grupo(id) ON DELETE CASCADE,
    fecha_union TIMESTAMPTZ NOT NULL DEFAULT now(),

    PRIMARY KEY (alumno_id, grupo_id)
);


-- ============================================================
-- 7. PREGUNTA
-- ============================================================

CREATE TABLE pregunta (
    id BIGSERIAL PRIMARY KEY,
    docente_id UUID NOT NULL REFERENCES docente(id) ON DELETE CASCADE,
    tipo VARCHAR(30) NOT NULL CHECK (
        tipo IN (
            'opcion_multiple',
            'relacion_columnas',
            'abierta',
            'programacion'
        )
    ),
    enunciado TEXT NOT NULL,
    respuesta_modelo TEXT,
    opciones JSONB
);


-- ============================================================
-- 8. EXAMEN
-- ============================================================

CREATE TABLE examen (
    id BIGSERIAL PRIMARY KEY,
    grupo_id BIGINT NOT NULL REFERENCES grupo(id) ON DELETE CASCADE,
    nombre VARCHAR(150) NOT NULL,
    fecha_apertura TIMESTAMPTZ NOT NULL,
    fecha_cierre TIMESTAMPTZ NOT NULL,

    orden VARCHAR(20) NOT NULL CHECK (
        orden IN ('aleatorio', 'fijo')
    ),

    escala VARCHAR(10) NOT NULL CHECK (
        escala IN ('0-10', '0-100')
    ),

    plataforma_permitida VARCHAR(20) NOT NULL CHECK (
        plataforma_permitida IN ('web', 'movil', 'ambas')
    ),

    publicado BOOLEAN NOT NULL DEFAULT false,

    resultado_visible VARCHAR(20) NOT NULL DEFAULT 'oculto' CHECK (
        resultado_visible IN ('final', 'aproximado', 'oculto')
    ),

    CHECK (fecha_cierre > fecha_apertura)
);


-- ============================================================
-- 9. APARTADO
-- ============================================================

CREATE TABLE apartado (
    id BIGSERIAL PRIMARY KEY,
    examen_id BIGINT NOT NULL REFERENCES examen(id) ON DELETE CASCADE,
    tipo VARCHAR(30) NOT NULL,
    cantidad_preguntas INTEGER NOT NULL CHECK (cantidad_preguntas > 0)
);


-- ============================================================
-- 10. EXAMEN_PREGUNTA
-- ============================================================

CREATE TABLE examen_pregunta (
    id BIGSERIAL PRIMARY KEY,
    apartado_id BIGINT NOT NULL REFERENCES apartado(id) ON DELETE CASCADE,
    pregunta_id BIGINT NOT NULL REFERENCES pregunta(id) ON DELETE RESTRICT,
    tiempo_segundos INTEGER NOT NULL,
    ponderacion NUMERIC(5,2) NOT NULL,
    respuesta_modelo_snapshot TEXT
);


-- ============================================================
-- 11. INTENTO_EXAMEN
-- ============================================================

CREATE TABLE intento_examen (
    id BIGSERIAL PRIMARY KEY,
    examen_id BIGINT NOT NULL REFERENCES examen(id) ON DELETE CASCADE,
    alumno_id BIGINT NOT NULL REFERENCES alumno(id) ON DELETE CASCADE,
    numero_intento INTEGER NOT NULL DEFAULT 1,
    token_sesion UUID NOT NULL DEFAULT gen_random_uuid(),
    fecha_inicio TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_entrega TIMESTAMPTZ,

    estado VARCHAR(20) NOT NULL DEFAULT 'en_progreso' CHECK (
        estado IN ('en_progreso', 'finalizado', 'anulado')
    ),

    UNIQUE (examen_id, alumno_id, numero_intento),
    UNIQUE (token_sesion)
);


-- ============================================================
-- 12. RESPUESTA_ALUMNO
-- ============================================================

CREATE TABLE respuesta_alumno (
    id BIGSERIAL PRIMARY KEY,
    intento_id BIGINT NOT NULL REFERENCES intento_examen(id) ON DELETE CASCADE,
    examen_pregunta_id BIGINT NOT NULL REFERENCES examen_pregunta(id) ON DELETE RESTRICT,
    respuesta_texto TEXT,
    puntaje_ia NUMERIC(5,2),
    retroalimentacion_ia JSONB,
    score_confianza NUMERIC(4,3),
    tokens_consumidos INTEGER,
    calificacion_final NUMERIC(5,2),
    revisada_manual BOOLEAN NOT NULL DEFAULT false,

    UNIQUE (intento_id, examen_pregunta_id)
);


-- ============================================================
-- 13. INCIDENCIA_PROCTORING
-- ============================================================

CREATE TABLE incidencia_proctoring (
    id BIGSERIAL PRIMARY KEY,
    intento_id BIGINT NOT NULL REFERENCES intento_examen(id) ON DELETE CASCADE,
    tipo_alerta VARCHAR(50) NOT NULL,
    evidencia_url TEXT,
    fecha_hora TIMESTAMPTZ NOT NULL DEFAULT now(),

    nivel_severidad VARCHAR(20) NOT NULL DEFAULT 'baja' CHECK (
        nivel_severidad IN ('baja', 'media', 'alta')
    )
);


-- ============================================================
-- 14. HISTORIAL_CALIFICACION
-- ============================================================

CREATE TABLE historial_calificacion (
    id BIGSERIAL PRIMARY KEY,
    respuesta_alumno_id BIGINT NOT NULL REFERENCES respuesta_alumno(id) ON DELETE RESTRICT,
    calificacion_anterior NUMERIC(5,2),
    calificacion_nueva NUMERIC(5,2) NOT NULL,
    modificado_por UUID REFERENCES docente(id),
    fecha_modificacion TIMESTAMPTZ NOT NULL DEFAULT now(),
    motivo TEXT
);


-- ============================================================
-- 15. EVALUACION
-- ============================================================

CREATE TABLE evaluacion (
    id BIGSERIAL PRIMARY KEY,
    grupo_id BIGINT NOT NULL REFERENCES grupo(id) ON DELETE CASCADE,
    nombre VARCHAR(100) NOT NULL,
    numero INTEGER NOT NULL
);


-- ============================================================
-- 16. RUBRO_EVALUACION
-- ============================================================

CREATE TABLE rubro_evaluacion (
    id BIGSERIAL PRIMARY KEY,
    evaluacion_id BIGINT NOT NULL REFERENCES evaluacion(id) ON DELETE CASCADE,
    tipo VARCHAR(50) NOT NULL,

    porcentaje NUMERIC(5,2) NOT NULL CHECK (
        porcentaje BETWEEN 0 AND 100
    )
);


-- ============================================================
-- 17. CALIFICACION_RUBRO
-- ============================================================

CREATE TABLE calificacion_rubro (
    id BIGSERIAL PRIMARY KEY,
    rubro_id BIGINT NOT NULL REFERENCES rubro_evaluacion(id) ON DELETE CASCADE,
    alumno_id BIGINT NOT NULL REFERENCES alumno(id) ON DELETE CASCADE,
    valor NUMERIC(5,2) NOT NULL,

    UNIQUE (rubro_id, alumno_id)
);


-- ============================================================
-- FUNCION: VALIDAR SUMA DE PORCENTAJES
-- ============================================================
-- Los rubros de una evaluacion deben sumar 100.
-- La validacion se realiza al final de la transaccion.
-- ============================================================

CREATE OR REPLACE FUNCTION trg_validar_suma_porcentajes()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_evaluacion_id BIGINT;
    v_evaluacion_anterior BIGINT;
    suma NUMERIC;
BEGIN

    IF TG_OP = 'DELETE' THEN
        v_evaluacion_id := OLD.evaluacion_id;
    ELSE
        v_evaluacion_id := NEW.evaluacion_id;
    END IF;

    -- Validar la evaluacion afectada si aun existe.
    IF EXISTS (
        SELECT 1
        FROM evaluacion
        WHERE id = v_evaluacion_id
    ) THEN

        SELECT COALESCE(SUM(porcentaje), 0)
        INTO suma
        FROM rubro_evaluacion
        WHERE evaluacion_id = v_evaluacion_id;

        IF suma <> 100 THEN
            RAISE EXCEPTION
                'Los rubros de la evaluacion % suman %, deben sumar 100',
                v_evaluacion_id,
                suma;
        END IF;

    END IF;

    -- Si un rubro cambia de evaluacion, validar tambien
    -- la evaluacion de origen.
    IF TG_OP = 'UPDATE' THEN

        v_evaluacion_anterior := OLD.evaluacion_id;

        IF v_evaluacion_anterior IS DISTINCT FROM v_evaluacion_id
           AND EXISTS (
               SELECT 1
               FROM evaluacion
               WHERE id = v_evaluacion_anterior
           )
        THEN

            SELECT COALESCE(SUM(porcentaje), 0)
            INTO suma
            FROM rubro_evaluacion
            WHERE evaluacion_id = v_evaluacion_anterior;

            IF suma <> 100 THEN
                RAISE EXCEPTION
                    'Los rubros de la evaluacion % suman %, deben sumar 100',
                    v_evaluacion_anterior,
                    suma;
            END IF;

        END IF;

    END IF;

    RETURN NULL;

END;
$$;


-- ============================================================
-- CONSTRAINT TRIGGER
-- ============================================================

CREATE CONSTRAINT TRIGGER validar_suma_porcentajes
AFTER INSERT OR UPDATE OR DELETE
ON rubro_evaluacion
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW
EXECUTE FUNCTION trg_validar_suma_porcentajes();