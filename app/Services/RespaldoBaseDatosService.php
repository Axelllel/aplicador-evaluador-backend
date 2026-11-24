<?php

/**
 * Archivo: RespaldoBaseDatosService.php
 * Módulo: app/Services
 * Descripción: Respalda una instantánea consistente cifrada y comprueba su restauración aislada.
 * Autor: Axel Rebollo Zeferino
 * Creado: 22/11/2026
 * Modificado: 22/11/2026 · Equipo BigBitByte · Recuperación
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RespaldoBaseDatosService
{
    private function environment(Connection $connection): array
    {
        $config = $connection->getConfig();

        return ['PGHOST' => $config['host'], 'PGPORT' => (string) ($config['port'] ?? 5432),
            'PGDATABASE' => $config['database'], 'PGUSER' => $config['username'], 'PGPASSWORD' => $config['password'] ?? '',
            'PGSSLMODE' => $config['sslmode'] ?? 'require', 'PGCONNECT_TIMEOUT' => '10'];
    }

    private function run(array $arguments, array $environment = []): string
    {
        $process = new Process($arguments, null, $environment);
        $process->setTimeout((int) config('backup.timeout'));
        $process->run();
        if (! $process->isSuccessful()) {
            // Ni la salida del proveedor ni el entorno (con contraseñas) pasan a logs.
            throw new RuntimeException('La operación de respaldo/restauración falló. Revisa permisos, herramientas y conectividad.');
        }

        return trim($process->getOutput());
    }

    private function key(): string
    {
        $file = config('backup.key_file');
        if (! extension_loaded('sodium') || ! is_string($file) || ! is_readable($file) || (fileperms($file) & 0077) !== 0) {
            throw new RuntimeException('Configura Sodium y un archivo de clave independiente con permisos 0600.');
        }
        $hex = trim(file_get_contents($file));
        if (! preg_match('/^[a-fA-F0-9]{64}$/', $hex)) {
            throw new RuntimeException('La clave debe contener 32 bytes aleatorios en hexadecimal.');
        }

        return hex2bin($hex);
    }

    private function encrypt(string $input, string $output, string $label): void
    {
        $key = $this->key();
        [$state,$header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        sodium_memzero($key);
        $source = fopen($input, 'rb');
        $target = fopen($output, 'xb');
        if (! $source || ! $target) {
            throw new RuntimeException('No se pudo abrir el archivo de respaldo.');
        }
        try {
            if (fwrite($target, 'BBB1'.$header) !== 4 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('Respaldo incompleto.');
            }
            $chunk = fread($source, 4096);
            do {
                $next = fread($source, 4096);
                if ($chunk === false || $next === false) {
                    throw new RuntimeException('No se pudo leer el respaldo.');
                }
                $final = $next === '';
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, $label, $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0);
                if (fwrite($target, $cipher) !== strlen($cipher)) {
                    throw new RuntimeException('No se pudo completar el respaldo cifrado.');
                }
                $chunk = $next;
            } while (! $final);
        } finally {
            fclose($source);
            fclose($target);
            sodium_memzero($state);
        }
    }

    private function decrypt(string $input, string $output, string $label): void
    {
        $source = fopen($input, 'rb');
        $target = fopen($output, 'xb');
        if (! $source || ! $target) {
            throw new RuntimeException('No se pudo abrir el respaldo.');
        }
        $state = null;
        try {
            if (fread($source, 4) !== 'BBB1') {
                throw new RuntimeException('Formato de respaldo inválido.');
            }
            $header = fread($source, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $key = $this->key();
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            sodium_memzero($key);
            $final = false;
            while (! feof($source)) {
                $cipher = fread($source, 4096 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                if ($cipher === '' || $cipher === false) {
                    break;
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher, $label);
                if ($result === false) {
                    throw new RuntimeException('El respaldo fue alterado o la clave no corresponde.');
                }
                [$plain,$tag] = $result;
                if (fwrite($target, $plain) !== strlen($plain)) {
                    throw new RuntimeException('Restauración incompleta.');
                }
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $final = true;
                    if (fread($source, 1) !== '') {
                        throw new RuntimeException('El respaldo contiene datos adicionales inválidos.');
                    }
                    break;
                }
            }
            if (! $final) {
                throw new RuntimeException('El respaldo está truncado.');
            }
        } finally {
            fclose($source);
            fclose($target);
            if (is_string($state)) {
                sodium_memzero($state);
            }
        }
    }

    private function evidence(Connection $connection): array
    {
        $answers = $connection->select('SELECT r.id, md5(row_to_json(r)::text) AS huella FROM public.respuesta_alumno r ORDER BY r.id');
        $tables = $connection->select("SELECT n.nspname AS esquema,c.relname AS tabla,c.relrowsecurity AS rls,c.relforcerowsecurity AS forzada FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname IN ('public','auth') AND c.relkind='r' ORDER BY 1,2");
        $routines = $connection->select("SELECT n.nspname AS esquema,p.proname,pg_get_function_identity_arguments(p.oid) AS argumentos,md5(pg_get_functiondef(p.oid)) AS huella FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname IN ('public','auth') AND p.prokind='f' ORDER BY 1,2,3");
        $policies = $connection->select("SELECT schemaname,tablename,policyname,permissive,roles,cmd,qual,with_check FROM pg_policies WHERE schemaname IN ('public','auth') ORDER BY 1,2,3");
        $grants = $connection->select("SELECT grantee,table_schema,table_name,privilege_type,is_grantable FROM information_schema.table_privileges WHERE table_schema IN ('public','auth') AND grantee IN ('PUBLIC','anon','authenticated','laravel_app') ORDER BY 1,2,3,4");
        $indexes = $connection->select("SELECT schemaname,tablename,indexname,indexdef FROM pg_indexes WHERE schemaname IN ('public','auth') ORDER BY 1,2,3");

        return json_decode(json_encode(compact('answers', 'tables', 'routines', 'policies', 'grants', 'indexes'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function backup(): string
    {
        $connection = DB::connection('backup');
        if (! config('database.connections.backup.url') || ! config('backup.directory')) {
            throw new RuntimeException('Configura conexión de respaldo independiente y destino externo.');
        }
        $key = $this->key();
        sodium_memzero($key);
        $root = rtrim(config('backup.directory'), '/');
        File::ensureDirectoryExists($root, 0700);
        $name = 'bbb-backup-'.gmdate('Ymd-His').'-'.Str::uuid();
        $temporary = $root.'/.'.$name;
        $final = $root.'/'.$name;
        File::makeDirectory($temporary, 0700);
        $environment = $this->environment($connection);
        try {
            $connection->beginTransaction();
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $snapshot = $connection->selectOne('SELECT pg_export_snapshot() AS id')->id;
            $evidence = $this->evidence($connection);
            $this->run([config('backup.pg_dump'), '--format=custom', '--no-owner', '--schema=public', '--schema=auth', '--schema=extensions', '--snapshot='.$snapshot, '--file='.$temporary.'/database.dump'], $environment);
            $connection->commit();
            $roles = $this->run([config('backup.pg_dumpall'), '--roles-only', '--no-role-passwords'], $environment);
            file_put_contents($temporary.'/roles.sql', $roles, LOCK_EX);
            $manifest = ['creado_utc' => gmdate(DATE_ATOM), 'alcance' => ['public', 'auth', 'extensions'],
                'postgresql' => $connection->selectOne('SHOW server_version')->server_version,
                'pg_dump' => $this->run([config('backup.pg_dump'), '--version']),
                'database_sha256' => hash_file('sha256', $temporary.'/database.dump'),
                'roles_sha256' => hash_file('sha256', $temporary.'/roles.sql'), 'evidencia' => $evidence];
            file_put_contents($temporary.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX);
            foreach (['database.dump', 'roles.sql', 'manifest.json'] as $file) {
                $this->encrypt($temporary.'/'.$file, $temporary.'/'.$file.'.enc', $file);
                unlink($temporary.'/'.$file);
            }
            file_put_contents($temporary.'/.complete', gmdate(DATE_ATOM), LOCK_EX);
            if (! rename($temporary, $final)) {
                throw new RuntimeException('No se pudo completar el respaldo.');
            }
            foreach (File::directories($root) as $directory) {
                if (! is_link($directory) && str_starts_with(basename($directory), 'bbb-backup-') && is_file($directory.'/.complete') && filemtime($directory.'/.complete') < time() - 30 * 86400) {
                    File::deleteDirectory($directory);
                }
            }

            return $final;
        } catch (Throwable $exception) {
            if ($connection->transactionLevel()) {
                $connection->rollBack();
            }
            File::deleteDirectory($temporary);
            throw $exception;
        }
    }

    public function verify(string $directory): array
    {
        $start = microtime(true);
        $connection = DB::connection('backup_restore');
        // No hay opción --force: esta herramienta jamás restaura encima de la aplicación.
        if (! config('database.connections.backup_restore.url') || ! preg_match('/^bbb_restore_[a-z0-9_]+$/', (string) $connection->getDatabaseName())) {
            throw new RuntimeException('Usa una conexión independiente y una BD vacía llamada bbb_restore_*.');
        }
        $count = $connection->selectOne("SELECT (SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname IN ('public','auth')) + (SELECT count(*) FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname IN ('public','auth')) AS total")->total;
        if ((int) $count !== 0) {
            throw new RuntimeException('La base de restauración debe estar vacía.');
        }
        if (! is_file($directory.'/.complete')) {
            throw new RuntimeException('El respaldo no está completo.');
        }
        $temporary = sys_get_temp_dir().'/bbb-restore-'.Str::uuid();
        File::makeDirectory($temporary, 0700);
        $environment = $this->environment($connection);
        try {
            foreach (['database.dump', 'roles.sql', 'manifest.json'] as $file) {
                $this->decrypt($directory.'/'.$file.'.enc', $temporary.'/'.$file, $file);
            }
            $manifest = json_decode(file_get_contents($temporary.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals($manifest['database_sha256'], hash_file('sha256', $temporary.'/database.dump')) || ! hash_equals($manifest['roles_sha256'], hash_file('sha256', $temporary.'/roles.sql'))) {
                throw new RuntimeException('La integridad del respaldo no coincide con su manifiesto.');
            }
            // Los roles se revisan y aprovisionan por separado; no se ejecuta SQL global automáticamente.
            $this->run([config('backup.pg_restore'), '--exit-on-error', '--clean', '--if-exists', '--no-owner', '--single-transaction', '--dbname='.$connection->getDatabaseName(), $temporary.'/database.dump'], $environment);
            $evidence = $this->evidence($connection);
            if ($evidence !== $manifest['evidencia']) {
                throw new RuntimeException('La restauración no conserva respuestas, rutinas, RLS o políticas.');
            }
            $duration = round(microtime(true) - $start, 3);

            return ['restauracion_verificada' => true, 'respuestas_verificadas' => count($evidence['answers']),
                'duracion_verificacion_segundos' => $duration, 'objetivo_rto_30_min_en_esta_prueba' => $duration <= 1800,
                'alcance_rpo' => 'Coincidencia exacta con la instantánea del respaldo. No demuestra RPO cero ante un incidente posterior.'];
        } finally {
            File::deleteDirectory($temporary);
        }
    }
}
