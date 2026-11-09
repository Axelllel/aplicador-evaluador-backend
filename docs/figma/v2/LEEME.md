# Paquete de diseño v2 (estilo del portal del alumno)

Referencia visual del estilo nuevo, tomada de la rama `staging` el 9 nov 2026. Sustituye a `../tokens.json` (COD-WEB 6.14) como referencia de colores y tipografía; `../capturas`, `../iconos` y `../logo.png` siguen vigentes.

| Carpeta o archivo | Contenido |
|---|---|
| `tokens-v2-portal.json` | Colores claro y oscuro, DM Sans y Manrope, tamaños y radios |
| `capturas/` | 22 capturas PNG (alumno y docente, escritorio 1440 y móvil 390) |
| `html/` | 11 HTML autocontenidos para importar con el plugin html.to.design de Figma (ver `html/LEEME.md`) |
| `scripts/` | Scripts para regenerar capturas y HTML con Edge (variables `CAP_SALIDA`, `CAP_CORREO`, `CAP_CLAVE`, `CAP_FOLIO`) |

Los datos de las capturas son de prueba locales. Las capturas no incluyen la pantalla del examen (requiere un pase del docente) ni el modo oscuro.

Avisos abiertos: el gris `#8490A3` da cerca de 3:1 sobre blanco (COD-WEB pide 4.5:1) y las fuentes se piden a Google Fonts (COD-WEB 6.15 pide no cargar fuentes externas).
