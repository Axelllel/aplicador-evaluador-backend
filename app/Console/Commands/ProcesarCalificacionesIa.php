<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\IaException;
use App\Models\Docente;
use App\Services\AsistenciaIaService;
use App\Services\DatabaseUserContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcesarCalificacionesIa extends Command
{
    protected $signature = 'examenes:procesar-calificaciones {--limite=3}';

    protected $description = 'Procesa calificación objetiva y propuestas de IA reservadas sin duplicar consultas.';

    public function handle(DatabaseUserContext $context, AsistenciaIaService $ai): int
    {
        $limit = filter_var($this->option('limite'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
        if ($limit === false) {
            return self::INVALID;
        }
        $useAi = (bool) config('ai.enabled') && (bool) config('ai.automatic_grading') && filled(config('ai.key'));
        for ($n = 0; $n < $limit; $n++) {
            $job = DB::selectOne('SELECT * FROM public.fn_tomar_trabajo_calificacion(?::boolean)', [$useAi ? 'true' : 'false'], false);
            if (! $job) {
                break;
            }
            $error = null;
            try {
                // La identidad procede de la tarea interna, nunca de un parámetro del cliente.
                $teacher = $context->ejecutarConDocenteVerificado($job->docente_id, fn () => Docente::find($job->docente_id));
                if (! $teacher) {
                    throw new IaException('Docente no disponible.', 404);
                }
                Auth::setUser($teacher);
                if ($job->tipo === 'objetivas') {
                    $context->ejecutarComoDocente(fn () => DB::selectOne('SELECT public.fn_autocalificar_intento(?::bigint)', [$job->intento_id]));
                } else {
                    $ai->grade((int) $job->respuesta_id);
                }
            } catch (IaException $exception) {
                // Una respuesta revisada manualmente ya no requiere IA.
                $error = $exception->httpStatus === 404 ? null : ($exception->httpStatus === 429 ? 'limite' : 'proveedor_no_disponible');
            } catch (Throwable $exception) {
                $error = 'procesamiento_no_completado';
            } finally {
                Auth::forgetGuards();
            }
            DB::selectOne('SELECT public.fn_resolver_trabajo_calificacion(?::bigint,?::uuid,?::text)', [$job->trabajo_id, $job->reserva, $error]);
            if ($error) {
                $this->warn('Una tarea quedó pendiente de reintento; la revisión manual sigue disponible.');
            }
        }

        return self::SUCCESS;
    }
}
