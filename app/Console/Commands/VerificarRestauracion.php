<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RespaldoBaseDatosService;
use Illuminate\Console\Command;
use Throwable;

class VerificarRestauracion extends Command
{
    protected $signature = 'bd:verificar-restauracion {directorio}';

    protected $description = 'Restaura únicamente en bbb_restore_* vacía y compara contenido y seguridad.';

    public function handle(RespaldoBaseDatosService $backup): int
    {
        try {
            $this->line(json_encode($backup->verify($this->argument('directorio')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Restauración no verificada. Conserva el respaldo y revisa conexión, permisos, herramientas y claves.');

            return self::FAILURE;
        }
    }
}
