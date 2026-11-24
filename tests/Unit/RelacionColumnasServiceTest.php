<?php

/**
 * Archivo: RelacionColumnasServiceTest.php
 * Módulo: tests/Unit
 * Descripción: Comprueba la presentación de selecciones parciales, referencias y formatos históricos.
 * Autor: Axel Rebollo Zeferino
 * Creado: 23/11/2026
 * Modificado: 23/11/2026 · Equipo BigBitByte · Revisión de columnas
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RelacionColumnasService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RelacionColumnasServiceTest extends TestCase
{
    #[DataProvider('answers')]
    public function test_review_preserves_the_student_selection_and_the_frozen_reference(string $answer, bool $recognized, ?string $selected): void
    {
        $pairs = [['izquierda' => 'Variable', 'derecha' => 'Referencia cambiada'], ['izquierda' => 'Constante', 'derecha' => 'Otra referencia']];
        $review = (new RelacionColumnasService)->revision($answer, "Variable = Valor modificable\nConstante = Valor fijo", $pairs);
        $this->assertSame($recognized, $review['formato_reconocido']);
        $this->assertSame($selected, $review['filas'][0]['respuesta']);
        $this->assertSame('Valor modificable', $review['filas'][0]['modelo']);
    }

    public static function answers(): array
    {
        return [
            'array' => ['["Valor fijo", "Valor fijo"]', true, 'Valor fijo'],
            'object' => ['{"Variable":"Valor modificable","Constante":""}', true, 'Valor modificable'],
            'partial' => ['["", "Valor fijo"]', true, null],
            'unknown' => ['respuesta antigua', false, null],
            'invalid entries' => ['[{"id":1}]', false, null],
        ];
    }

    public function test_missing_or_ambiguous_reference_is_left_for_manual_review(): void
    {
        $this->assertNull((new RelacionColumnasService)->revision('[]', null, []));
        $pairs = [['izquierda' => 'Uno', 'derecha' => 'A'], ['izquierda' => 'Dos', 'derecha' => 'B']];
        $this->assertNull((new RelacionColumnasService)->revision('[]', "Uno = A\nUno = B", $pairs));
    }
}
