<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CerrarIntentosVencidos extends Command
{
    protected $signature = 'examenes:cerrar-vencidos {--lotes=10}';

    protected $description = 'Finaliza intentos vencidos conservando respuestas y un único registro de cierre.';

    public function handle(): int
    {
        $batches = filter_var($this->option('lotes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($batches === false) {
            $this->error('Indica de 1 a 100 lotes.');

            return self::INVALID;
        }
        $total = 0;
        for ($batch = 0; $batch < $batches; $batch++) {
            $count = (int) DB::selectOne('SELECT public.fn_cerrar_intentos_vencidos(100) AS total', [], false)->total;
            $total += $count;
            if ($count < 100) {
                break;
            }
        }
        $this->info("Intentos finalizados: {$total}");

        return self::SUCCESS;
    }
}
