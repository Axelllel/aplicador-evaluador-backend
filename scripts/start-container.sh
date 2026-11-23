#!/usr/bin/env bash
set -Eeuo pipefail
# El planificador debe seguir vivo para cerrar intentos aunque nadie abra la app.
su -s /bin/sh www-data -c 'exec php artisan schedule:work --no-interaction' &
scheduler_pid=$!
apache2-foreground &
apache_pid=$!
shutdown() {
  kill -TERM "$scheduler_pid" "$apache_pid" 2>/dev/null || true
  wait "$scheduler_pid" "$apache_pid" 2>/dev/null || true
}
trap shutdown EXIT
trap 'exit 0' TERM INT
wait -n "$scheduler_pid" "$apache_pid"
# Un proceso esencial terminó: el supervisor de Render debe reiniciar el contenedor.
exit 1
