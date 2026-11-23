<?php

/**
 * Archivo: SimulacionPagoService.php
 * Módulo: app/Services
 * Descripción: Simula el pago de un plan y activa la suscripción sin cobro bancario.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 23/11/2026 · Leonardo Ballestero López · Cabecera de archivo
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SimulacionPagoService
{
    /** La función recibe únicamente datos de facturación y terminaciones de prueba. */
    public function confirmar(array $datos): array
    {
        $detalle = [
            'titular' => $datos['titular'], 'correo_facturacion' => $datos['correo_facturacion'],
            'ultimos_cuatro' => substr($datos[$datos['metodo_pago'] === 'tarjeta' ? 'numero_tarjeta' : 'clabe'], -4),
        ];
        if ($datos['metodo_pago'] === 'spei') {
            $detalle['banco'] = $datos['banco'];
        }
        $row = DB::selectOne('SELECT public.fn_confirmar_pago_simulado(CAST(? AS uuid), CAST(? AS integer), CAST(? AS integer), CAST(? AS integer), CAST(? AS text), CAST(? AS jsonb)) AS datos', [
            $datos['operacion_id'], (int) $datos['grupos'], (int) $datos['alumnos'], (int) $datos['preguntas'], $datos['metodo_pago'], json_encode($detalle, JSON_THROW_ON_ERROR),
        ]);

        return json_decode($row->datos, true, flags: JSON_THROW_ON_ERROR);
    }
}
