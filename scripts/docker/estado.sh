#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Estado rápido del stack Docker
#  (scripts/server/monitor.sh es para el servidor Linux: mira systemctl, etc.)
#      ./scripts/docker/estado.sh
# ═══════════════════════════════════════════════════════════════════
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

echo "── Contenedores ─────────────────────────────────────────"
docker compose ps
echo
echo "── Base de datos (como trinity_readonly) ────────────────"
docker compose exec -T db sh -c 'MYSQL_PWD="$DB_READONLY_PASS" mysql -utrinity_readonly "$MYSQL_DATABASE" -t -e "
  SELECT (SELECT COUNT(*) FROM usuarios) AS usuarios, (SELECT COUNT(*) FROM torneos) AS torneos,
         (SELECT COUNT(*) FROM partidos) AS partidos, (SELECT COUNT(*) FROM auditoria) AS eventos_auditoria;
  SELECT tabla, accion, COUNT(*) AS cantidad FROM auditoria GROUP BY tabla, accion ORDER BY cantidad DESC LIMIT 8;"' \
  || echo "No se pudo consultar la base (¿está levantado el contenedor db?)"
echo
echo "── Últimos errores del sitio ────────────────────────────"
docker compose logs --tail=15 web 2>&1 | grep -iE "error|fatal|warn" || echo "(sin errores recientes)"
