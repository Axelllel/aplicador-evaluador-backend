<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAttemptTest extends TestCase
{
    #[DataProvider('invalidCredentials')]
    public function test_start_rejects_missing_or_invalid_credentials_with_422(array $body, array $errors): void
    {
        DB::shouldReceive('selectOne')->never();
        $this->postJson('/api/v1/student/exams/1/start', $body)
            ->assertUnprocessable()->assertJsonValidationErrors($errors);
    }

    public static function invalidCredentials(): array
    {
        return [
            'id is not a credential' => [['alumno_id' => 14], ['folio_codigo', 'codigo_acceso']],
            'malformed PIN' => [['folio_codigo' => 'FOLIO123', 'codigo_acceso' => '123456'], ['codigo_acceso']],
            'malformed folio' => [['folio_codigo' => "' OR 1=1 --", 'codigo_acceso' => 'ABCD27'], ['folio_codigo']],
        ];
    }

    public function test_missing_migration_returns_actionable_503_without_sql_or_credentials(): void
    {
        $error = new \PDOException('SQL with confidential parameters');
        $error->errorInfo = ['42883', null, 'function missing'];
        DB::shouldReceive('selectOne')->once()->andThrow(new QueryException('pgsql', 'select', [], $error));

        $this->postJson('/api/v1/student/exams/1/start', ['folio_codigo' => 'FOLIO123', 'codigo_acceso' => 'ABCD27'])
            ->assertServiceUnavailable()->assertJsonPath('code', 'MIGRATION_REQUIRED')
            ->assertDontSee('confidential')->assertDontSee('ABCD27');
    }

    public function test_pin_guesses_are_limited_to_five_per_minute(): void
    {
        DB::shouldReceive('selectOne')->never();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/student/exams/1/start')->assertUnprocessable();
        }
        $this->postJson('/api/v1/student/exams/1/start')->assertTooManyRequests();
    }
}
