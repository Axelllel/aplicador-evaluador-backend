<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RespaldoBaseDatosService;
use Illuminate\Console\Command;
use Throwable;

class RespaldarBaseDatos extends Command
{
    protected $signature = 'bd:respaldar';

    protected $description = 'Genera un respaldo consistente cifrado con retención de 30 días.';

    public function handle(RespaldoBaseDatosService $backup): int
    {
        try {
            $this->info($backup->backup());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Respaldo no completado. Verifica la conexión independiente, las herramientas, el archivo de clave y el destino.');

            return self::FAILURE;
        }
    }
}
