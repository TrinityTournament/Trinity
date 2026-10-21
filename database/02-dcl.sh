#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Docker initdb 02: usuarios de MySQL (DCL)
#  (la carpeta database/ se monta entera en /docker-entrypoint-initdb.d)
#
#  La imagen oficial de MySQL ejecuta esto UNA sola vez, al crear el
#  volumen de datos, justo después de 01-schema.sql y antes de
#  03-auditoria.sql (el orden sale del prefijo numérico).
#
#  En vez de duplicar los GRANT, lee la plantilla que ya se entrega
#  (database/extras/dcl.example.sql) y le hace dos reemplazos:
#    · CAMBIAR_*_PASSWORD  →  contraseñas del .env (así no hay claves
#                             escritas en ningún archivo del proyecto)
#    · 'localhost'         →  '%'  (la app se conecta desde OTRO
#                             contenedor, no por el socket del servidor)
#  Es seguro dejar '%' porque el compose NO publica el puerto de MySQL
#  hacia afuera (solo la red interna `trinity_net`).
#
#  Nota: la imagen puede ejecutar este archivo con `source` (si no tiene
#  permiso de ejecución, p. ej. en Windows), por eso NO se usa `set -u`
#  ni `exit`: tocarían el shell del entrypoint de la imagen.
# ═══════════════════════════════════════════════════════════════════
set -eo pipefail

trinity_plantilla="${DCL_TEMPLATE:-/docker-entrypoint-initdb.d/extras/dcl.example.sql}"
trinity_host="${DB_USER_HOST:-%}"
trinity_user="${DB_USER:-trinity_app}"

# Escapa \ y ' para meter el valor dentro de un literal SQL entre comillas simples.
trinity_esc() {
    local v="$1"
    v="${v//\\/\\\\}"
    v="${v//\'/\\\'}"
    printf '%s' "$v"
}

trinity_requerida() {
    if [ -z "${!1}" ]; then
        echo "[02-dcl] ERROR: falta la variable $1 en el .env (ver .env.example)." >&2
        return 1
    fi
}

for v in MYSQL_ROOT_PASSWORD DB_PASS DB_READONLY_PASS DB_BACKUP_PASS DB_MIGRATOR_PASS; do
    trinity_requerida "$v"
done

if [ ! -f "$trinity_plantilla" ]; then
    echo "[02-dcl] ERROR: no encuentro la plantilla $trinity_plantilla" >&2
    return 1 2>/dev/null || false
fi

if ! [[ "$trinity_user" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "[02-dcl] ERROR: DB_USER solo puede tener letras, números y _" >&2
    return 1 2>/dev/null || false
fi

# En bash >= 5.2 un "&" en el texto de reemplazo significa "lo que matcheó";
# se desactiva para que las contraseñas con "&" queden literales.
shopt -u patsub_replacement 2>/dev/null || true

trinity_sql="$(<"$trinity_plantilla")"
trinity_sql="${trinity_sql//CAMBIAR_APP_PASSWORD/$(trinity_esc "$DB_PASS")}"
trinity_sql="${trinity_sql//CAMBIAR_READONLY_PASSWORD/$(trinity_esc "$DB_READONLY_PASS")}"
trinity_sql="${trinity_sql//CAMBIAR_BACKUP_PASSWORD/$(trinity_esc "$DB_BACKUP_PASS")}"
trinity_sql="${trinity_sql//CAMBIAR_MIGRATOR_PASSWORD/$(trinity_esc "$DB_MIGRATOR_PASS")}"

trinity_local="'localhost'"
trinity_remoto="'${trinity_host}'"
trinity_sql="${trinity_sql//"$trinity_local"/"$trinity_remoto"}"

if [ "$trinity_user" != "trinity_app" ]; then
    trinity_sql="${trinity_sql//\'trinity_app\'/\'$trinity_user\'}"
fi

if grep -qE "CAMBIAR_[A-Z]+_PASSWORD" <<<"$trinity_sql"; then
    echo "[02-dcl] ERROR: quedó algún CAMBIAR_* sin reemplazar." >&2
    return 1 2>/dev/null || false
fi

# MYSQL_PWD evita pasar la clave de root por la línea de comandos.
printf '%s\n' "$trinity_sql" | MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --default-character-set=utf8mb4

echo "[02-dcl] Usuarios creados: ${trinity_user}, trinity_readonly, trinity_backup, trinity_migrator (host '${trinity_host}')."

unset -f trinity_esc trinity_requerida
unset trinity_plantilla trinity_host trinity_user trinity_sql trinity_local trinity_remoto
