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

## Reanudación móvil por PIN y respuestas guardadas — 22/11/2026

El flujo móvil actual no necesita cookies ni un pase cifrado:

1. Enviar `POST /api/v1/student/exams/{examenId}/start` con `folio_codigo` y `codigo_acceso`. Si el intento sigue abierto, devuelve HTTP 200, `reanudado: true` y conserva `intento_id`, `token_sesion` y `fecha_inicio`. Un intento nuevo devuelve HTTP 201.
2. Con ese `token_sesion`, enviar `POST /api/v1/student/attempts/questions` al abrir o reabrir el examen. Devuelve `data.examen_id`, `data.nombre`, `data.preguntas` y `data.respuestas`.
3. Restaurar cada campo usando `data.respuestas[examen_pregunta_id.toString()]`. Las claves corresponden a la asociación del reactivo con el examen, no al ID del banco ni a la posición de la tarjeta. Los valores conservan exactamente el texto guardado por `/answers`, incluso código o selecciones serializadas.

Ejemplo del contenido de la respuesta (los IDs y el texto son ilustrativos):

```json
{
  "data": {
    "examen_id": 1,
    "nombre": "Examen",
    "preguntas": [
      {"examen_pregunta_id": 4, "apartado_id": 2, "tipo": "abierta", "enunciado": "Define variable.", "opciones": null, "tiempo_segundos": 60}
    ],
    "respuestas": {"4": "Un valor que puede cambiar."}
  }
}
```

Sin respuestas, `respuestas` es un objeto vacío `{}`. La lectura no escribe ni finaliza el intento, no reinicia el reloj ni cambia el orden para su token. Devuelve solo respuestas del intento autorizado; nunca devuelve modelos de corrección, respuestas de otros alumnos ni calificaciones reservadas. La app debe guardar cada cambio mediante `/answers` y esperar su confirmación; esta consulta recupera lo persistido en el servidor.

Esta corrección de API reutiliza `fn_preguntas_portal_alumno(uuid)`, ya creada en `20261109120000_revision_y_resultados_portal_alumno.sql`. No agrega una migración. Si esa función aún no está instalada en un ambiente, la API devuelve HTTP 503 con `code: MIGRATION_REQUIRED` y debe aplicarse esa migración existente. Las sesiones inexistentes, cerradas o fuera de disponibilidad mantienen su rechazo.

## Límites y decisiones pendientes del lote original

- Ningún token de folio/nombre equivale a identidad. El docente debe entregar el pase individual al estudiante correcto. Un pase robado antes de usarse puede permitir suplantación. En producción requiere canal seguro de entrega y validaciones antifraude adicionales.
- Ingreso permite **un intento por alumno y examen**. Reintentos requieren una regla aprobada y una nueva función; no se implementan en esta entrega.
- Para opción múltiple, `respuesta_modelo` y `respuesta_texto` deben contener el mismo identificador de alternativa (por ejemplo `A`). Hay que unificar estructura del JSON `opciones` con web/móvil.
- **No implementado**: evaluador IA externo, procesamiento real de evidencias multimedia, streaming/monitor de cámara, pasarela de pago real, roles superadmin, recuperación de registros parciales de signup, reportes exportables, despliegue público. Requieren reglas/servicios externos.
- No se ejecutaron pruebas funcionales de integración en la base del usuario. Antes de producción hacen falta pruebas de RLS, concurrencia, seguridad de pases, calibración de notas y limitación de tasas por usuario.
- Recomendación de seguridad: HTTPS en producción y no registrar tokens/pases en logs ni URLs.

## Revertir solo los archivos nuevos

Antes de aplicar la migración: restaurar `routes/api.php.antes-lote.bak`, y eliminar únicamente los controladores listados en el paquete; las tablas originales quedan intactas. Si la migración ya se aplicó, no improvisar un rollback SQL destructivo: revisar primero las dependencias y tener respaldo.


---

## Agregados de integración — 22/11/2026

Actualización: 22/11/2026. Backend Laravel, web Blade y API compartida con Flutter. Los pagos continúan siendo de prueba: activan capacidades reales en la cuenta y no realizan un cobro bancario.

### Despliegue del lote

1. Respaldar y usar una ventana sin exámenes activos para cambiar el contrato de navegación del alumno.
2. Como alternativa de una sola ejecución, usar `scripts/completar-requisitos.sql` completo: reúne los seis archivos en una transacción y termina con una consulta cuyos indicadores deben ser todos `true`. No aplicar ambos métodos. Para aplicarlos por separado, ejecutar completos y en este orden los seis archivos SQL nuevos en el proyecto Supabase correspondiente:
   - `supabase/migrations/20261123010000_control_tiempo_intentos.sql`.
   - `supabase/migrations/20261123020000_recuperacion_pin_controlada.sql`.
   - `supabase/migrations/20261123030000_calificacion_en_segundo_plano.sql`.
   - `supabase/migrations/20261123040000_administracion_controlada.sql`.
   - `supabase/migrations/20261123050000_revocacion_sesiones_docente.sql`.
   - `supabase/migrations/20261123060000_evidencia_incidencias.sql`.
3. Publicar esta versión de Laravel junto con los assets compilados. `scripts/start-container.sh` mantiene Apache y el planificador; el Dockerfile lo utiliza. En otro entorno ejecutar `php artisan schedule:work` como servicio supervisado, o configurar el cron de Laravel. El contenedor usa caché de archivos para los bloqueos del planificador; si se cambia a caché de BD, deben existir sus tablas y permisos. El proceso debe permanecer activo; un alojamiento que se suspenda necesita un worker/cron independiente.
4. Conservar v1 para la app Flutter actual e integrar v2 cuando se habilite su control individual de tiempo. La web de este commit ya utiliza v2; al cambiar de cliente, un intento activado en v2 requiere un cliente que lo soporte.
5. Verificar con cuentas de prueba: crear y reanudar un intento, conservar respuestas, recorrer preguntas, agotar tiempo, entregar, consultar propuesta de IA y publicar la revisión docente. La integración en el proyecto Supabase real requiere esta comprobación después de aplicar SQL; las pruebas automatizadas usan bases desechables.

No se ejecutan migraciones automáticamente al iniciar el contenedor. No se concede administración ni se crea un operador de soporte por defecto. Una migración aplicada se corrige mediante otra migración; no se eliminan tablas para volver a una versión anterior.

### Alumno: identidad, reanudación y tiempo

El móvil usa solicitudes JSON; no necesita cookies. La API v1 conserva las preguntas completas, las respuestas guardadas y el guardado existente con límite global. Se agrega el contrato v2 para el tiempo por pregunta. Una vez activado v2 para un intento, no se puede volver a v1 para eludir sus límites; el servidor responde `UPGRADE_REQUIRED`.

Rutas del nuevo flujo:

- `POST /api/v1/grupos/unirse`: alta o reingreso por `folio_codigo` y `codigo_acceso`. El alta entrega un código aleatorio de cuatro letras y dos números una sola vez. El código se protege con HMAC y un secreto del servidor.
- `POST /api/v1/student/exams/{examenId}/start`: recibe `folio_codigo` y `codigo_acceso`; devuelve 201 para un intento nuevo y 200 para uno reanudado, conservando su ID, token y fecha de inicio.
- `POST /api/v2/student/attempts/questions`: recibe `token_sesion`; devuelve el índice de preguntas, `respuestas`, `tiempo`, `version_contrato: 2` y `requiere_abrir_pregunta: true`. En v2 el índice contiene IDs, tipo y duración; la consigna y las opciones se obtienen al abrir el reactivo, para fijar su primer acceso real.
- `POST /api/v2/student/attempts/open-question`: enviar `token_sesion` y `examen_pregunta_id` antes de mostrar el reactivo. Devuelve `data.pregunta` con su contenido, `primer_acceso`, `pregunta_fecha_limite`, `fecha_limite`, `servidor_ahora`, `estado` y `puede_responder`. Reabrir no reinicia el tiempo. Las parejas correctas de relación de columnas se convierten en listas independientes antes de enviarlas al alumno.
- `POST /api/v2/student/attempts/answers`: enviar `token_sesion`, `examen_pregunta_id` y `respuesta_texto`. Esperar la confirmación antes de marcar una respuesta como guardada. El servidor usa `fn_registrar_respuesta_temporizada`; la función heredada no se invoca desde esta versión de la API.
- `POST /api/v2/student/attempts/time`: sincroniza el reloj del servidor y finaliza un intento cuyo límite global haya vencido.
- `POST /api/v2/student/attempts/finish`: entrega idempotente; preserva la primera fecha de entrega y genera una sola evidencia de cierre.
- `POST /api/v2/student/attempts/result`: respeta la publicación elegida por el docente: oculto, aproximado o final.
- `POST /api/v2/student/attempts/incidents`: registra `cambio_pestana`, `salida_pantalla`, `conexion`, `rostro_no_detectado`, `multiples_rostros`, `camara_desactivada`, `movimiento` u `otro`, con severidad baja/media/alta y evidencia HTTPS opcional. La web registra pérdida de foco y salida de pantalla completa; una incidencia no anula automáticamente un examen.

Restaurar los valores con `data.respuestas[examen_pregunta_id.toString()]`. Estas claves corresponden a la asociación de la pregunta con el examen, no al ID del banco ni a su posición. Sin respuestas se devuelve `{}`. Los códigos, textos y selecciones serializadas se conservan exactamente como fueron guardados.

El límite global es el menor entre el cierre del examen y el inicio más la suma de tiempos. Cada pregunta vence en el menor entre ese límite y su primer acceso más su duración. El servidor acepta la recepción estrictamente anterior al límite: exactamente al vencimiento ya se rechaza. Desconectarse no detiene el reloj. Los errores 409 `QUESTION_NOT_OPEN`, `QUESTION_EXPIRED`, `ATTEMPT_EXPIRED` o `ATTEMPT_CLOSED` permiten distinguir el motivo sin mostrar SQL ni secretos.

`php artisan examenes:cerrar-vencidos` ejecuta el cierre por lotes. El planificador lo llama cada diez segundos. El estado se materializa en esa ejecución; la fecha de entrega automática corresponde al límite lógico. Aunque el planificador esté detenido, los endpoints de tiempo/preguntas y el guardado rechazan las operaciones tardías.

### Recuperación del PIN

Implementa PR-BD-PIN-01. Administración debe registrar explícitamente dos identidades Supabase del personal en `operador_soporte`, con funciones `verificador` y `autorizador`. Deben tener un registro de docente para usar la autenticación actual de la API. El cliente no elige esos roles ni aporta la identidad del operador.

1. Verificar por un medio institucional independiente la identidad, la inscripción y el canal de entrega. Nombre, folio de grupo o captura de pantalla no bastan.
2. El verificador autenticado envía `POST /api/v1/support/pin-recovery` con `alumno_id`, `folio_caso`, `referencia_identidad`, `referencia_canal`, `motivo` e `identidad_verificada: true`. Las referencias identifican evidencias controladas por la institución; no deben contener el PIN ni copias innecesarias de documentos personales.
3. Otro operador con función de autorizador envía `POST /api/v1/support/pin-recovery/{casoId}/authorize`. El servidor genera el nuevo código, sustituye su HMAC, registra la revocación y emisión, aumenta la versión de credencial y rota todos los tokens anteriores del alumno en una transacción. No crea otro alumno.
4. Entregar el código devuelto una sola vez por el canal validado. Si se pierde la respuesta, tramitar otro caso; no se puede consultar el PIN en claro posteriormente.

Las inscripciones, respuestas, intentos terminados y calificaciones conservan sus IDs. Las sesiones web previas son invalidadas en su siguiente petición; los tokens de intentos anteriores dejan de autorizar consultas. Los HMAC de códigos emitidos quedan reservados para impedir su reutilización futura, incluso después de revocarlos.

### IA en segundo plano

Configurar `GROQ_API_KEY` solo en el servidor. `AI_AUTOMATIC_GRADING=true` habilita propuestas automáticas; `AI_ENABLED=false` suspende las consultas externas. El proveedor y los límites se configuran en `config/ai.php`; la generación y la revisión manual existentes conservan sus endpoints.

Una entrega o cierre automático encola en la misma transacción la calificación objetiva y las respuestas abiertas/de programación. `php artisan examenes:procesar-calificaciones` procesa lotes acotados; el planificador lo ejecuta cada minuto. La cola persiste en PostgreSQL, usa reservas exclusivas, recupera tareas interrumpidas y limita reintentos. Se conservan las cuotas, el control de consumo, la huella del contenido y la idempotencia del servicio de IA. Un límite de consumo pospone la tarea; no convierte la propuesta en una nota final.

El docente ve el estado pendiente, en proceso o fallido. Puede calificar manualmente. Una propuesta de IA se muestra al alumno solo como aproximación cuando el docente la habilita y las respuestas necesarias tienen evaluación vigente. La publicación final requiere revisión; las preguntas sin contestar cuentan cero.

### Administración, reportes y seguridad

La tabla `administrador_sistema` autoriza identidades concretas por asignación administrativa de BD. No se otorga `super_admin` desde un formulario, metadatos editables ni parámetros de URL. El login devuelve el rol autorizado y la web lo conserva en la sesión del servidor.

- `GET /api/v1/admin/teachers` y `GET /api/v1/admin/payments`.
- `PATCH /api/v1/admin/teachers/{uuid}`: `suspender` booleano y `motivo` de al menos diez caracteres.
- `PATCH /api/v1/admin/payments/{id}`: estado `pendiente`, `confirmado` o `rechazado`, con `motivo`.
- Web: `/administracion/docentes` y `/administracion/pagos`. Suspender bloquea los endpoints del docente, incluida la activación de planes. Rehabilitar no regala vigencia: se comprueba un pago confirmado vigente. Los cambios administrativos generan auditoría.
- `GET /api/v1/teacher/groups/{groupId}/analytics`: estadísticas del grupo propio.
- `GET /api/v1/teacher/exams/{examId}/results/export/{pdf|xlsx}`: archivo autorizado, sin caché. La web incluye botones en Resultados. Excel trata los nombres como texto, nunca como fórmulas.

La calificación consolidada y las exportaciones usan los mismos resultados y las mismas restricciones de propiedad. Las respuestas pendientes de revisión no se publican como notas definitivas. Las consultas agregadas evitan una consulta por alumno.

### Segundo factor del docente

El flujo está implementado con los endpoints oficiales de Supabase Auth. La API valida el token con Supabase antes de usar su nivel AAL. Un factor verificado exige AAL2 para acceder a los datos del docente; las rutas MFA permiten completar la verificación con AAL1.

- `GET /api/v1/auth/mfa`.
- `POST /api/v1/auth/mfa/enroll`: `telefono` en formato internacional, por ejemplo `+52...`.
- `POST /api/v1/auth/mfa/{factorId}/challenge`.
- `POST /api/v1/auth/mfa/{factorId}/verify`: `challenge_id` y `codigo`.
- `DELETE /api/v1/auth/mfa/{factorId}`: requiere AAL2.

La web incluye Seguridad de la cuenta, la captura del código y avisos de envío/error. Primero configurar Phone MFA y el proveedor SMS en Supabase; después activar `SUPABASE_MFA_PHONE_ENABLED=true`, lo que exige el segundo factor a los docentes que aún no lo tengan. No activar la obligatoriedad antes de probar el envío y la recepción reales. La implementación local no configura ni acredita saldo al proveedor externo.

Referencia técnica: https://supabase.com/docs/guides/auth/auth-mfa/phone

### Respaldos y restauración

`php artisan bd:respaldar` usa una conexión de operación independiente (`BACKUP_DATABASE_URL`), `pg_dump`/`pg_dumpall` compatibles con el servidor, destino externo montado (`BACKUP_DIRECTORY`) y una clave independiente `BACKUP_KEY_FILE`. Generar 32 bytes aleatorios, guardar sus 64 dígitos hexadecimales en un archivo 0600 y custodiarlo fuera del respaldo. Conservar la clave mientras existan copias que la requieran. Se utiliza Sodium secretstream con cifrado autenticado, sin cargar el archivo completo en memoria.

La copia incluye esquemas public/auth/extensions, roles sin contraseñas, rutinas, RLS, políticas y permisos. Las huellas de las respuestas y la extracción usan la misma instantánea PostgreSQL. Se cifra el archivo, los roles y el manifiesto; solo una copia terminada recibe el marcador `.complete`. Retención: treinta días. `BACKUP_ENABLED=true` programa su ejecución diaria a las 23:00 de Ciudad de México, siempre que el proceso disponga de las herramientas, conexión de operación y destino. Estas credenciales no se usan en las solicitudes de la API.

`php artisan bd:verificar-restauracion /ruta/al/respaldo` exige `BACKUP_RESTORE_DATABASE_URL` hacia una base vacía con prefijo `bbb_restore_`. Restaura en una transacción, comprueba integridad, compara respuestas completas mediante sus huellas, rutinas, tablas/RLS, políticas, permisos e índices, y registra la duración de la prueba. Rechaza un destino con objetos. Los roles globales se revisan y aprovisionan por separado; la herramienta no ejecuta automáticamente cambios de roles en el servidor. La clave de cifrado y las dependencias externas se requieren también para recuperar.

La prueba automatizada realiza una copia cifrada, elimina las respuestas del origen desechable y recupera su contenido en otra BD. Esto acredita la recuperación de esa instantánea y su duración local. **No acredita RPO cero para respuestas confirmadas después de la copia**, ni el RTO del servicio real. Esos compromisos necesitan el mecanismo continuo de infraestructura y un simulacro en el entorno contratado. Los archivos externos y la configuración del despliegue requieren respaldo separado; no se incluyen en un dump SQL.

### Validación reproducible

En una instancia PostgreSQL desechable en 127.0.0.1, con administrador local de pruebas y clientes PostgreSQL instalados:

```bash
composer install
npm install
BBB_TEST_PG_PORT=55440 BBB_TEST_PG_BIN=/ruta/a/clientes/postgresql/bin bash scripts/test-backend.sh
npm run build
```

El script ejecuta también las pruebas de BD, cuotas de IA, permisos, PIN, tiempos y restauración. Los nombres de BD de pruebas son fijos y desechables. No apuntar sus variables a un proyecto real. Sin esas variables, PHPUnit omite las pruebas que requieren PostgreSQL; ese resultado no acredita la integración SQL.

### Dependencias externas que requieren cierre operativo

- Aplicar el lote SQL y comprobar el despliegue web/API en el Supabase y hosting del proyecto.
- Configurar SMS/MFA, comprobar SMTP y mantener activos el planificador y el procesamiento de trabajos.
- Asignar administradores y dos operadores de soporte autorizados, con referencias institucionales de verificación.
- Configurar custodia, almacenamiento externo, respaldo continuo y simulacro real para validar RPO/RTO.
- Elegir e integrar el SDK de detección facial/movimiento antes de afirmar que la cámara ofrece supervisión automática. La captura de foco y la recepción de incidencias ya funcionan; no constituyen detección de rostros.
- Flutter puede conservar v1; para utilizar control individual de tiempo debe integrar v2 y sus errores. Este repositorio contiene la API y la web, no modifica el cliente Flutter.

La pasarela bancaria real sigue fuera del alcance acordado: los pagos son simulados y las capacidades adquiridas sí se registran en la base.


### Cierre de sesión y supervisión añadidos

La implementación de cámara descrita aquí amplía el alcance inicial registrado al comienzo de este archivo. MediaPipe Tasks Vision 0.10.32 y BlazeFace short range se ejecutan localmente en un worker con permiso explícito de cámara. El modelo tiene versión y SHA-256 fijados; el build comprueba su integridad y copia WASM/modelo dentro de `public/build/proctoring`. No depende de un CDN durante el examen. No se solicita micrófono ni se transmiten fotografías. La API admite evidencia descriptiva validada (tecnología, número de rostros, duración y confianza), visible solo para el docente propietario. No identifica personas ni demuestra fraude. Una detección no anula el intento. La revisión es humana.

El cliente registra cambios de pestaña, salida de pantalla completa, desconexión, cámara desactivada y ausencia/múltiples rostros persistentes. Agrupa eventos del mismo tipo durante treinta segundos y reintenta el envío mientras permanezca abierto el examen. La denegación o falta de cámara se informa claramente. Las pruebas de navegador usan cámara sintética y no acreditan precisión biométrica sobre personas reales.

`POST /api/v1/auth/logout` requiere Bearer. Revoca inmediatamente el hash del token y el `session_id` en el gateway y solicita a Supabase cerrar la sesión actual. La respuesta distingue la revocación local y la confirmación del proveedor. Otro JWT de la misma sesión queda bloqueado; un nuevo login independiente conserva acceso. La web utiliza este endpoint al salir. No se persiste el JWT en claro en la tabla de revocación.

### Pruebas por cada PR

`.github/workflows/backend-tests.yml` instala PHP 8.4, PostgreSQL 18 y Node 22. Ejecuta la suite con bases desechables, restauración cifrada real, pruebas de navegador y JavaScript, auditoría de dependencias y cobertura mínima del 70 % en **todos los servicios y modelos**, alcance del COD-WEB 11.4. Conserva Clover/JUnit y capturas como artifacts. `phpunit.coverage.xml` mantiene intacta la configuración de la suite normal y explicita ese alcance.

Comprobación local de este lote: 325 pruebas PHP, 4432 aserciones y 89.21 % de líneas ejecutables cubiertas en servicios y modelos. El pipeline vuelve a medir en cada PR. El administrador del repositorio debe seleccionar el estado `Pruebas y cobertura 70` como requisito de merge en las reglas de `staging` y `main`; crear el workflow no modifica por sí solo la protección de ramas.

La capa `resources/css/ui-standards.css` agrega fuentes del sistema, títulos 24/20 px, cuerpo 16 px, texto secundario 14 px, controles de al menos 44 px y geometría adaptable. La paleta se conserva. No se descargan fuentes externas. Las pruebas de navegador revisan anchuras 1366/768/375 y ampliación tipográfica al 200 %.

### Activación en el servicio existente

1. Aplicar `scripts/completar-requisitos.sql` completo en SQL Editor; todos los indicadores finales deben ser verdaderos. Este lote agrega seis migraciones y no borra alumnos, respuestas ni tablas originales.
2. Aplicar `scripts/activar-administrador.sql` después de sustituir `TU_CORREO`; una cuenta docente existente debe coincidir exactamente.
3. Para Render Free, habilitar pg_cron y aplicar `scripts/activar-cierre-supabase.sql`; la fecha límite es aplicada por el backend aunque el cierre materializado ocurra en el siguiente minuto. El procesamiento con IA espera un proceso Laravel activo; si Render se suspende, las tareas permanecen en cola hasta que vuelva a estar activo.
4. Conservar `APP_KEY`, `STUDENT_PIN_PEPPER`, `SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY`, las credenciales de BD y la clave de IA actuales. Añadir solo los valores de `scripts/activacion.env.example` que falten.
5. Integrar este PR en staging y esperar el deploy Live de Render. El nuevo Dockerfile compila el modelo y arranca el planificador. En una shell del servicio, `bash scripts/comprobar-activacion.sh` permite comprobar rutas y procesar un lote real. No iniciar un segundo `schedule:work` si ya lo ejecuta el contenedor.
6. Volver a iniciar sesión para obtener el rol de administrador. Verificar con cuentas de prueba los permisos y una entrega completa.

**Segundo factor telefónico:** Supabase Advanced MFA Phone no está incluido en Free y requiere un complemento pagado además del proveedor SMS. No se activa ni contrata desde este código. Mantener `SUPABASE_MFA_PHONE_ENABLED=false` hasta que el responsable habilite y pruebe ese servicio. Referencias oficiales: https://supabase.com/docs/guides/auth/auth-mfa/phone y https://supabase.com/docs/guides/platform/manage-your-usage/advanced-mfa-phone.
