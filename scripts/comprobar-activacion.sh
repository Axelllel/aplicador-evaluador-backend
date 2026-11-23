#!/usr/bin/env bash
set -Eeuo pipefail
# Ejecutar en la shell de la nueva versión de Laravel, después de aplicar el SQL.
php artisan config:clear
php artisan view:clear
php artisan route:list --path=api/v2/student
php artisan route:list --path=api/v1/auth/logout
php artisan examenes:cerrar-vencidos --lotes=1
php artisan examenes:procesar-calificaciones --limite=3
