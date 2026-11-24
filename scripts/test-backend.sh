#!/usr/bin/env bash
set -Eeuo pipefail
: "${BBB_TEST_PG_PORT:?Indica el puerto de PostgreSQL desechable en 127.0.0.1.}"
: "${BBB_TEST_PG_BIN:?Indica el directorio de pg_dump, pg_dumpall y pg_restore.}"
if [[ ! "$BBB_TEST_PG_PORT" =~ ^[0-9]+$ ]]; then exit 2; fi
for tool in pg_dump pg_dumpall pg_restore; do
  if [[ ! -x "$BBB_TEST_PG_BIN/$tool" ]]; then printf 'Falta la herramienta: %s\n' "$tool" >&2; exit 2; fi
done
export ATTEMPT_TEST_PG_PORT="$BBB_TEST_PG_PORT" AI_TEST_PG_PORT="$BBB_TEST_PG_PORT"
export PAYMENT_TEST_PG_PORT="$BBB_TEST_PG_PORT" BACKUP_TEST_PG_PORT="$BBB_TEST_PG_PORT"
export BACKUP_TEST_PG_BIN="$BBB_TEST_PG_BIN" APP_ENV=testing
if [[ -z "${APP_KEY:-}" ]]; then APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"; export APP_KEY; fi
php artisan test --compact "$@"
