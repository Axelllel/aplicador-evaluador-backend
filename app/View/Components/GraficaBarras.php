<?php

/**
 * Archivo: GraficaBarras.php
 * Módulo: app/View/Components
 * Descripción: Componente <x-grafica-barras>: calcula la geometría de una gráfica de barras en SVG propio (sin
 *              librerías ni fuentes externas) con las reglas del Manual de Calidad, sección 3.10: serie principal,
 *              secundaria y de apoyo, sin 3D ni sombras, título, ejes con unidad, leyenda, fuente de los datos y
 *              etiquetas directas sobre las barras (Manual COD-WEB, secciones 6.14 y 8.3).
 * Autor: Leonardo Ballestero López
 * Creado: 06/11/2026
 * Modificado: 06/11/2026 · Leonardo Ballestero López · Versión inicial
 */

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Gráfica de barras verticales agrupadas; el color sale de tokens del tema, aplicados en la vista.
 */
class GraficaBarras extends Component
{
    /** Ancho mínimo del dibujo; por debajo se desplaza dentro de su región para no encoger el texto. */
    public const ANCHO_MINIMO = 520;

    public const ALTO = 340;

    public const MARGEN_IZQUIERDO = 64;

    public const MARGEN_DERECHO = 16;

    public const MARGEN_SUPERIOR = 28;

    public const MARGEN_INFERIOR = 76;

    public const ANCHO_DE_BARRA = 48;

    public const SEPARACION = 28;

    /** Máximo de caracteres de la etiqueta de una categoría antes de abreviarla con «…». */
    public const LARGO_DE_ETIQUETA = 16;

    /** Tonos válidos de una serie; la vista los traduce a tokens del tema. */
    public const TONOS = ['principal', 'secundaria', 'apoyo'];

    private static int $contador = 0;

    public string $identificador;

    /**
     * @param  list<string>  $categorias  Nombre de cada grupo de barras (eje horizontal).
     * @param  list<array{nombre: string, valores: list<int|float|null>, tono?: string}>  $series
     * @param  string  $ejeX  Título del eje horizontal.
     * @param  string  $ejeY  Título del eje vertical, con su unidad (por ejemplo «Porcentaje (%)»).
     * @param  string  $fuente  Origen de los datos, visible bajo la gráfica.
     * @param  string  $unidad  Sufijo de las etiquetas de las barras (por ejemplo «%»).
     * @param  float|null  $maximo  Tope fijo del eje vertical; si es null se calcula con los datos.
     */
    public function __construct(
        public string $titulo,
        public array $categorias,
        public array $series,
        public string $ejeX,
        public string $ejeY,
        public string $fuente,
        public string $unidad = '',
        public ?float $maximo = null,
        public ?string $descripcion = null,
    ) {
        $this->identificador = 'grafica-'.++self::$contador;
    }

    /**
     * Indica si hay al menos un valor que dibujar.
     */
    public function tieneDatos(): bool
    {
        if ($this->categorias === []) {
            return false;
        }

        foreach ($this->series as $serie) {
            foreach ($serie['valores'] as $valor) {
                if ($valor !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calcula tamaños, marcas del eje vertical y posición de cada barra.
     *
     * @return array{
     *     ancho: int, alto: int, base: int, tope: int, izquierda: int, derecha: int,
     *     marcas: list<array{y: float, etiqueta: string}>,
     *     grupos: list<array{x: float, centro: float, etiqueta: string, completa: string,
     *         barras: list<array{x: float, y: float, ancho: int, alto: float, etiqueta: string, serie: int, tono: string}>}>
     * }
     */
    public function geometria(): array
    {
        $cantidad = max(1, count($this->categorias));
        $series = max(1, count($this->series));
        $anchoNatural = $series * self::ANCHO_DE_BARRA + self::SEPARACION;
        $anchoDelGrupo = max($anchoNatural, (self::ANCHO_MINIMO - self::MARGEN_IZQUIERDO - self::MARGEN_DERECHO) / $cantidad);
        $anchoDeLaZona = $anchoDelGrupo * $cantidad;
        $ancho = (int) ceil(self::MARGEN_IZQUIERDO + $anchoDeLaZona + self::MARGEN_DERECHO);

        $base = self::ALTO - self::MARGEN_INFERIOR;
        $tope = self::MARGEN_SUPERIOR;
        $altoUtil = $base - $tope;

        [$limite, $paso] = $this->escalaVertical();

        $marcas = [];

        for ($valor = 0.0; $valor <= $limite + 0.0001; $valor += $paso) {
            $marcas[] = ['y' => round($base - ($valor / $limite) * $altoUtil, 2), 'etiqueta' => $this->formatear($valor)];
        }

        $grupos = [];
        $anchoDeLasBarras = $series * self::ANCHO_DE_BARRA;

        foreach ($this->categorias as $indice => $categoria) {
            $inicio = self::MARGEN_IZQUIERDO + $indice * $anchoDelGrupo;
            $primeraBarra = $inicio + ($anchoDelGrupo - $anchoDeLasBarras) / 2;
            $barras = [];

            foreach (array_values($this->series) as $numeroDeSerie => $serie) {
                $valor = $serie['valores'][$indice] ?? null;

                if ($valor === null) {
                    continue;
                }

                $alto = round(max(0.0, min((float) $valor, $limite)) / $limite * $altoUtil, 2);

                $barras[] = [
                    'x' => round($primeraBarra + $numeroDeSerie * self::ANCHO_DE_BARRA, 2),
                    'y' => round($base - $alto, 2),
                    'ancho' => self::ANCHO_DE_BARRA,
                    'alto' => $alto,
                    'etiqueta' => $this->formatear((float) $valor, true),
                    'serie' => $numeroDeSerie,
                    'tono' => in_array($serie['tono'] ?? 'principal', self::TONOS, true) ? ($serie['tono'] ?? 'principal') : 'principal',
                ];
            }

            $grupos[] = [
                'x' => round($primeraBarra, 2),
                'centro' => round($inicio + $anchoDelGrupo / 2, 2),
                'etiqueta' => Str::limit($categoria, self::LARGO_DE_ETIQUETA, '…'),
                'completa' => $categoria,
                'barras' => $barras,
            ];
        }

        return [
            'ancho' => $ancho,
            'alto' => self::ALTO,
            'base' => $base,
            'tope' => $tope,
            'izquierda' => self::MARGEN_IZQUIERDO,
            'derecha' => $ancho - self::MARGEN_DERECHO,
            'marcas' => $marcas,
            'grupos' => $grupos,
        ];
    }

    /**
     * Texto de la tabla alternativa para lectores de pantalla: una fila por categoría con el valor de cada serie.
     *
     * @return list<list<string>>
     */
    public function filasDeDatos(): array
    {
        $filas = [];

        foreach ($this->categorias as $indice => $categoria) {
            $fila = [$categoria];

            foreach ($this->series as $serie) {
                $valor = $serie['valores'][$indice] ?? null;
                $fila[] = $valor === null ? '—' : $this->formatear((float) $valor, true);
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * Calcula el tope y el paso del eje vertical: usa el máximo fijo o redondea el mayor valor a 1, 2 o 5 por
     * potencia de diez, con no más de cinco intervalos.
     *
     * @return array{0: float, 1: float}
     */
    private function escalaVertical(): array
    {
        $mayor = 0.0;

        foreach ($this->series as $serie) {
            foreach ($serie['valores'] as $valor) {
                $mayor = max($mayor, (float) $valor);
            }
        }

        $objetivo = $this->maximo ?? max($mayor, 1.0);

        // Las cantidades (sin unidad) usan pasos enteros; los porcentajes pueden usar décimas.
        $potencias = $this->unidad === '' ? [1, 10, 100, 1000, 10000] : [0.1, 1, 10, 100, 1000];
        $candidatos = [];

        foreach ([1, 2, 5] as $multiplo) {
            foreach ($potencias as $potencia) {
                $paso = $multiplo * $potencia;

                if ($objetivo / $paso <= 5.0001) {
                    $candidatos[] = $paso;
                }
            }
        }

        $paso = $candidatos === [] ? $objetivo / 4 : min($candidatos);
        $limite = $this->maximo ?? ceil($objetivo / $paso) * $paso;

        return [(float) $limite, (float) $paso];
    }

    /**
     * Da formato a un valor: enteros sin decimales y, si los hay, hasta uno; con la unidad si es una etiqueta.
     */
    private function formatear(float $valor, bool $conUnidad = false): string
    {
        $texto = abs($valor - round($valor)) < 0.005
            ? (string) (int) round($valor)
            : rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.');

        return $conUnidad && $this->unidad !== '' ? $texto.' '.$this->unidad : $texto;
    }

    public function render(): View
    {
        return view('components.grafica-barras');
    }
}
