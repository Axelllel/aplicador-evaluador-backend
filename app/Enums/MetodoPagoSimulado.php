<?php

declare(strict_types=1);

namespace App\Enums;

enum MetodoPagoSimulado: string
{
    case Tarjeta = 'tarjeta';
    case Spei = 'spei';
}
