# BigBitByte – lote de backend 2026-10-30

Paquete incremental: **no cambia ninguna de las 17 tablas**. Basado en el repositorio `Axelllel/aplicador-evaluador-backend`, rama `main` (12 rutas previas). Se agregan seis controladores nuevos, rutas y una migración de funciones. No contiene `.env`, secretos ni credenciales. No incluye despliegue.

## Instalación única en Ubuntu (sin aplicar todavía la migración)

1. Colocar este archivo TAR.GZ en la raíz `~/proyectos/bigbitbyte-backend` desde VS Code/Explorador.
2. En Ubuntu:

```bash
cd ~/proyectos/bigbitbyte-backend
cp routes/api.php routes/api.php.antes-lote.bak
tar -xzf bigbitbyte_lote_backend_20261030.tar.gz
```

> Se sobreescribe únicamente `routes/api.php`. Los controladores nuevos tienen nombres distintos a los 7 originales. **No ejecutar `php artisan migrate`, `migrate:fresh` ni `supabase db reset`**.

## Fase de integración y pruebas (solo cuando se acuerde)

1. Revisar los cambios: `git status --short`, `git diff -- routes/api.php`; inspeccionar el SQL.
2. Hacer respaldo de PostgreSQL y confirmar conexión correcta.
3. Aplicar la única migración nueva mediante el flujo habitual de Supabase CLI: `npx supabase migration up --local` (este paso **sí cambia la base** y debe hacerse después del respaldo).
4. Reiniciar Laravel si su configuración/rutas están cacheadas y ejecutar `./vendor/bin/sail artisan route:list --path=api`.
5. Integrar con cliente web/Flutter y ejecutar pruebas completas de roles, expiración, RLS, e intentos.

## Flujo general

- Docente crea/pública examen usando rutas originales. Estudiante se inscribe por folio mediante `/v1/grupos/unirse` (NO otorga acceso a exámenes).
- Docente autenticado consulta alumnos `GET /api/v1/teacher/groups/{grupoId}/students`.
- Docente emite **pase cifrado individual** `POST /api/v1/teacher/exams/{examenId}/students/{alumnoId}/grant`. Caduca en 30 minutos o al cierre del examen. **Debe entregarse privadamente** al alumno verificado presencialmente o por un canal de identidad confiable.
- Alumno inicia con `POST /api/v1/student/attempts/start`, JSON `{ "pase":"...", "plataforma":"web" }`. Solo en ventana publicada y para alumno inscrito. El pase no sustituye una identidad verificada por el docente.
- Alumno consulta preguntas con `POST /api/v1/student/attempts/questions`, JSON `{ "token_sesion":"UUID" }`. Nunca se envía la respuesta modelo.
- Alumno responde con `POST /api/v1/student/attempts/answers`, JSON `{ "token_sesion":"UUID", "examen_pregunta_id":4, "respuesta_texto":"4" }`.
- Alumno finaliza con `POST /api/v1/student/attempts/finish`, JSON `{ "token_sesion":"UUID" }`. Una vez finalizado la función existente rechaza respuestas nuevas.
- Incidencia con `POST /api/v1/student/attempts/incidents`, JSON `{ "token_sesion":"UUID", "tipo_alerta":"cambio_pestana", "nivel_severidad":"baja" }`. URL HTTPS opcional; **NO hay carga de imágenes ni proctoring por cámara implementados**.
- Resultado alumno con `POST /api/v1/student/attempts/result`, JSON `{ "token_sesion":"UUID" }` (depende de `resultado_visible` del examen).
- Docente revisa `GET /api/v1/teacher/exams/{examenId}/attempts`, `GET /api/v1/teacher/attempts/{attemptId}/answers`, `GET /api/v1/teacher/attempts/{attemptId}/incidents`.
- Opción múltiple: `POST /api/v1/teacher/attempts/{attemptId}/auto-grade` (solo coincidencia EXACTA entre texto de respuesta y snapshot). No se usa IA ni calificación automática para abiertas, programación o relación de columnas.
- Corrección manual: `PATCH /api/v1/teacher/answers/{answerId}/grade`, JSON `{ "calificacion":80, "motivo":"Revision de criterio" }`. Si ya existía nota, **motivo obligatorio**; la modificación se registra en historial.
- Resultados docente: `GET /api/v1/teacher/exams/{examenId}/results`; nota `null` mientras falten preguntas por calificar. La nota pondera `ponderacion`, en escala 0-10 o 0-100.
- Evaluaciones/rubros: `GET/POST /api/v1/teacher/groups/{grupoId}/evaluations`; creación JSON `{ "nombre":"Parcial 1", "numero":1, "rubros":[{"tipo":"examen","porcentaje":70},{"tipo":"tareas","porcentaje":30}] }`. Nota por rubro: `POST /api/v1/teacher/rubrics/{rubroId}/grades`, JSON `{ "alumno_id":3, "valor":90 }`.
- Cuentas: `POST /api/v1/auth/register` (JSON `nombre,email,password,telefono?`), `POST /api/v1/auth/recover` (`email`). Requieren configuración Supabase Auth SMTP/verificación para correos reales. Cuenta nueva queda `inactivo`, sin plan ni pagos.
- Catálogo y pagos: `GET /api/v1/teacher/plans`, `GET /api/v1/teacher/payments`; **no se crean ni confirman cobros** sin integración con pasarela externa.

## Límites y decisiones pendientes

- Ningún token de folio/nombre equivale a identidad. El docente debe entregar el pase individual al estudiante correcto. Un pase robado antes de usarse puede permitir suplantación. En producción requiere canal seguro de entrega y validaciones antifraude adicionales.
- Ingreso permite **un intento por alumno y examen**. Reintentos requieren una regla aprobada y una nueva función; no se implementan en esta entrega.
- Para opción múltiple, `respuesta_modelo` y `respuesta_texto` deben contener el mismo identificador de alternativa (por ejemplo `A`). Hay que unificar estructura del JSON `opciones` con web/móvil.
- **No implementado**: evaluador IA externo, procesamiento real de evidencias multimedia, streaming/monitor de cámara, pasarela de pago real, roles superadmin, recuperación de registros parciales de signup, reportes exportables, despliegue público. Requieren reglas/servicios externos.
- No se ejecutaron pruebas funcionales de integración en la base del usuario. Antes de producción hacen falta pruebas de RLS, concurrencia, seguridad de pases, calibración de notas y limitación de tasas por usuario.
- Recomendación de seguridad: HTTPS en producción y no registrar tokens/pases en logs ni URLs.

## Revertir solo los archivos nuevos

Antes de aplicar la migración: restaurar `routes/api.php.antes-lote.bak`, y eliminar únicamente los controladores listados en el paquete; las tablas originales quedan intactas. Si la migración ya se aplicó, no improvisar un rollback SQL destructivo: revisar primero las dependencias y tener respaldo.
