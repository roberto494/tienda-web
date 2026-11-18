#!/bin/sh
set -e

# Espera a que MySQL acepte conexiones y crea el administrador (si no existe)
intentos=0
until php /var/www/app/scripts/crear_admin.php; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 10 ]; then
        echo "[ADMIN] No se pudo crear el administrador tras $intentos intentos" >&2
        break
    fi
    sleep 3
done

# Arranca Apache en primer plano (proceso principal del contenedor)
exec apache2-foreground
