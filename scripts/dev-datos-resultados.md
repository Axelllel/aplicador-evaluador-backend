# Datos de prueba para Resultados y Estadísticas (solo desarrollo local)

Crea alumnos, intentos, respuestas e incidencias en el examen publicado «Parcial de prueba» (examen 1, grupo 1)
usando el **flujo oficial de la API** (unirse por folio, pase del docente, iniciar, responder, entregar), no SQL.
Solo la preparación del examen y la ventana de fechas usan SQL, porque el examen ya estaba publicado.

Requisitos: Sail y Supabase local en marcha, el docente de `docs/dev-credenciales.local.md` activo
(`scripts/dev-activar-docente.sql`) y `python3` y `curl` en Ubuntu/WSL. La contraseña se lee del archivo local y nunca se imprime.

## 1. Preparar el examen (SQL como `postgres`)

El examen solo tenía una pregunta de opción múltiple. Se agrega un apartado de pregunta abierta (50 % y 50 %) y se
**abre la ventana temporalmente**, porque `fn_iniciar_intento` exige `CURRENT_TIMESTAMP` entre apertura y cierre.

```sql
BEGIN;
INSERT INTO apartado(examen_id, tipo, cantidad_preguntas)
SELECT 1, 'abierta', 1 WHERE NOT EXISTS (SELECT 1 FROM apartado WHERE examen_id = 1 AND tipo = 'abierta');
INSERT INTO examen_pregunta(apartado_id, pregunta_id, tiempo_segundos, ponderacion, respuesta_modelo_snapshot)
SELECT a.id, 2, 180, 50, p.respuesta_modelo FROM apartado a, pregunta p
WHERE a.examen_id = 1 AND a.tipo = 'abierta' AND p.id = 2
  AND NOT EXISTS (SELECT 1 FROM examen_pregunta WHERE apartado_id = a.id);
UPDATE examen_pregunta SET ponderacion = 50 WHERE id = 1;
UPDATE examen SET fecha_apertura = now() - interval '1 hour', fecha_cierre = now() + interval '3 hours' WHERE id = 1;
COMMIT;
```

```bash
docker exec -i supabase_db_bigbitbyte-backend psql -U postgres -d postgres < preparar.sql
```

## 2. Flujo del alumno por la API (curl)

Pasos por alumno: `POST /api/v1/grupos/unirse` (folio y nombre) → `POST /api/v1/teacher/exams/1/students/{alumno}/grant`
(token del docente; devuelve el pase cifrado) → `POST /api/v1/student/attempts/start` (pase y plataforma) →
`/questions` → `/answers` (una por pregunta) → `/incidents` (opcional) → `/finish`.

> **Límite compartido:** los `throttle:N,1` de la API usan **un solo contador por IP** (login, unirse y el flujo del
> alumno lo comparten). Tras un alumno hay que esperar ~60 s antes de unir al siguiente; el script lo hace solo.

El script es idempotente (omite a quien ya tiene intento). Casos que crea: Ana (entrega todo), Beto (entrega con
2 incidencias), Carla (entrega, sin calificar), Diego (no entrega: «En progreso») y Elena (entrega sin responder
la pregunta abierta).

```bash
#!/usr/bin/env bash
set -u
cd ~/proyectos/bigbitbyte-backend

B=http://localhost:8000/api/v1
PSQL="docker exec -i supabase_db_bigbitbyte-backend psql -U postgres -d postgres -tA"
EMAIL=$(grep -i 'Correo' docs/dev-credenciales.local.md | sed -E 's/.*: *//' | tr -d '\r ')
PASS=$(grep -i 'Contrasena' docs/dev-credenciales.local.md | sed -E 's/.*: *//' | tr -d '\r')

j() { python3 -c "import sys,json; d=json.load(sys.stdin); print(eval(sys.argv[1]))" "$1"; }
post() { curl -s -X POST "$B$1" -H 'Content-Type: application/json' -H 'Accept: application/json' ${3:+-H "Authorization: Bearer $3"} -d "$2"; }
json() { python3 -c 'import json,sys; print(json.dumps(dict(zip(sys.argv[1::2], sys.argv[2::2]))))' "$@"; }

DT=$(post /auth/login "$(json email "$EMAIL" password "$PASS")" | j "d['access_token']")
FOLIO=$($PSQL -c "select folio_codigo from grupo where id=1")

alumno() {  # nombre, respuesta de opción múltiple, respuesta abierta, finalizar (1/0), incidencias (1/0)
  local NOM="$1" MC="$2" AB="$3" FIN="$4" INC="$5" AID
  AID=$($PSQL -c "select id from alumno where nombre='$NOM'")
  if [ -n "$AID" ] && [ -n "$($PSQL -c "select 1 from intento_examen where examen_id=1 and alumno_id=$AID")" ]; then
    echo "$NOM ya tiene intento; se omite."; return
  fi
  if [ -z "$AID" ]; then
    sleep 62
    AID=$(post /grupos/unirse "$(json folio_codigo "$FOLIO" nombre "$NOM")" | j "d['data']['alumno_id']")
  fi
  PASE=$(post /teacher/exams/1/students/$AID/grant '{}' "$DT" | j "d['data']['pase']")
  R=$(post /student/attempts/start "$(json pase "$PASE" plataforma web)")
  ST=$(echo "$R" | j "d['data']['token_sesion']")
  EPS=$(post /student/attempts/questions "$(json token_sesion "$ST")" | j "' '.join(str(p['examen_pregunta_id'])+':'+p['tipo'] for p in d['data']['preguntas'])")
  for P in $EPS; do
    EP=${P%%:*}; TIPO=${P##*:}
    if [ "$TIPO" = "opcion_multiple" ]; then TXT="$MC"; else TXT="$AB"; fi
    [ -n "$TXT" ] && post /student/attempts/answers "$(python3 -c 'import json,sys; print(json.dumps({"token_sesion":sys.argv[1],"examen_pregunta_id":int(sys.argv[2]),"respuesta_texto":sys.argv[3]}))' "$ST" "$EP" "$TXT")" > /dev/null
  done
  if [ "$INC" = "1" ]; then
    post /student/attempts/incidents "$(json token_sesion "$ST" tipo_alerta cambio_pestana nivel_severidad media)" > /dev/null
    post /student/attempts/incidents "$(json token_sesion "$ST" tipo_alerta salida_pantalla nivel_severidad alta)" > /dev/null
  fi
  [ "$FIN" = "1" ] && post /student/attempts/finish "$(json token_sesion "$ST")" > /dev/null
}

alumno "Ana Torres" "París" "Las plantas usan la luz del sol para convertir agua y dióxido de carbono en glucosa y oxígeno." 1 0
alumno "Beto Luna" "Londres" "Es cuando las plantas respiran." 1 1
alumno "Carla Ríos" "París" "Es el proceso donde las plantas convierten luz en energía química." 1 0
alumno "Diego Soto" "Roma" "" 0 0
alumno "Elena Vega" "París" "" 1 0
```

> Si el script se genera desde PowerShell, escríbelo como UTF-8 sin BOM y con LF; si no, los acentos se corrompen
> (así apareció un alumno «Carla RÃ­os» en un primer intento, que se borró y se repitió).

## 3. Calificación inicial por la API (docente)

Con el token del docente (`POST /api/v1/auth/login`):

```text
POST  /api/v1/teacher/attempts/{1,2,6}/auto-grade     → {"preguntas_calificadas":1,"metodo":"coincidencia_exacta_opcion_multiple"}
PATCH /api/v1/teacher/answers/{respuesta abierta de Ana}/grade   {"calificacion":90}
PATCH /api/v1/teacher/answers/{respuesta abierta de Beto}/grade  {"calificacion":40}
```

Queda Carla sin calificar (para probar el panel), Diego en progreso y Elena pendiente para siempre
(no respondió la pregunta abierta, así que nunca existe la fila que habría que calificar).

## 4. Restaurar la ventana del examen

```sql
UPDATE examen SET fecha_apertura = '2026-11-08 08:00:00+00', fecha_cierre = '2026-11-08 10:00:00+00' WHERE id = 1;
```

## Resultado esperado tras calificar a Carla desde el panel (70, luego 80 con motivo)

| Intento | Alumno | Estado | Nota (0 a 10) |
|---|---|---|---|
| 1 | Ana Torres | Finalizado | 9.5 |
| 2 | Beto Luna | Finalizado | 2 |
| 4 | Diego Soto | En progreso | Sin entregar |
| 5 | Carla Ríos | Finalizado | 9 |
| 6 | Elena Vega | Finalizado | Pendiente de calificar |

Estadísticas: 3 calificados, promedio 6.83 (68.33 %), mínimo 2, máximo 9.5, aprobación 66.67 %, distribución
(0–2, 2–4, 4–6, 6–8, 8–10) = 0, 1, 0, 0, 2; errores por pregunta: abierta 30 %, opción múltiple 25 %.
