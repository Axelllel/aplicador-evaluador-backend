<?php

/**
 * Archivo: ExportacionResultadosTest.php
 * Módulo: tests/Feature
 * Descripción: Verifica contenido y seguridad de los archivos exportados.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Exportaciones
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ExportacionResultadosService;
use DOMDocument;
use Tests\TestCase;
use ZipArchive;

class ExportacionResultadosTest extends TestCase
{
    public function test_excel_preserves_accents_and_does_not_execute_names_as_formulas(): void
    {
        $name = '=HYPERLINK("https://example.test") <Ana & Pérez>';
        $response = app(ExportacionResultadosService::class)->download(['id' => 2, 'nombre' => 'Examen'], [[
            'alumno_id' => 14, 'nombre' => $name, 'estado' => 'finalizado', 'calificacion' => 8.5,
            'escala' => '0-10', 'fecha_entrega' => null,
        ]], 'xlsx');
        $path = tempnam(sys_get_temp_dir(), 'bbb-test-');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($xml));
            $this->assertStringContainsString($name, $document->textContent);
            $this->assertSame(0, $document->getElementsByTagName('f')->length);
        } finally {
            unlink($path);
        }
    }

    public function test_pdf_contains_all_students_across_multiple_pages(): void
    {
        $rows = [];
        for ($i = 1; $i <= 80; $i++) {
            $rows[] = ['alumno_id' => $i, 'nombre' => 'Alumno '.$i,
                'estado' => 'finalizado', 'calificacion' => 7.5, 'escala' => '0-10'];
        }
        $pdf = app(ExportacionResultadosService::class)->download(['id' => 2, 'nombre' => 'Examen'], $rows, 'pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Alumno 1 |', $pdf);
        $this->assertStringContainsString('Alumno 80 |', $pdf);
        $this->assertStringContainsString('/Count 3', $pdf);
    }
}
