<?php

declare(strict_types=1);

return [
    'key_file' => env('BACKUP_KEY_FILE'),
    'enabled' => env('BACKUP_ENABLED', false),
    'directory' => env('BACKUP_DIRECTORY'),
    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'pg_dumpall' => env('BACKUP_PG_DUMPALL', 'pg_dumpall'),
    'pg_restore' => env('BACKUP_PG_RESTORE', 'pg_restore'),
    'timeout' => 1200,
];
