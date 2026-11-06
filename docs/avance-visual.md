# Avance visual del panel docente

Maqueta navegable del panel web (Laravel 12, Blade y Tailwind 4) construida según el Manual del Código Web
(BBB-MAN-COD-WEB v1.3), secciones 6.1 a 6.19. Usa datos de ejemplo y no tiene autenticación real.

> TODO(Leonardo Ballestero López): todo lo marcado como maqueta se reemplaza cuando existan los modelos
> Docente, Alumno, Grupo, Examen y Suscripción. Hoy cualquier correo válido y contraseña de 8 caracteres o más
> abren el panel, y el rol se elige con `?rol=super_admin`.

## Cómo verlo

```powershell
php artisan serve          # http://127.0.0.1:8000
npm run build              # genera public/build (o npm run dev para recarga en vivo)
php artisan test           # pruebas del avance
```

En Windows con el proyecto dentro de OneDrive, `bootstrap/cache` puede no ser escribible. Antes de `php artisan`
define las variables `APP_PACKAGES_CACHE`, `APP_SERVICES_CACHE`, `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`
y `APP_EVENTS_CACHE` apuntando a una carpeta local (consulta `iniciar-demo.ps1`).

## Páginas

| Ruta | Qué muestra | Sección del manual |
| --- | --- | --- |
| `GET /` | Redirige a `/login` | 6.16 |
| `GET /login` | Logotipo de 96 px, formulario de una columna y errores de validación en español | 6.14, 6.16, 6.17 |
| `GET /panel` | Panel del docente: aviso de límite, tarjetas de uso del plan y tabla de exámenes (15 filas por página) | 6.16, 6.17, 6.18 |
| `GET /panel?rol=super_admin` | Panel del superadministrador con su menú lateral y tabla de docentes | 6.16 (Tabla 14) |
| `GET /guia-estilo` | Guía de estilo: paleta, contraste, hallazgos, tipografía, componentes, mensajes, modal, QR, íconos y logotipo | 6.14 a 6.19 |
| `GET /grupos`, `/banco-de-preguntas`, `/examenes`, `/resultados`, `/estadisticas`, `/suscripcion`, `/docentes`, `/pagos` | Páginas provisionales para que cada destino del menú tenga su marcador de página activa | 6.16 |

## Estructura

- `app/Http/Controllers/Web/`: controladores delgados (`SesionController`, `PanelController`, `SeccionController`, `GuiaEstiloController`).
- `app/Services/`: `MenuLateralService` (Tabla 14), `CodigoQrService`, `PaletaColoresService` y `PanelEjemploService` (datos de ejemplo).
- `app/Enums/`: `RolPanel` y `AccionPanel` (Tabla 17: cada acción con su texto e ícono fijos).
- `resources/views/components/`: componentes Blade (botón, caja de texto, tarjeta, tabla, modal, alerta, QR, logotipo, ícono).
- `resources/css/app.css`: única fuente de los colores (`@theme`). Las vistas solo usan los tokens `primario`, `acento`,
  `acento-claro`, `borde`, `texto-suave`, `superficie`, `exito`, `aviso`, `error` y `white`.
- `lang/es/`: todos los textos visibles (`panel`, `acciones`, `mensajes`, `sesion`, `guia`, `ejemplo`).
- `docs/recursos-graficos.md`: registro de licencias de los recursos gráficos.

## Decisiones de diseño

- El acento (`#2E74B5`) solo se usa sobre fondo blanco. Dentro de tarjetas (superficie) los botones de texto y los enlaces
  van en color primario con subrayado, y los enlaces dentro de párrafos siempre van subrayados.
- El color nunca es el único indicador: los mensajes llevan ícono y texto, los estados llevan texto, el menú activo lleva
  borde, negrita y `aria-current="page"`.
- Íconos: Heroicons 20/solid de 20 × 20 px, un solo color.
- El margen libre del logotipo es la mitad de su alto: 48 px alrededor a 96 px (bloque `p-12` sobre fondo primario en el login)
  y 20 px a los lados a 40 px en la barra superior (la barra mide 64 px, así que arriba y abajo quedan 12 px).
- La guía de estilo lista los hallazgos de contraste de la paleta del manual sin cambiar sus colores.
