#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Restaurar un backup en el stack Docker
#
#      ./scripts/docker/restore.sh backups/trinity_2026-10-20_03-00.sql.gz
#      ./scripts/docker/restore.sh archivo.sql.gz --yes     (sin preguntar)
#
#  ⚠ REEMPLAZA los datos actuales por los del backup. Usa root porque tiene que
#    recrear tablas y triggers. Si querés volver a un estado limpio desde cero,
#    es más simple:  docker compose down -v && docker compose up -d
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

ARCHIVO="${1:-}"
if [ -z "$ARCHIVO" ] || [ ! -f "$ARCHIVO" ]; then
    echo "Uso: ./scripts/docker/restore.sh <backup.sql.gz> [--yes]" >&2
    exit 1
fi
gzip -t "$ARCHIVO" || { echo "El archivo no es un gzip válido." >&2; exit 1; }

if [ "${2:-}" != "--yes" ]; then
    read -r -p "Esto va a REEMPLAZAR la base de datos actual con $ARCHIVO. ¿Seguir? (escribí 'si'): " R
    [ "$R" = "si" ] || { echo "Cancelado."; exit 1; }
fi

echo "[restore] Restaurando..."
gzip -dc "$ARCHIVO" | docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE"'
echo "[restore] Listo."
