#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Chequeo del bot de WhatsApp
#
#  Pregunta al bot si está conectado:
#    · conectado    → (opcional) manda un WhatsApp de confirmación
#    · desconectado → (opcional) manda un correo de alerta y sale con 1
#
#  Todo sale del .env (ver .env.example); NADA va escrito en este script:
#    WA_SECRET     (obligatoria) secreto compartido con el bot
#    WA_BOT_URL    URL completa del bot (ej. un túnel). Si no está, se arma
#                  con WA_BOT_HOST (127.0.0.1) y WA_BOT_PORT (3001).
#    ALERT_PHONE   número que recibe el "El bot está funcionando" (opcional)
#    ALERT_EMAIL   correo que recibe la alerta si el bot cae (opcional)
#    ALERT_NAME    nombre de esa persona (opcional)
#    BREVO_KEY     necesaria solo para mandar el correo de alerta
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../../.env"

# Se lee con grep (no con `source`): un .env con espacios o símbolos no se ejecuta como código.
get_env() {
    [[ -f "$ENV_FILE" ]] || return 0
    { grep -E "^${1}=" "$ENV_FILE" || true; } | tail -n1 | cut -d '=' -f2- | tr -d '"' | tr -d "'"
}

WA_SECRET="${WA_SECRET:-$(get_env WA_SECRET)}"
BREVO_KEY="${BREVO_KEY:-$(get_env BREVO_KEY)}"
WA_BOT_URL="${WA_BOT_URL:-$(get_env WA_BOT_URL)}"
WA_BOT_HOST="${WA_BOT_HOST:-$(get_env WA_BOT_HOST)}"
WA_BOT_PORT="${WA_BOT_PORT:-$(get_env WA_BOT_PORT)}"
ALERT_PHONE="${ALERT_PHONE:-$(get_env ALERT_PHONE)}"
ALERT_EMAIL="${ALERT_EMAIL:-$(get_env ALERT_EMAIL)}"
ALERT_NAME="${ALERT_NAME:-$(get_env ALERT_NAME)}"

if [[ -z "$WA_SECRET" ]]; then
    echo "Error: WA_SECRET no está definida (ni en el entorno ni en $ENV_FILE)."
    exit 1
fi

BOT_URL="${WA_BOT_URL:-http://${WA_BOT_HOST:-127.0.0.1}:${WA_BOT_PORT:-3001}}"
BOT_URL="${BOT_URL%/}"

# El secreto viaja por un archivo temporal de cabeceras (-H @archivo) y no por
# la línea de comandos, así no aparece en `ps` ni en el historial.
HDR_FILE="$(mktemp)"
trap 'rm -f "$HDR_FILE"' EXIT
chmod 600 "$HDR_FILE"
printf 'x-wa-secret: %s\nngrok-skip-browser-warning: true\nContent-Type: application/json\n' "$WA_SECRET" > "$HDR_FILE"

response=$(curl -s --max-time 10 "$BOT_URL/api/whatsapp/status" -H "@$HDR_FILE" || true)

# Esto corrobora si el bot está conectado.
if ! status=$(echo "$response" | jq -r '.connected' 2>/dev/null); then
    status="false"
fi

if [[ "$status" == "true" ]]; then
    echo "El bot funciona."

    if [[ -n "$ALERT_PHONE" ]]; then
        echo "Enviando mensaje..."
        curl -sS -f --max-time 15 "$BOT_URL/api/whatsapp/send" -H "@$HDR_FILE" \
            -d "$(jq -n --arg phone "$ALERT_PHONE" '{phone: $phone, message: "El bot está funcionando."}')"
        echo
        echo "Mensaje enviado."
    fi
    exit 0
fi

echo "El bot no está funcionando."

if [[ -n "$ALERT_EMAIL" && -n "$BREVO_KEY" ]]; then
    echo "Enviando correo de alerta..."
    BREVO_HDR="$(mktemp)"
    trap 'rm -f "$HDR_FILE" "$BREVO_HDR"' EXIT
    chmod 600 "$BREVO_HDR"
    printf 'api-key: %s\naccept: application/json\ncontent-type: application/json\n' "$BREVO_KEY" > "$BREVO_HDR"

    curl -sS -f --max-time 15 -H "@$BREVO_HDR" \
        -d "$(jq -n --arg email "$ALERT_EMAIL" --arg name "${ALERT_NAME:-Administración}" '{
            sender: {name: "WhatsApp Bot", email: "trinitysupportteam@gmail.com"},
            to: [{email: $email, name: $name}],
            subject: "Bot de WhatsApp apagado",
            textContent: "El bot de WhatsApp no está funcionando."
        }')" \
        "https://api.brevo.com/v3/smtp/email"
    echo
    echo "Correo enviado."
else
    echo "(No se mandó alerta: faltan ALERT_EMAIL y/o BREVO_KEY en el .env.)"
fi
exit 1
