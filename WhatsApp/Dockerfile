# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Bot de WhatsApp (Node 22)  ·  OPCIONAL (perfil "bot")
#  Contexto de build: ./WhatsApp   (ver docker-compose.yml)
#
#  · Solo se instalan las dependencias que el código realmente importa
#    en la práctica; youtube-dl-exec no descarga su binario (no se usa).
#  · Las imágenes (assets/, 90 MB) NO van en la imagen: se montan.
#  · La sesión de WhatsApp (.auth) y las fotos subidas (storage/) viven en
#    volúmenes con nombre, para que sobrevivan a recrear el contenedor.
# ═══════════════════════════════════════════════════════════════════
FROM node:22-bookworm-slim AS deps
ENV YOUTUBE_DL_SKIP_DOWNLOAD=true
WORKDIR /app
COPY package.json ./
# python3/make/g++ solo por si better-sqlite3 (módulo nativo) no trae binario
# precompilado para la arquitectura del equipo (p. ej. algunas ARM).
RUN apt-get update \
 && apt-get install -y --no-install-recommends python3 make g++ \
 && npm install --omit=dev --no-audit --no-fund \
 && rm -rf /var/lib/apt/lists/* /root/.npm

FROM node:22-bookworm-slim
ENV NODE_ENV=production
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY package.json main.js ./
COPY src ./src
RUN mkdir -p /app/.auth /app/storage/photos /app/assets \
 && chown -R node:node /app/.auth /app/storage
USER node
EXPOSE 3001

# La API responde 200 aunque WhatsApp todavía no esté vinculado ({"connected":false}):
# "saludable" = el servidor HTTP del bot está vivo.
HEALTHCHECK --interval=20s --timeout=5s --start-period=20s --retries=5 \
  CMD node -e "fetch('http://127.0.0.1:'+(process.env.WA_PORT||3001)+'/api/whatsapp/status',{headers:{'x-wa-secret':process.env.WA_SECRET||''}}).then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "main.js"]
