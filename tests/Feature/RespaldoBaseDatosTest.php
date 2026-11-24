<?php

/**
 * Archivo: RespaldoBaseDatosTest.php
 * Módulo: tests/Feature
 * Descripción: Ejecuta respaldo cifrado y restauración real en PostgreSQL desechable.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Recuperación
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RespaldoBaseDatosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

class RespaldoBaseDatosTest extends TestCase
{
    public function test_real_encrypted_backup_restores_answers_routines_and_rls_and_refuses_to_overwrite(): void
    {
        $port = getenv('BACKUP_TEST_PG_PORT');
        $bin = getenv('BACKUP_TEST_PG_BIN');
        if (! $port || ! $bin) {
            $this->markTestSkipped('Requiere PostgreSQL y clientes pg_dump/pg_restore desechables.');
        }
        $admin = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=postgres', 'postgres');
        $admin->exec('DROP DATABASE IF EXISTS bbb_backup_testing');
        $admin->exec('DROP DATABASE IF EXISTS bbb_restore_testing');
        $admin->exec('CREATE DATABASE bbb_backup_testing');
        $admin->exec('CREATE DATABASE bbb_restore_testing');
        $source = new PDO('pgsql:host=127.0.0.1;port='.(int) $port.';dbname=bbb_backup_testing', 'postgres');
        $source->exec("CREATE SCHEMA auth; CREATE TABLE auth.users(id uuid PRIMARY KEY); DO $$ BEGIN IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='anon') THEN CREATE ROLE anon; END IF; IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='authenticated') THEN CREATE ROLE authenticated; END IF; END $$");
        foreach (glob(base_path('supabase/migrations/*.sql')) as $file) {
            $sql = str_replace('CREATE EXTENSION IF NOT EXISTS pgcrypto;', '', file_get_contents($file));
            if (str_contains($file, 'seguridad_roles') && $source->query("SELECT 1 FROM pg_roles WHERE rolname='laravel_app'")->fetchColumn()) {
                $sql = str_replace('CREATE ROLE laravel_app', 'ALTER ROLE laravel_app', $sql);
            }
            $source->exec($sql);
        }
        $source->exec("INSERT INTO auth.users VALUES('11111111-1111-4111-8111-111111111111');
INSERT INTO public.docente(id,nombre,correo) VALUES('11111111-1111-4111-8111-111111111111','Docente de prueba','prueba@example.test');
INSERT INTO public.grupo(id,docente_id,materia,folio_codigo) VALUES(1,'11111111-1111-4111-8111-111111111111','Curso de prueba','PRUEBA');
INSERT INTO public.alumno(id,nombre) VALUES(14,'Alumno de prueba');
INSERT INTO public.examen(id,grupo_id,nombre,fecha_apertura,fecha_cierre,orden,escala,plataforma_permitida) VALUES(1,1,'Prueba',now()-interval '1 hour',now()+interval '1 hour','fijo','0-10','ambas');
INSERT INTO public.pregunta(id,docente_id,tipo,enunciado,respuesta_modelo) VALUES(1,'11111111-1111-4111-8111-111111111111','abierta','Define variable','Valor modificable');
INSERT INTO public.apartado(id,examen_id,tipo,cantidad_preguntas) VALUES(1,1,'abierta',1);
INSERT INTO public.examen_pregunta(id,apartado_id,pregunta_id,tiempo_segundos,ponderacion) VALUES(1,1,1,60,100);
INSERT INTO public.intento_examen(id,examen_id,alumno_id,estado) VALUES(1,1,14,'finalizado');
INSERT INTO public.respuesta_alumno(intento_id,examen_pregunta_id,respuesta_texto) VALUES(1,1,'Respuesta confirmada antes del respaldo.');");
        $directory = sys_get_temp_dir().'/bbb-backup-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory, 0700);
        File::makeDirectory($directory.'/keys', 0700);
        file_put_contents($directory.'/secret', bin2hex(random_bytes(32)));
        chmod($directory.'/secret', 0600);
        config(['database.connections.backup' => ['driver' => 'pgsql', 'url' => "postgresql://postgres@127.0.0.1:$port/bbb_backup_testing", 'sslmode' => 'disable', 'charset' => 'utf8'],
            'database.connections.backup_restore' => ['driver' => 'pgsql', 'url' => "postgresql://postgres@127.0.0.1:$port/bbb_restore_testing", 'sslmode' => 'disable', 'charset' => 'utf8'],
            'backup.directory' => $directory.'/archives',
            'backup.key_file' => $directory.'/secret',
            'backup.pg_dump' => $bin.'/pg_dump', 'backup.pg_dumpall' => $bin.'/pg_dumpall', 'backup.pg_restore' => $bin.'/pg_restore']);
        DB::purge('backup');
        DB::purge('backup_restore');
        try {
            $service = app(RespaldoBaseDatosService::class);
            $archive = $service->backup();
            $this->assertFileExists($archive.'/database.dump.enc');
            $this->assertFileDoesNotExist($archive.'/database.dump');
            $this->assertStringNotContainsString('Respuesta confirmada', file_get_contents($archive.'/database.dump.enc'));
            // Simula la pérdida de datos de origen después de terminar la copia; el destino conserva la instantánea.
            $source->exec('DELETE FROM public.respuesta_alumno');
            $result = $service->verify($archive);
            $this->assertTrue($result['restauracion_verificada']);
            $this->assertSame(1, $result['respuestas_verificadas']);
            $this->assertTrue($result['objetivo_rto_30_min_en_esta_prueba']);
            $this->assertSame('Respuesta confirmada antes del respaldo.', DB::connection('backup_restore')->selectOne('SELECT respuesta_texto FROM public.respuesta_alumno')->respuesta_texto);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('La base de restauración debe estar vacía.');
            $service->verify($archive);
        } finally {
            DB::disconnect('backup');
            DB::disconnect('backup_restore');
            File::deleteDirectory($directory);
        }
    }
}
