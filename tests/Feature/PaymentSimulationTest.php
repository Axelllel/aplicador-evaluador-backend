<?php

/**
 * Archivo: PaymentSimulationTest.php
 * Módulo: tests/Feature
 * Descripción: Valida el flujo de simulación, confirmaciones y privacidad de datos de prueba.
 * Autor: Axel Rebollo Zeferino
 * Creado: 20/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Revisión de integración
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifySupabaseToken;
use App\Services\PlanDocenteService;
use App\Services\SimulacionPagoService;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConPanelFalso;
use Tests\TestCase;

class PaymentSimulationTest extends TestCase
{
    use ConPanelFalso;

    private const API = '/api/v1/teacher/payments/simulate';

    private const WEB = '/suscripcion/planes/pago/simular';

    private const OPERATION = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.payment_simulation.enabled' => true]);
        DB::purge('sqlite');
    }

    public function test_checkout_exposes_both_methods_and_does_not_refill_card_secrets(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->withSession(['_old_input' => ['numero_tarjeta' => '4242424242424242', 'cvv' => '123']])
            ->get('/suscripcion/planes/pago?grupos=3&alumnos=25&preguntas=150')
            ->assertOk()->assertSee('Activar plan')->assertDontSee('Simular y activar mi plan')->assertSee('Transferencia SPEI')
            ->assertDontSee('4242 4242 4242 4242')->assertDontSee('Se valida el formato de los datos.')->assertDontSee('No se crearán grupos automáticamente.')->assertSee('$181.00')->assertDontSee('value="4242424242424242"', false)->assertDontSee('value="123"', false);
    }

    public function test_api_requires_authentication(): void
    {
        $this->postJson(self::API, $this->card())->assertUnauthorized();
    }

    public function test_card_fields_and_capacity_are_validated_before_payment(): void
    {
        $this->apiIdentity();
        $this->mock(SimulacionPagoService::class)->shouldNotReceive('confirmar');
        $input = array_replace($this->card(), ['grupos' => 11, 'numero_tarjeta' => '4111111111111112', 'cvv' => '1', 'correo_facturacion' => 'bad']);
        $this->postJson(self::API, $input)->assertUnprocessable()->assertJsonValidationErrors(['grupos', 'numero_tarjeta', 'cvv', 'correo_facturacion']);
        $this->postJson(self::API, array_replace($this->card(), ['vencimiento' => '01/20']))->assertUnprocessable()->assertJsonValidationErrors('vencimiento');
    }

    public function test_spei_has_its_own_required_fields_and_checksum(): void
    {
        $this->apiIdentity();
        $this->postJson(self::API, array_replace($this->card(), ['metodo_pago' => 'spei', 'clabe' => '032180000118359710']))
            ->assertUnprocessable()->assertJsonValidationErrors(['banco', 'clabe']);
    }

    public function test_api_uses_validated_capacity_without_client_price_or_teacher_id(): void
    {
        $this->apiIdentity();
        $this->mock(SimulacionPagoService::class)->shouldReceive('confirmar')->once()
            ->with(Mockery::on(fn (array $data): bool => ! isset($data['monto']) && ! isset($data['docente_id']) && $data['grupos'] === 3))
            ->andReturn($this->receipt());
        $this->postJson(self::API, $this->card() + ['monto' => 0.01, 'docente_id' => 'another-teacher'])
            ->assertCreated()->assertJsonPath('data.grupos', 3)->assertJsonPath('data.monto', '181.00')
            ->assertJsonMissingPath('data.numero_tarjeta')->assertJsonMissingPath('data.cvv');
    }

    public function test_disabled_simulation_does_not_call_persistence(): void
    {
        $this->apiIdentity();
        config(['services.payment_simulation.enabled' => false]);
        $this->mock(SimulacionPagoService::class)->shouldNotReceive('confirmar');
        $this->postJson(self::API, $this->card())->assertForbidden();
    }

    public function test_persistence_receives_only_last_four_not_card_number_or_cvv(): void
    {
        DB::shouldReceive('getConnections')->andReturn([]);
        DB::shouldReceive('selectOne')->once()->with(Mockery::type('string'), Mockery::on(function (array $bindings): bool {
            $json = json_encode($bindings);

            return ! str_contains($json, '4242424242424242') && ! str_contains($json, 'cvv')
                && str_contains($json, '4242');
        }))->andReturn((object) ['datos' => json_encode($this->receipt())]);
        $this->assertSame(3, (new SimulacionPagoService)->confirmar($this->card())['grupos']);
    }

    public function test_successful_web_payment_redirects_to_a_receipt_with_purchased_capacity(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->prepararLecturas();
        $this->api->responder('POST', '/teacher/payments/simulate', ['data' => $this->receipt()], 201);
        $this->post(self::WEB, $this->card())->assertRedirect(route('panel.suscripcion'))
            ->assertSessionHas('pago_confirmado.grupos', 3)->assertSessionMissing('_old_input.numero_tarjeta')->assertSessionMissing('_old_input.cvv');
        $this->get('/suscripcion')->assertOk()->assertSee('Plan activado exitosamente')->assertSee('3 grupos activos')->assertSee('SIM-'.self::OPERATION);
    }

    public function test_validation_errors_do_not_flash_payment_secrets(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->post(self::WEB, array_replace($this->card(), ['cvv' => '1']))
            ->assertSessionHasErrors('cvv')->assertSessionHasInput('operacion_id', self::OPERATION)
            ->assertSessionMissing('_old_input.numero_tarjeta')->assertSessionMissing('_old_input.cvv');
        $this->assertSame([], $this->api->llamadas);
    }

    public function test_service_failure_keeps_the_operation_for_safe_retry_without_a_success_notice(): void
    {
        $this->prepararPanel();
        $this->conSesionDelPanel();
        $this->api->responder('POST', '/teacher/payments/simulate', ['message' => 'No se registró el pago.'], 503);
        $this->post(self::WEB, $this->card())->assertSessionHas('error', 'No se registró el pago.')
            ->assertSessionMissing('pago_confirmado')->assertSessionHasInput('operacion_id', self::OPERATION)
            ->assertSessionMissing('_old_input.numero_tarjeta')->assertSessionMissing('_old_input.cvv');
    }

    public function test_missing_migration_reports_unavailable_without_claiming_success(): void
    {
        $this->apiIdentity();
        $cause = new \PDOException('Function not installed');
        $cause->errorInfo = ['42883', null, 'Function not installed'];
        $exception = new QueryException('pgsql', 'select function', [], $cause);
        $this->mock(SimulacionPagoService::class)->shouldReceive('confirmar')->once()->andThrow($exception);
        $this->postJson(self::API, $this->card())->assertServiceUnavailable()
            ->assertJsonPath('message', 'La activación de planes todavía no está habilitada en la base de datos. No se registró el pago.')
            ->assertJsonMissingPath('data.pago_id');
    }

    public function test_current_plan_selection_ignores_an_older_longer_payment_for_another_plan(): void
    {
        $planes = collect([['id' => 1], ['id' => 2]]);
        $pagos = collect([
            ['paquete_id' => 1, 'estado' => 'confirmado', 'es_plan_actual' => false, 'fecha_inicio_vigencia' => '2020-01-01', 'fecha_fin_vigencia' => '2099-01-01'],
            ['paquete_id' => 2, 'estado' => 'confirmado', 'es_plan_actual' => true, 'fecha_inicio_vigencia' => now()->subDay()->toIso8601String(), 'fecha_fin_vigencia' => now()->addMonth()->toIso8601String()],
        ]);
        $plan = (new PlanDocenteService)->planVigente($planes, $pagos);
        $this->assertSame(2, $plan['plan']['id']);
    }

    #[DataProvider('acceptedCards')]
    public function test_simulation_accepts_different_card_formats_without_a_fixed_number(string $number, string $cvv): void
    {
        $this->apiIdentity();
        $this->mock(SimulacionPagoService::class)->shouldReceive('confirmar')->once()
            ->with(Mockery::on(fn (array $data): bool => $data['numero_tarjeta'] === str_replace([' ', '-'], '', $number)))
            ->andReturn($this->receipt());
        $this->postJson(self::API, array_replace($this->card(), ['numero_tarjeta' => $number, 'cvv' => $cvv]))
            ->assertCreated()->assertJsonPath('data.simulado', true)->assertJsonMissingPath('data.cvv');
    }

    public static function acceptedCards(): array
    {
        return [
            'visa 13' => ['4222222222222', '321'],
            'visa 16 with separators' => ['4111-1111 1111-1111', '987'],
            'mastercard' => ['2223003122003222', '456'],
            'diners 14' => ['30569309025904', '654'],
            'amex 15' => ['378282246310005', '9876'],
            'visa 19' => ['4000000000000000006', '321'],
        ];
    }

    #[DataProvider('rejectedCardFields')]
    public function test_incorrect_card_fields_cannot_activate_a_plan(array $invalid, string $field): void
    {
        $this->apiIdentity();
        $this->mock(SimulacionPagoService::class)->shouldNotReceive('confirmar');
        $this->postJson(self::API, array_replace($this->card(), $invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function rejectedCardFields(): array
    {
        return [
            'wrong checksum' => [['numero_tarjeta' => '4111111111111112'], 'numero_tarjeta'],
            'all zero' => [['numero_tarjeta' => '0000000000000000'], 'numero_tarjeta'],
            'repeated digits' => [['numero_tarjeta' => '8888888888888888'], 'numero_tarjeta'],
            'too short' => [['numero_tarjeta' => '411111111111'], 'numero_tarjeta'],
            'too long' => [['numero_tarjeta' => '41111111111111111111'], 'numero_tarjeta'],
            'letters' => [['numero_tarjeta' => '411111111111111A'], 'numero_tarjeta'],
            'amex requires four' => [['numero_tarjeta' => '378282246310005', 'cvv' => '123'], 'cvv'],
            'other cards require three' => [['numero_tarjeta' => '4111111111111111', 'cvv' => '1234'], 'cvv'],
            'cvv letters' => [['cvv' => '12A'], 'cvv'],
            'invalid month' => [['vencimiento' => '13/39'], 'vencimiento'],
        ];
    }

    public function test_card_is_valid_through_the_current_month_but_not_the_previous_one(): void
    {
        $this->apiIdentity();
        $this->mock(SimulacionPagoService::class)->shouldReceive('confirmar')->once()->andReturn($this->receipt());
        $this->postJson(self::API, array_replace($this->card(), ['vencimiento' => now()->format('m/y')]))->assertCreated();
        $this->postJson(self::API, array_replace($this->card(), ['vencimiento' => now()->subMonthNoOverflow()->format('m/y')]))->assertUnprocessable()->assertJsonValidationErrors('vencimiento');
    }

    private function apiIdentity(): void
    {
        $this->withoutMiddleware(VerifySupabaseToken::class);
        $this->actingAs(new GenericUser(['id' => self::OPERATION]));
    }

    private function card(): array
    {
        return ['operacion_id' => self::OPERATION, 'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150,
            'metodo_pago' => 'tarjeta', 'titular' => 'Docente de prueba', 'correo_facturacion' => 'prueba@example.test',
            'numero_tarjeta' => '4242424242424242', 'vencimiento' => '12/39', 'cvv' => '123'];
    }

    private function receipt(): array
    {
        return ['pago_id' => 2, 'paquete_id' => 4, 'grupos' => 3, 'alumnos' => 25, 'preguntas' => 150,
            'monto' => '181.00', 'referencia' => 'SIM-'.self::OPERATION, 'simulado' => true, 'reutilizado' => false];
    }
}
