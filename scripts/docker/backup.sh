#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Backup de la base de datos del stack Docker
#
#  Equivale a scripts/server/backup.sh (que es para un servidor Linux con
#  MySQL instalado), pero corriendo mysqldump DENTRO del contenedor `db`.
#
#      ./scripts/docker/backup.sh               → ./backups/trinity_AAAA-MM-DD_HH-MM.sql.gz
#      ./scripts/docker/backup.sh /ruta/carpeta → en otra carpeta
#
#  · Usa el usuario trinity_backup (solo lectura + LOCK TABLES + TRIGGER).
#  · Incluye triggers (la auditoría) y rutinas.
#  · Conserva los últimos 7 backups de esa carpeta.
#  Para automatizarlo (cron en el equipo que corre Docker):
#      0 3 * * *  cd /ruta/al/proyecto && ./scripts/docker/backup.sh >> backups/backup.log 2>&1
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

DEST="${1:-./backups}"
MAX_BACKUPS=7
mkdir -p "$DEST"
ARCHIVO="$DEST/trinity_$(date +%Y-%m-%d_%H-%M).sql.gz"

echo "[backup] Volcando la base de datos..."
# La clave se lee DENTRO del contenedor (variable DB_BACKUP_PASS) y viaja por MYSQL_PWD:
# no pasa por tu terminal ni queda en el historial.
if ! docker compose exec -T db sh -c \
    'MYSQL_PWD="$DB_BACKUP_PASS" mysqldump -utrinity_backup --single-transaction --no-tablespaces --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' \
    | gzip > "$ARCHIVO"; then
    rm -f "$ARCHIVO"
    echo "[backup] ERROR: falló el volcado. ¿Está levantado el contenedor db? (docker compose ps)" >&2
    exit 1
fi

# Verificación mínima: que sea un gzip válido y tenga tablas dentro.
# (grep -c lee todo el flujo; con `grep -q` + pipefail, gzip recibiría SIGPIPE y un backup
#  bueno se descartaría por error.)
TABLAS=0
if gzip -t "$ARCHIVO" 2>/dev/null; then
    TABLAS=$(gzip -dc "$ARCHIVO" | grep -c "^CREATE TABLE" || true)
fi
if [ "$TABLAS" -lt 1 ]; then
    rm -f "$ARCHIVO"
    echo "[backup] ERROR: el archivo generado no es un backup válido." >&2
    exit 1
fi
echo "[backup] OK → $ARCHIVO ($(du -h "$ARCHIVO" | cut -f1))"

# Rotación
TOTAL=$(find "$DEST" -maxdepth 1 -name 'trinity_*.sql.gz' | wc -l)
if [ "$TOTAL" -gt "$MAX_BACKUPS" ]; then
    find "$DEST" -maxdepth 1 -name 'trinity_*.sql.gz' | sort | head -n "$((TOTAL - MAX_BACKUPS))" | xargs rm -f
    echo "[backup] Se eliminaron $((TOTAL - MAX_BACKUPS)) backup(s) antiguo(s)."
fi
