# Paquete para pasar el panel web a Figma

Sistema Aplicador / Evaluador de Exámenes · BigBitByte · panel web del docente.
Fuente de verdad visual: Manual del Código Web (BBB-MAN-COD-WEB v1.3) §6.14 a §6.19 y Manual de Calidad (BBB-MAN-CAL v2.2) §2.

## Qué contiene esta carpeta

| Carpeta o archivo | Para qué sirve |
|---|---|
| `tokens.json` | Colores, tipografía, espaciado, radios y tamaños. Se importa con el plugin Tokens Studio, o se crean las variables a mano. |
| `iconos/` | 29 íconos Heroicons (20 × 20, SVG, licencia MIT). Se pegan o arrastran a Figma. |
| `capturas/` | Capturas reales de cada pantalla en escritorio (1440 px) y móvil (390 px), con datos reales del backend. |
| `logo.png` | Logotipo BigBitByte (extraído de la portada del manual, baja resolución; falta el SVG original). |

## Estructura recomendada del archivo de Figma

Una **página de Figma por sección**. Así el equipo se reparte el trabajo sin pisarse.

| Página | Contenido | Responsable |
|---|---|---|
| 0. Portada | Nombre, versión, estado, enlaces a los manuales | |
| 1. Fundamentos | Colores, tipografía, espaciado, radios, íconos, logotipo | |
| 2. Componentes | Ver la tabla de componentes de abajo | |
| 3. Acceso | Inicio de sesión, registro, páginas de error | |
| 4. Panel | Barra superior, menú lateral, inicio con consumo del plan y exámenes | |
| 5. Grupos | Lista, alta de grupo, tarjeta con QR y folio | |
| 6. Banco de preguntas | Lista y alta (4 tipos de pregunta) | |
| 7. Exámenes | Lista, alta, apartados, publicar | |
| 8. Suscripción | Planes y pagos | |
| 9. Resultados y estadísticas | Lista de intentos, detalle de intento y calificación, gráficas | |
| 10. Superadministrador | **Por diseñar** (el backend aún no tiene este rol) | |
| 11. Alumno (web) | **Por diseñar** (unirse con folio, presentar examen, ver resultado) | |
| 12. Flujos | Mapa de navegación y flujo docente y alumno | |

## 1. Fundamentos (COD-WEB §6.14, §6.15, §6.19)

- **Colores:** los 10 de `tokens.json`. Reglas: contraste mínimo 4.5:1 para texto, el color nunca es el único indicador de estado (siempre texto o ícono), el acento `#3B82F6` nunca se usa para texto.
- **Tipografía:** fuente del sistema. En Figma usar Inter o Segoe UI como sustituto. Estilos: título 24/600, sección 20/600, cuerpo 16/400, botón 16/600, etiqueta 14/400, leyenda 12/400, código 14 monoespaciado.
- **Espaciado:** cuadrícula de 8 px. Márgenes y bloques 24 px, entre campos 16 px.
- **Logotipo (CAL §2.2):** cubo de tres B. Mínimo 32 px, margen libre igual a la mitad de su alto, no girar, deformar ni recolorear. En la barra superior 40 px y en el inicio de sesión 96 px, dentro de un bloque `#1F2937`.
- **Íconos:** solo Heroicons. Un ícono por acción de la Tabla 17 (Abrir `folder-open`, Nuevo `plus`, Guardar `check`, Editar `pencil-square`, Eliminar `trash`, Cancelar `x-mark`, Buscar `magnifying-glass`, Publicar `paper-airplane`, Resultados `clipboard-document-check`, Estadísticas `chart-bar`, Descargar QR `qr-code`, Copiar folio `clipboard-document`, Reintentar `arrow-path`, Cerrar sesión `arrow-right-on-rectangle`).

## 2. Componentes (COD-WEB §6.17, §6.18; Tablas 15 a 17)

Diseñar cada uno como componente con variantes y estados (reposo, hover, foco, deshabilitado, error).

| Componente | Medidas y reglas | Variantes |
|---|---|---|
| Botón | Alto 44, radio 8, texto 16/600 | Principal (relleno `#1F2937`), secundario (borde 1), de texto (acento sobre blanco, primario subrayado sobre tarjeta), peligro; con y sin ícono |
| Caja de texto | Alto 44, borde 1 `#A0AEC0`, radio 8, foco con anillo 2 `#2E74B5`, error con borde `#B3261E` | Con etiqueta arriba, ayuda y mensaje de error abajo |
| Lista desplegable y área de texto | Mismas reglas que la caja de texto | Normal, foco, error |
| Tarjeta | Padding 24, fondo `#F3F4F6`, radio 12, sin sombra | Con título, con acciones |
| Tabla de datos | Encabezado `#1F2937` con texto blanco en seminegrita, bordes 1, filas de 48 como mínimo, 15 filas por página | Con paginación, con acciones de fila (solo ícono con descripción) |
| Insignia de estado | Texto más color: Borrador, Publicado, Cerrado, Activo | 4 estados |
| Alertas | Éxito e informativo: aviso arriba que desaparece a los 5 s. Error: banner persistente con botón cerrar. Advertencia: banner | 4 tipos |
| Modal de confirmación | Ancho máximo 512, radio 12, máximo 2 botones, acción principal a la derecha | Eliminar (peligro) |
| Código QR | 256 × 256, margen blanco 16, folio debajo, botones Descargar QR y Copiar folio | |
| Barra superior | Alto 64, color primario, logotipo 40, nombre del usuario y menú de cuenta | Escritorio, móvil con hamburguesa |
| Menú lateral | Ancho 256, color primario, ítem activo marcado con negrita y borde (no solo color) | 7 destinos del docente |
| Paginación | Anterior, números, Siguiente | |
| Gráfica de barras | SVG propio (CAL §3.10): serie principal `#3B82F6`, secundaria `#60A5FA` con rayas, apoyo `#1F2937`; sin 3D ni sombras; título, ejes con unidad, leyenda, fuente y valor escrito sobre cada barra | Una serie, dos series, estado vacío ("Aún no hay resultados para mostrar.") |
| Tarjeta de cifra | Etiqueta, valor grande y nota; estados normal y pendiente | Resumen de estadísticas |

## 3. Pantallas por sección

Cada fila indica el estado real de la maqueta y la vista que la implementa, para comparar contra el diseño.

| # | Sección | Pantallas a diseñar | Captura de referencia | Vista Blade | Estado |
|---|---|---|---|---|---|
| 3 | **Acceso** | Inicio de sesión, registro (cuenta queda inactiva hasta asignar plan), error 404, 419 y 500 | `01-login`, `02-registro`, `12-error-404` | `auth/login`, `auth/registro`, `errors/*` | Funciona con Supabase |
| 4 | **Panel** | Inicio: aviso de límite del plan, tarjetas de consumo (grupos, preguntas, alumnos), tabla de exámenes | `03-panel` | `teacher/dashboard` | Datos reales |
| 5 | **Grupos** | Lista de grupos, formulario Nuevo grupo, tarjeta con QR y folio, errores 402 y 403 | `04-grupos` | `grupos/index` | Datos reales |
| 6 | **Banco de preguntas** | Lista y alta para opción múltiple, relación de columnas, abierta y programación | `05-banco-de-preguntas` | `preguntas/index` | Datos reales |
| 7 | **Exámenes** | Lista, nuevo examen, apartados del examen y asignación de preguntas, publicar | `06-examenes`, `07-examen-apartados` | `examenes/index`, `examenes/apartados` | Datos reales |
| 8 | **Suscripción** | Planes disponibles y pagos del docente (solo lectura) | `09-suscripcion` | `suscripcion/index` | Datos reales |
| 9 | **Resultados** | Elegir examen, tabla de intentos (alumno, estado, entrega, nota o "Pendiente de calificar"), detalle de intento con respuestas, incidencias, calificación manual con motivo y autocalificar opción múltiple | `10-resultados`, `10b-resultados-detalle-intento` | `resultados/index`, `resultados/intento` | Datos reales |
| 9b | **Estadísticas** | Resumen general, promedio y aprobación por examen y por grupo, distribución de notas, preguntas con mayor error (gráficas de barras con tabla equivalente) | `11-estadisticas` | `estadisticas/index` | Datos reales (calculadas en el panel; la API no tiene analytics) |
| 10 | **Superadministrador** | Docentes (activar y suspender), pagos y suscripciones | `admin/dashboard` (maqueta) | `admin/dashboard` | El backend no lo tiene |
| 11 | **Alumno (web)** | Unirse con folio o QR, presentar examen con temporizador, finalizar, ver resultado | | | No existe; la API sí (`/api/v1/student/attempts/*`) |

Las gráficas siguen el Manual de Calidad §3.10: serie principal `#3B82F6`, secundaria `#60A5FA`, apoyo `#1F2937`, sin 3D ni sombras, con título, ejes con unidad, leyenda y fuente. Ojo: `#60A5FA` da 2.54:1 sobre blanco, por eso las barras llevan el valor escrito encima y la serie secundaria va con rayas (el color nunca es el único indicador).

## 4. Flujo de navegación para el mapa de Figma

```
Registro ─▶ (cuenta inactiva hasta que se asigne plan)
Inicio de sesión ─▶ Panel
Panel ─▶ Grupos ─▶ Nuevo grupo ─▶ Tarjeta con QR y folio
Panel ─▶ Banco de preguntas ─▶ Nueva pregunta
Panel ─▶ Exámenes ─▶ Nuevo examen ─▶ Apartados ─▶ Asignar preguntas ─▶ Publicar
Panel ─▶ Resultados ─▶ Calificar respuesta ─▶ Estadísticas
Panel ─▶ Suscripción
Cualquier pantalla ─▶ Menú de cuenta ─▶ Cerrar sesión

Alumno (web o móvil): folio o QR ─▶ pase del docente ─▶ presentar examen ─▶ finalizar ─▶ resultado
```

## 5. Reglas de interfaz que Figma debe respetar (resumen)

- Barra superior 64 px y menú lateral 256 px; bajo 1024 px el menú pasa a hamburguesa.
- Contenido centrado, ancho máximo 1280, márgenes de 24.
- Formularios de una columna, etiqueta arriba, error abajo, botón principal abajo a la derecha.
- Todo elemento interactivo con área mínima de 44 × 44.
- Mensajes en español, frases cortas, en voz activa, sin códigos ni detalles técnicos.
- Mayúscula inicial solo en la primera palabra de cada texto.

## 6. Hallazgos de contraste abiertos (decisión del equipo)

| Par | Relación | Nota |
|---|---|---|
| Borde `#A0AEC0` sobre blanco | 2.26:1 | Bajo 3:1 que WCAG pide para componentes |
| Acento `#2E74B5` sobre `#F3F4F6` | 4.46:1 | Por eso dentro de tarjetas los enlaces van en primario subrayado |
| Acento claro `#3B82F6` como texto | 3.68:1 | Ya prohibido por el manual |

## 7. Cómo llevarlo a Figma

1. **Tokens:** instalar el plugin Tokens Studio, importar `tokens.json` y generar las variables de color, tipografía y tamaños.
2. **Íconos:** arrastrar los SVG de `iconos/` a la página Fundamentos y convertirlos en componentes.
3. **Pantallas:** usar las capturas de `capturas/` como referencia para reconstruir cada pantalla con componentes. Alternativa: un plugin de la comunidad que importe una página web en ejecución (por ejemplo html.to.design) apuntando a `http://localhost:8000`; conviene probarlo y revisar el resultado.
4. **Revisión:** comparar cada pantalla contra la tabla de la sección 3 y los manuales antes de marcarla como lista.
