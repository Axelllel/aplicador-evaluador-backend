<?php

/**
 * Archivo: guia.php
 * Módulo: lang/es
 * Descripción: Textos de la guía de estilo del panel (Manual COD-WEB, secciones 6.14 a 6.19).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

return [
    'titulo' => 'Guía de estilo',
    'descripcion' => 'Todos los elementos visuales del panel, con sus medidas y la sección del manual que cumplen.',
    'referencia' => 'COD-WEB §:seccion',
    'en_esta_pagina' => 'En esta página',

    'secciones' => [
        'paleta' => 'Paleta de colores',
        'contraste' => 'Relaciones de contraste',
        'hallazgos' => 'Hallazgos de contraste',
        'tipografia' => 'Tipografía',
        'distribucion' => 'Distribución y medidas',
        'botones' => 'Botones',
        'campos' => 'Cajas de texto',
        'tarjeta' => 'Tarjeta',
        'tabla' => 'Tabla de datos',
        'mensajes' => 'Mensajes',
        'modal' => 'Ventana modal y confirmación',
        'qr' => 'Código QR del grupo',
        'acciones' => 'Acciones e íconos',
        'logotipo' => 'Logotipo',
    ],

    'paleta' => [
        'descripcion' => 'Los colores se definen una sola vez en resources/css/app.css; las vistas solo usan estos tokens.',
        'columnas' => [
            'muestra' => 'Muestra',
            'token' => 'Token',
            'hex' => 'Hexadecimal',
            'uso' => 'Uso',
            'sobre_blanco' => 'Contraste sobre blanco',
            'texto_blanco' => 'Contraste con texto blanco encima',
        ],
        'usos' => [
            'primario' => 'Texto principal, títulos, barra superior y lateral, botones principales y encabezados de tabla.',
            'acento' => 'Enlaces, anillo de foco y texto sobre fondo blanco.',
            'acento-claro' => 'Solo elementos decorativos e indicadores; no se usa para texto.',
            'acento-medio' => 'Serie secundaria de las gráficas; nunca para texto y siempre con etiquetas directas.',
            'borde' => 'Bordes de campos y tarjetas, separadores.',
            'texto-suave' => 'Etiquetas, descripciones y textos de apoyo.',
            'white' => 'Fondo general de todas las páginas.',
            'superficie' => 'Tarjetas, bloques de resultados y zonas agrupadas.',
            'exito' => 'Mensajes y estados de operación correcta.',
            'aviso' => 'Avisos, como el límite del plan de suscripción.',
            'error' => 'Errores, validaciones y acciones destructivas.',
        ],
    ],

    'contraste' => [
        'descripcion' => 'El texto necesita 4.5:1 como mínimo y los bordes y componentes 3:1. '
            .'El color nunca es el único indicador: siempre va con texto o ícono.',
        'columnas' => [
            'elemento' => 'Elemento',
            'fondo' => 'Fondo',
            'uso' => 'Uso',
            'relacion' => 'Relación',
            'minimo' => 'Mínimo',
            'resultado' => 'Resultado',
        ],
        'cumple' => 'Cumple',
        'no_cumple' => 'No cumple',
        'usos' => [
            'texto_principal' => 'Texto principal sobre la página',
            'texto_en_tarjeta' => 'Texto principal dentro de una tarjeta',
            'barras_y_botones' => 'Texto blanco sobre barras y botones principales',
            'texto_secundario' => 'Etiquetas y texto de apoyo sobre blanco',
            'texto_secundario_en_tarjeta' => 'Etiquetas y texto de apoyo dentro de una tarjeta',
            'enlace_sobre_blanco' => 'Enlaces y botones de texto sobre fondo blanco',
            'error_sobre_blanco' => 'Mensaje de validación sobre blanco',
            'error_en_tarjeta' => 'Mensaje de validación dentro de una tarjeta',
            'exito_sobre_blanco' => 'Estado de éxito sobre blanco',
            'exito_en_tarjeta' => 'Estado de éxito dentro de una tarjeta',
            'aviso_sobre_blanco' => 'Estado de advertencia sobre blanco',
            'aviso_en_tarjeta' => 'Estado de advertencia dentro de una tarjeta',
            'banner_exito' => 'Texto blanco en el aviso de éxito',
            'banner_advertencia' => 'Texto blanco en el banner de advertencia',
            'banner_error' => 'Texto blanco en el banner de error y en el botón Eliminar de la confirmación',
            'borde_componentes' => 'Borde de cajas de texto y tarjetas sobre blanco',
            'acento_en_tarjeta' => 'Enlaces y botones de texto en acento dentro de una tarjeta',
            'acento_claro_como_texto' => 'Acento claro usado como texto',
        ],
    ],

    'hallazgos' => [
        'descripcion' => 'Combinaciones definidas por el manual que no alcanzan el mínimo. '
            .'No se cambian los colores del manual: las vistas las evitan o las compensan.',
        'columnas' => [
            'hallazgo' => 'Hallazgo',
            'colores' => 'Colores',
            'relacion' => 'Relación',
            'minimo' => 'Mínimo',
            'resultado' => 'Resultado',
            'referencia' => 'Sección del manual',
        ],
        'colores' => ':texto sobre :fondo',
        'items' => [
            'borde_componentes' => [
                'titulo' => 'Borde sobre blanco',
                'detalle' => 'Los bordes de cajas de texto y tarjetas quedan bajo 3:1, '
                    .'el mínimo para componentes de interfaz.',
                'referencia' => 'COD-WEB §6.14 (Tabla 12) y §6.17 (Tabla 15)',
            ],
            'acento_en_tarjeta' => [
                'titulo' => 'Acento sobre superficie',
                'detalle' => 'El acento queda bajo 4.5:1 sobre el fondo de las tarjetas. '
                    .'Allí los botones de texto y los enlaces usan el color primario con subrayado.',
                'referencia' => 'COD-WEB §6.14 (Tabla 12) y §6.17 (Tabla 15)',
            ],
            'acento_claro_como_texto' => [
                'titulo' => 'Acento claro como texto',
                'detalle' => 'El acento claro queda bajo 4.5:1 sobre blanco; por eso el manual prohíbe usarlo para texto.',
                'referencia' => 'COD-WEB §6.14 (Tabla 12)',
            ],
        ],
    ],

    'tipografia' => [
        'descripcion' => 'Fuente del sistema (font-sans), tamaños en rem e interlineado de 1.5 en el cuerpo. '
            .'Solo la primera palabra de cada texto lleva mayúscula inicial y, con zoom del 200 %, nada se trunca.',
        'columnas' => [
            'estilo' => 'Estilo',
            'muestra' => 'Muestra',
            'medidas' => 'Tamaño, grosor y color',
            'clase' => 'Clase de Tailwind',
        ],
        'estilos' => [
            'titulo_pagina' => [
                'nombre' => 'Título de página',
                'muestra' => 'Panel de exámenes',
                'medidas' => '24 px (1.5 rem), seminegrita, primario',
                'clase' => 'text-2xl font-semibold',
            ],
            'encabezado_seccion' => [
                'nombre' => 'Encabezado de sección',
                'muestra' => 'Resumen del plan',
                'medidas' => '20 px (1.25 rem), seminegrita, primario',
                'clase' => 'text-xl font-semibold',
            ],
            'cuerpo' => [
                'nombre' => 'Cuerpo de texto',
                'muestra' => 'Revisa los exámenes de tus grupos y publica los que estén listos para tus alumnos.',
                'medidas' => '16 px (1 rem), normal, primario, interlineado de 1.5',
                'clase' => 'text-base leading-normal',
            ],
            'boton' => [
                'nombre' => 'Texto de botón',
                'muestra' => 'Guardar',
                'medidas' => '16 px (1 rem), seminegrita, blanco o primario',
                'clase' => 'text-base font-semibold',
            ],
            'etiqueta' => [
                'nombre' => 'Etiqueta y texto secundario',
                'muestra' => 'Correo electrónico',
                'medidas' => '14 px (0.875 rem), normal, texto-suave',
                'clase' => 'text-sm text-texto-suave',
            ],
            'leyenda' => [
                'nombre' => 'Leyenda',
                'muestra' => 'Escribe al menos 8 caracteres.',
                'medidas' => '12 px (0.75 rem), normal, texto-suave',
                'clase' => 'text-xs text-texto-suave',
            ],
            'validacion' => [
                'nombre' => 'Mensaje de validación',
                'muestra' => 'Completa correo electrónico.',
                'medidas' => '12 px (0.75 rem), normal, error',
                'clase' => 'text-xs text-error',
            ],
            'codigo' => [
                'nombre' => 'Código en línea (folio, ruta)',
                'muestra' => 'ABC-1234',
                'medidas' => '14 px (0.875 rem), monoespaciada, primario sobre superficie',
                'clase' => 'font-mono text-sm',
            ],
        ],
    ],

    'distribucion' => [
        'descripcion' => 'Cuadrícula de 8 px. Por debajo de 1024 px la barra lateral se oculta y se abre con el botón de menú.',
        'columnas' => [
            'elemento' => 'Elemento',
            'medida' => 'Medida',
            'clase' => 'Clase de Tailwind',
        ],
        'medidas' => [
            ['elemento' => 'Barra superior', 'medida' => '64 px de alto', 'clase' => 'h-16'],
            ['elemento' => 'Barra lateral', 'medida' => '256 px de ancho, fija desde 1024 px', 'clase' => 'w-64 lg:block'],
            ['elemento' => 'Contenido', 'medida' => 'Centrado, hasta 1280 px de ancho', 'clase' => 'max-w-[1280px]'],
            ['elemento' => 'Margen del contenido', 'medida' => '24 px', 'clase' => 'p-6'],
            ['elemento' => 'Separación entre bloques', 'medida' => '24 px', 'clase' => 'gap-6, space-y-6'],
            ['elemento' => 'Separación entre campos', 'medida' => '16 px', 'clase' => 'space-y-4'],
            ['elemento' => 'Área mínima de clic o toque', 'medida' => '44 × 44 px', 'clase' => 'min-h-11 min-w-11'],
        ],
    ],

    'botones' => [
        'descripcion' => 'Alto de 44 px como mínimo y esquinas de 8 px. '
            .'El botón principal ocupa todo el ancho en los formularios móviles.',
        'principal' => 'Principal',
        'secundario' => 'Secundario',
        'texto' => 'De texto sobre fondo blanco',
        'texto_en_tarjeta' => 'De texto dentro de una tarjeta',
        'eliminar' => 'De eliminación',
        'ancho_completo' => 'Principal de ancho completo',
        'nota_acento' => 'El acento solo se usa sobre fondo blanco; dentro de una tarjeta el botón de texto '
            .'va en color primario con subrayado.',
    ],

    'campos' => [
        'descripcion' => 'Alto de 44 px, borde de 1 px y esquinas de 8 px. Con foco muestra un anillo de 2 px en acento; '
            .'con error, el borde cambia a color de error y el mensaje va debajo.',
        'normal' => 'Normal',
        'foco' => 'Con foco (simulado)',
        'error' => 'Con error',
        'etiqueta' => 'Correo electrónico',
        'marcador' => 'nombre@escuela.example',
        'ayuda' => 'Pulsa Tab sobre cualquier campo para ver el foco real.',
        'mensaje_de_error' => 'Completa correo electrónico.',
    ],

    'tarjeta' => [
        'descripcion' => 'Relleno de 24 px, fondo de superficie, esquinas de 12 px y sin sombra.',
        'encabezado' => 'Grupos activos',
        'texto' => 'Tienes 5 grupos con exámenes publicados.',
        'enlace' => 'Ver los grupos',
        'secundario' => 'Los enlaces dentro de una tarjeta van en color primario y subrayados.',
    ],

    'tabla' => [
        'descripcion' => 'Filas de 48 px como mínimo, 15 filas por página, encabezado primario con texto blanco '
            .'en seminegrita y bordes de 1 px. Las acciones de fila llevan título y texto alternativo.',
    ],

    'mensajes' => [
        'descripcion' => 'Éxito e información son avisos superiores que se ocultan a los 5 s; '
            .'error y advertencia son banners.',
        'avisos_superiores' => 'Avisos superiores (contenedor demostrativo)',
        'nota_avisos' => 'En el panel aparecen fijos en la parte superior de la pantalla. '
            .'Se ocultan a los 5 s; usa Reintentar para verlos de nuevo.',
        'banners' => 'Banners',
        'nota_banners' => 'El banner de error es persistente y tiene botón de cierre. Recarga la página para verlo otra vez.',
    ],

    'modal' => [
        'descripcion' => 'Ancho máximo de 512 px, esquinas de 12 px y como máximo dos botones, '
            .'con la acción principal a la derecha.',
        'eliminar' => 'Confirmación de eliminación (botón en color de error)',
        'publicar' => 'Confirmación general (botón primario)',
    ],

    'qr' => [
        'descripcion' => 'Cuadrado de 256 × 256 px, centrado, con margen blanco de 16 px y el folio debajo. '
            .'Se genera como SVG con simple-qrcode.',
    ],

    'acciones' => [
        'descripcion' => 'Cada texto va siempre con el mismo ícono de Heroicons (20 × 20 px, un solo color). '
            .'Solo la barra superior y las acciones de tabla usan íconos sin texto visible, con título y texto alternativo.',
        'columnas' => [
            'accion' => 'Acción',
            'boton' => 'Botón',
            'tabla' => 'Acción de tabla',
            'icono' => 'Ícono en Heroicons',
            'observacion' => 'Observación',
        ],
        'descripciones' => [
            'abrir' => 'Abrir',
            'nuevo' => 'Crear un recurso',
            'guardar' => 'Guardar',
            'editar' => 'Editar',
            'eliminar' => 'Eliminar',
            'cancelar' => 'Cancelar',
            'aceptar' => 'Aceptar',
            'buscar' => 'Buscar',
            'publicar' => 'Publicar un examen',
            'resultados' => 'Ver resultados',
            'estadisticas' => 'Ver estadísticas',
            'descargar_qr' => 'Descargar código QR',
            'copiar_folio' => 'Copiar folio',
            'reintentar' => 'Reintentar',
            'cerrar_sesion' => 'Cerrar sesión',
        ],
        'observaciones' => [
            'eliminar' => 'En color de error y con confirmación previa.',
            'guardar' => 'Comparte el ícono con Aceptar, como indica el manual.',
            'aceptar' => 'Comparte el ícono con Guardar, como indica el manual.',
        ],
    ],

    'iconos_adicionales' => [
        'encabezado' => 'Íconos del menú, la barra superior y los mensajes',
        'descripcion' => 'Salen del mismo conjunto Heroicons y no se mezclan con otros.',
        'lista' => [
            'squares-2x2' => 'Menú lateral: Panel',
            'user-group' => 'Menú lateral: Grupos',
            'question-mark-circle' => 'Menú lateral: Banco de preguntas',
            'document-text' => 'Menú lateral: Exámenes',
            'credit-card' => 'Menú lateral: Suscripción',
            'users' => 'Menú lateral: Docentes',
            'banknotes' => 'Menú lateral: Pagos y suscripciones',
            'bars-3' => 'Barra superior: botón de menú',
            'user-circle' => 'Barra superior: menú de cuenta',
            'chevron-down' => 'Barra superior: indicador del menú de cuenta',
            'check-circle' => 'Mensaje de éxito y resultado que cumple',
            'x-circle' => 'Mensaje de error y resultado que no cumple',
            'exclamation-triangle' => 'Mensaje de advertencia',
            'information-circle' => 'Mensaje informativo',
            'exclamation-circle' => 'Mensaje de validación de un campo',
        ],
    ],

    'logotipo' => [
        'descripcion' => 'Se inserta solo con el componente de logotipo, conserva su proporción, no se recolorea '
            .'y deja un margen libre igual a la mitad de su alto.',
        'barra' => 'Barra superior: 40 px de alto y 20 px de margen libre',
        'inicio_de_sesion' => 'Inicio de sesión: 96 px de alto y 48 px de margen libre',
        'origen' => 'El archivo viene de la portada del manual, con resolución baja; está pendiente el SVG original.',
    ],
];
