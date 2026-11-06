# Registro de recursos gráficos

Registro de todo recurso gráfico incorporado a `public/` o a `resources/`, según el Manual del Código Web
(BBB-MAN-COD-WEB v1.3), secciones 6.19 y 14.4. Un recurso que no esté en esta tabla no se acepta en la revisión de código.

| Recurso | Origen | Licencia | Incorporación |
| --- | --- | --- | --- |
| Íconos Heroicons, variante 20/solid (29 SVG copiados a `resources/views/components/icon/`; paquete npm `heroicons` 2.2.0) | Tailwind Labs, <https://heroicons.com> | MIT (uso libre, incluso comercial) | 27/10/2026 |
| Logotipo de BigBitByte (`public/images/logo.png`, PNG con transparencia de 537 × 600 px) | Equipo del proyecto (BigBitByte) | Propiedad del proyecto. Extraído de la portada del Manual de Código v1.3, resolución baja; pendiente el SVG original | 27/10/2026 |
| simple-qrcode 4.2.0 (genera el QR del grupo como SVG; depende de `bacon/bacon-qr-code` 2.0.8 y `dasprid/enum` 1.0.7) | SimpleSoftwareIO, <https://github.com/SimpleSoftwareIO/simple-qrcode> | MIT (simple-qrcode); BSD-2-Clause (bacon-qr-code y dasprid/enum) | 27/10/2026 |

## Notas

- Los íconos se usan solo mediante el componente `<x-icon name="...">`, con tamaño de 20 × 20 px y un solo color (`currentColor`).
  No se mezclan con otros conjuntos de íconos (COD-WEB §6.19).
- El logotipo se inserta solo mediante el componente `<x-logo>` (COD-WEB §6.14). Cuando exista el SVG original,
  se reemplaza el archivo y se actualiza esta fila.
- El QR se genera como SVG para no depender de las extensiones `gd` o `imagick` de PHP.
