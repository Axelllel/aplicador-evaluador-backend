<?php

/**
 * Archivo: VistasBladeTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica los estándares de las vistas: cabecera de archivo, ausencia de colores propios,
 *              logotipo solo mediante x-logo, textos en lang/es y sin fuentes externas
 *              (Manual COD-WEB, secciones 6.7, 6.14, 6.15 y 6.18).
 * Autor: Leonardo Ballestero López
 * Creado: 27/10/2026
 * Modificado: 27/10/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class VistasBladeTest extends TestCase
{
    public function test_todas_las_vistas_empiezan_con_la_cabecera_de_archivo(): void
    {
        $vistas = $this->archivosDe(resource_path('views'), '.blade.php');

        $this->assertNotEmpty($vistas);

        foreach ($vistas as $ruta => $contenido) {
            $this->assertStringStartsWith('{{--', $contenido, "{$ruta} debe empezar con la cabecera {{-- ... --}}.");
            $this->assertMatchesRegularExpression('/^\{\{--(.*?)--\}\}/s', $contenido);

            preg_match('/^\{\{--(.*?)--\}\}/s', $contenido, $cabecera);

            foreach (['Archivo: '.basename($ruta), 'Módulo:', 'Descripción:', 'Autor: Leonardo Ballestero López', 'Creado:', 'Modificado:'] as $campo) {
                $this->assertStringContainsString($campo, $cabecera[1], "{$ruta} no tiene el campo «{$campo}» en la cabecera.");
            }
        }
    }

    public function test_los_archivos_php_nuevos_llevan_el_prologo_antes_de_declare_strict_types(): void
    {
        $archivos = array_merge(
            $this->archivosDe(app_path('Enums'), '.php'),
            $this->archivosDe(app_path('Services'), '.php'),
            $this->archivosDe(app_path('View'), '.php'),
            $this->archivosDe(app_path('Http/Controllers/Web'), '.php'),
            $this->archivosDe(app_path('Http/Requests'), '.php'),
            $this->archivosDe(base_path('tests/Feature'), '.php'),
            [base_path('routes/web.php') => (string) file_get_contents(base_path('routes/web.php'))],
        );

        // Los archivos del backend (Supabase, controladores de la API) no siguen la cabecera del manual.
        $archivos = array_filter(
            $archivos,
            fn (string $ruta): bool => ! str_ends_with($ruta, 'DatabaseUserContext.php'),
            ARRAY_FILTER_USE_KEY,
        );

        foreach (['panel', 'acciones', 'mensajes', 'sesion', 'ejemplo', 'guia'] as $archivoDeIdioma) {
            $ruta = lang_path('es/'.$archivoDeIdioma.'.php');
            $archivos[$ruta] = (string) file_get_contents($ruta);
        }

        foreach ($archivos as $ruta => $contenido) {
            $this->assertMatchesRegularExpression(
                '/^<\?php\n\n\/\*\*\n \* Archivo: '.preg_quote(basename($ruta), '/').'\n \* Módulo: .+\n \* Descripción: .+\n(?: \*  .+\n)* \* Autor: Leonardo Ballestero López\n \* Creado: \d{2}\/\d{2}\/\d{4}\n \* Modificado: .+\n \*\/\n\ndeclare\(strict_types=1\);/',
                $contenido,
                "{$ruta} debe tener el prólogo del Manual COD-WEB §6.7 antes de declare(strict_types=1).",
            );
        }
    }

    public function test_ninguna_vista_contiene_colores_hexadecimales_ni_valores_arbitrarios_de_color(): void
    {
        foreach ($this->archivosDe(resource_path('views'), '.blade.php') as $ruta => $contenido) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![&\w])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b/',
                $contenido,
                "{$ruta} contiene un color hexadecimal.",
            );
            $this->assertStringNotContainsString('bg-[#', $contenido, "{$ruta} usa bg-[#...].");
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:bg|text|border|ring|outline|fill|stroke|divide|decoration|from|to|via)-\[/',
                $contenido,
                "{$ruta} usa un valor arbitrario en una utilidad de color.",
            );
            $this->assertDoesNotMatchRegularExpression('/\b(?:rgb|rgba|hsl|hsla|oklch)\(/', $contenido, "{$ruta} declara un color.");
            $this->assertDoesNotMatchRegularExpression('/style="[^"]*color/i', $contenido, "{$ruta} usa un color en línea.");
        }
    }

    public function test_las_vistas_solo_usan_colores_de_los_tokens_del_tema(): void
    {
        $paletaDeTailwind = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

        foreach ($this->archivosDe(resource_path('views'), '.blade.php') as $ruta => $contenido) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:bg|text|border|ring|outline|fill|stroke|divide|decoration)-(?:'.$paletaDeTailwind.'|black)(?:-\d+)?\b/',
                $contenido,
                "{$ruta} usa un color de la paleta por defecto de Tailwind en lugar de un token.",
            );
        }
    }

    public function test_el_logotipo_solo_se_inserta_con_el_componente_x_logo(): void
    {
        foreach ($this->archivosDe(resource_path('views'), '.blade.php') as $ruta => $contenido) {
            if (basename($ruta) === 'logo.blade.php') {
                $this->assertStringContainsString('images/logo.png', $contenido);

                continue;
            }

            $this->assertStringNotContainsString('logo.png', $contenido, "{$ruta} no debe referenciar el archivo del logotipo.");
            $this->assertStringNotContainsString('<img', $contenido, "{$ruta} no debe insertar imágenes directamente.");
        }
    }

    public function test_las_vistas_no_cargan_fuentes_ni_recursos_externos(): void
    {
        foreach ($this->archivosDe(resource_path('views'), '.blade.php') as $ruta => $contenido) {
            $this->assertDoesNotMatchRegularExpression('/fonts\.(?:googleapis|bunny|gstatic)/', $contenido, $ruta);
            $this->assertDoesNotMatchRegularExpression('/<link[^>]+href="https?:/', $contenido, $ruta);
            $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="https?:/', $contenido, $ruta);
        }
    }

    public function test_las_claves_de_idioma_usadas_en_las_vistas_existen_en_lang_es(): void
    {
        foreach ($this->archivosDe(resource_path('views'), '.blade.php') as $ruta => $contenido) {
            preg_match_all('/__\(\s*\'([a-z0-9_.\-]+)\'\s*[,)]/', $contenido, $coincidencias);

            foreach ($coincidencias[1] as $clave) {
                $this->assertTrue(Lang::has($clave, 'es'), "{$ruta} usa la clave «{$clave}», que no existe en lang/es.");
            }
        }
    }

    public function test_los_textos_de_las_acciones_coinciden_con_la_tabla_17(): void
    {
        $this->assertSame(
            [
                'abrir' => 'Abrir',
                'nuevo' => 'Nuevo',
                'guardar' => 'Guardar',
                'editar' => 'Editar',
                'eliminar' => 'Eliminar',
                'cancelar' => 'Cancelar',
                'aceptar' => 'Aceptar',
                'buscar' => 'Buscar',
                'publicar' => 'Publicar',
                'resultados' => 'Resultados',
                'estadisticas' => 'Estadísticas',
                'descargar_qr' => 'Descargar QR',
                'copiar_folio' => 'Copiar folio',
                'reintentar' => 'Reintentar',
                'cerrar_sesion' => 'Cerrar sesión',
            ],
            array_intersect_key(
                __('acciones'),
                array_flip(['abrir', 'nuevo', 'guardar', 'editar', 'eliminar', 'cancelar', 'aceptar', 'buscar', 'publicar', 'resultados', 'estadisticas', 'descargar_qr', 'copiar_folio', 'reintentar', 'cerrar_sesion']),
            ),
        );
    }

    /**
     * Devuelve el contenido de todos los archivos con la terminación indicada, por ruta.
     *
     * @return array<string, string>
     */
    private function archivosDe(string $directorio, string $terminacion): array
    {
        $archivos = [];

        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getFilename(), $terminacion)) {
                $archivos[str_replace('\\', '/', $archivo->getPathname())] = (string) file_get_contents($archivo->getPathname());
            }
        }

        ksort($archivos);

        return $archivos;
    }
}
