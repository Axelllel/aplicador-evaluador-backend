# HTML de las pantallas para html.to.design

Once archivos HTML autocontenidos (CSS, logo e íconos incrustados, sin scripts), generados el 9 nov 2026 desde la rama `Leonardo-staging` con datos de prueba locales.

| Archivo | Pantalla |
|---|---|
| A1-ingreso-folio | Alumno: ingreso con folio |
| A2-inicio | Alumno: inicio |
| A3-calificaciones | Alumno: calificaciones |
| D1-login | Docente: inicio de sesión |
| D2-panel | Docente: panel |
| D3-grupos | Docente: grupos |
| D4-banco-preguntas | Docente: banco de preguntas |
| D5-examenes | Docente: exámenes |
| D6-suscripcion | Docente: suscripción |
| D7-resultados | Docente: resultados |
| D8-estadisticas | Docente: estadísticas |

## Cómo importarlos
1. En Figma instala el plugin **html.to.design** (comunidad) y ábrelo en un archivo de diseño.
2. Elige la opción de importar desde **archivo HTML** (o pega el código) y selecciona uno de estos archivos.
3. Ancho recomendado: **1440** para escritorio y **390** para móvil. Las pantallas son responsivas, así que el mismo archivo sirve para ambos.
4. Revisa los límites del plan gratuito del plugin antes de importar los once.

## Avisos
- Las fuentes DM Sans y Manrope se piden a Google Fonts; hace falta internet al importar (en Figma ambas están disponibles).
- Los enlaces apuntan a `127.0.0.1:8000`; no afectan al diseño.
- No incluye la pantalla del examen (requiere un pase del docente) ni el modo oscuro.
- Para regenerarlos: `node html-figma.mjs` con las variables `CAP_SALIDA`, `CAP_CORREO`, `CAP_CLAVE` y `CAP_FOLIO` (el script está en el scratchpad de la sesión; se puede copiar a esta carpeta).
