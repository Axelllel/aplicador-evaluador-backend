<?php

/**
 * Archivo: AdministracionVistaService.php
 * Módulo: app/Services
 * Descripción: Prepara indicadores, filtros y series administrativas exclusivamente con registros de la API.
 * Autor: Axel Rebollo Zeferino
 * Creado: 24/11/2026
 * Modificado: 24/11/2026 · Equipo BigBitByte · Panel administrativo
 */

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AdministracionVistaService
{
    public function docentes(array $registros): Collection
    {
        return collect($registros)->map(fn (array $row): array => $row + [
            'suspendido_en' => null, 'estado_pago' => 'inactivo',
        ])->map(fn (array $row): array => $row + [
            'iniciales' => $this->iniciales($row['nombre']),
            'estado_cuenta' => $row['suspendido_en'] ? 'suspendido' : 'habilitado',
        ])->values();
    }

    public function pagos(array $registros): Collection
    {
        return collect($registros)->map(function (array $row): array {
            $fecha = $row['fecha'] ?? $row['fecha_inicio_vigencia'] ?? null;

            return $row + [
                'folio' => 'PAG-'.str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT),
                'importe' => $this->moneda((float) $row['monto']),
                'fecha_texto' => $this->fecha($fecha),
                'inicio_texto' => $this->fecha($row['fecha_inicio_vigencia'] ?? null),
                'fin_texto' => $this->fecha($row['fecha_fin_vigencia'] ?? null),
                'mes_registro' => $fecha ? CarbonImmutable::parse($fecha, 'America/Mexico_City')->setTimezone('America/Mexico_City')->format('Y-m') : null,
                'iniciales' => $this->iniciales($row['nombre']),
            ];
        })->sortByDesc('id')->values();
    }

    public function filtrarDocentes(Collection $rows, array $filters): Collection
    {
        return $rows->filter(fn (array $row): bool => $this->coincide($filters['q'] ?? '', $row['nombre'].' '.$row['correo'])
            && (($filters['estado'] ?? '') === '' || $row['estado_cuenta'] === $filters['estado']))->values();
    }

    public function filtrarPagos(Collection $rows, array $filters): Collection
    {
        return $rows->filter(fn (array $row): bool => $this->coincide($filters['q'] ?? '', $row['nombre'].' '.$row['folio'].' '.$row['id'])
            && (($filters['estado'] ?? '') === '' || $row['estado'] === $filters['estado'])
            && (($filters['mes'] ?? '') === '' || $row['mes_registro'] === $filters['mes']))->values();
    }

    public function resumen(Collection $docentes, Collection $pagos): array
    {
        return [
            'docentes' => $docentes->count(),
            'habilitados' => $docentes->where('estado_cuenta', 'habilitado')->count(),
            'suspendidos' => $docentes->where('estado_cuenta', 'suspendido')->count(),
            'pagos' => $pagos->count(),
            'pendientes' => $pagos->where('estado', 'pendiente')->count(),
            'confirmados' => $pagos->where('estado', 'confirmado')->count(),
            'rechazados' => $pagos->where('estado', 'rechazado')->count(),
            'monto_confirmado' => $this->moneda((float) $pagos->where('estado', 'confirmado')->sum('monto')),
        ];
    }

    public function serie(Collection $pagos): array
    {
        $mes = CarbonImmutable::now('America/Mexico_City')->startOfMonth();
        $series = [];
        for ($n = 5; $n >= 0; $n--) {
            $fecha = $mes->subMonths($n);
            $importe = (float) $pagos->where('mes_registro', $fecha->format('Y-m'))->where('estado', 'confirmado')->sum('monto');
            $series[] = ['mes' => $fecha->locale('es')->translatedFormat('M'), 'periodo' => $fecha->format('Y-m'),
                'valor' => $importe, 'importe' => $this->moneda($importe)];
        }
        $max = max(1, ...array_column($series, 'valor'));

        return ['meses' => array_map(fn (array $point): array => $point + ['altura' => round($point['valor'] / $max * 150, 2)], $series),
            'maximo' => $this->moneda($max), 'mitad' => $this->moneda($max / 2),
            'total' => $this->moneda(array_sum(array_column($series, 'valor')))];
    }

    public function moneda(float $amount): string
    {
        return '$'.number_format($amount, 2, '.', ',');
    }

    private function coincide(string $query, string $text): bool
    {
        return $query === '' || str_contains(mb_strtolower($text), mb_strtolower(trim($query)));
    }

    private function iniciales(string $name): string
    {
        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2))));
    }

    private function fecha(?string $date): string
    {
        return $date ? CarbonImmutable::parse($date, 'America/Mexico_City')->setTimezone('America/Mexico_City')->format('d/m/Y') : '—';
    }
}
