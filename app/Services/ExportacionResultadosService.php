<?php

/**
 * Archivo: ExportacionResultadosService.php
 * Módulo: app/Services
 * Descripción: Exporta calificaciones autorizadas en Excel y PDF.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Integración de requisitos pendientes
 */

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ExportacionResultadosService
{
    public function download(array $exam, iterable $results, string $format): Response
    {
        $rows = [['Alumno ID', 'Nombre', 'Estado', 'Calificación', 'Escala', 'Entrega']];
        foreach ($results as $row) {
            $rows[] = [$row['alumno_id'], $row['nombre'], $row['estado'],
                $row['calificacion'] ?? 'Pendiente', $row['escala'], $row['fecha_entrega'] ?? ''];
        }
        $body = $format === 'xlsx' ? $this->xlsx($rows) : $this->pdf($exam['nombre'], $rows);

        return response($body, 200, [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf',
            'Content-Disposition' => 'attachment; filename="resultados-examen-'.(int) $exam['id'].'.'.$format.'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function xlsx(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="40" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="5" width="12" customWidth="1"/><col min="6" max="6" width="32" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';
            foreach ($row as $column => $value) {
                $reference = chr(65 + $column).($index + 1);
                // inlineStr evita fórmulas incluso si el nombre empieza por =, +, - o @.
                $xml .= is_int($value) || is_float($value)
                    ? '<c r="'.$reference.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'bbb-export-');
        if ($path === false) {
            throw new RuntimeException('No se pudo preparar la exportación.');
        }
        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo Excel.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Calificaciones" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    private function pdf(string $title, array $rows): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        $headers = array_shift($rows);
        $body = [];
        foreach ($rows as $row) {
            $line = preg_replace('/[\r\n\t]+/u', ' ', implode(' | ', array_map('strval', $row)));
            array_push($body, ...mb_str_split($line, 95));
        }
        foreach (array_chunk($body ?: ['Sin intentos registrados.'], 39) as $page => $chunk) {
            $pageId = 4 + $page * 2;
            $contentId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $lines = ['BigBitByte | Calificaciones', ...mb_str_split($title, 95), 'Página '.($page + 1), implode(' | ', $headers), '', ...$chunk];
            $stream = "BT /F1 9 Tf 36 806 Td 16 TL\n";
            foreach ($lines as $line) {
                $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $line);
                $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], (string) $text);
                $stream .= '('.$text.") Tj T*\n";
            }
            $stream .= 'ET';
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        for ($id = 1; $id < count($offsets); $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf.'trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
