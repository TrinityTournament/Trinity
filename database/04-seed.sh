#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Docker initdb 04: datos de prueba (seed)
#
#  Corre al final del arranque inicial (después de esquema, usuarios y
#  triggers), así el seed pasa por los triggers de auditoría.
#  LOAD_SEED=0 en el .env  →  base vacía (solo estructura).
#
#  Sin `set -u` ni `exit`: la imagen puede ejecutar esto con `source`.
# ═══════════════════════════════════════════════════════════════════
set -eo pipefail

if [ "${LOAD_SEED:-1}" = "0" ]; then
    echo "[04-seed] LOAD_SEED=0 → no se cargan datos de prueba."
else
    echo "[04-seed] Cargando datos de prueba (puede tardar unos segundos)..."
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --default-character-set=utf8mb4 < /docker-entrypoint-initdb.d/extras/seed.sql
    echo "[04-seed] Listo. Cuentas de prueba: ver README (sección Credenciales)."
fi
