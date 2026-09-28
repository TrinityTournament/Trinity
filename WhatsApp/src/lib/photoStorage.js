// ══════════════════════════════════════════════════════════
//  TRINITY Bot — storage de fotos de perfil
//  Las fotos de perfil de Trinity ya NO se guardan en la base
//  de datos (antes iban como base64 directo en usuarios.foto_url).
//  Ahora se guardan acá, en el disco del servidor del bot, y el
//  lado PHP las sirve mediante una llamada real a esta API cada
//  vez que hace falta mostrar una (app/users/photo.php, vía
//  BotStorageClient.php) — nunca se cachea una copia en MySQL.
// ══════════════════════════════════════════════════════════

import { randomUUID } from 'node:crypto';
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PHOTOS_DIR = path.join(__dirname, '..', '..', 'storage', 'photos');

const MIME_A_EXT = {
    'image/jpeg': 'jpg',
    'image/png':  'png',
    'image/webp': 'webp',
};
const EXT_A_MIME = Object.fromEntries(Object.entries(MIME_A_EXT).map(([m, e]) => [e, m]));

async function ensureDir() {
    await fs.mkdir(PHOTOS_DIR, { recursive: true });
}

/**
 * Guarda una foto a partir de un data URL completo
 * ("data:image/jpeg;base64,...") y devuelve el id generado.
 * Lanza si el formato no es uno de los soportados.
 */
export async function guardarFoto(dataUrl) {
    const match = /^data:(image\/(?:jpeg|jpg|png|webp));base64,([A-Za-z0-9+/=]+)$/.exec(dataUrl || '');
    if (!match) {
        throw new Error('Formato de imagen inválido.');
    }

    const mime = match[1] === 'image/jpg' ? 'image/jpeg' : match[1];
    const ext  = MIME_A_EXT[mime];
    const buffer = Buffer.from(match[2], 'base64');

    // Mismo límite que ya validaba PHP (2MB) — doble chequeo, defensivo.
    if (buffer.length > 2 * 1024 * 1024) {
        throw new Error('La imagen es demasiado grande.');
    }

    await ensureDir();
    const id = randomUUID();
    await fs.writeFile(path.join(PHOTOS_DIR, `${id}.${ext}`), buffer);
    return id;
}

/**
 * Busca el archivo de un id (no sabemos la extensión de antemano,
 * así que probamos las soportadas). Devuelve { mime, buffer } o null.
 */
export async function leerFoto(id) {
    if (!/^[a-f0-9-]{36}$/i.test(id || '')) return null;

    for (const ext of Object.keys(EXT_A_MIME)) {
        const file = path.join(PHOTOS_DIR, `${id}.${ext}`);
        try {
            const buffer = await fs.readFile(file);
            return { mime: EXT_A_MIME[ext], buffer };
        } catch {
            // no es esta extensión, seguimos probando
        }
    }
    return null;
}

/**
 * Borra una foto vieja cuando el usuario sube una nueva. Falla en
 * silencio si ya no existe — no es crítico, solo limpieza de disco.
 */
export async function borrarFoto(id) {
    if (!/^[a-f0-9-]{36}$/i.test(id || '')) return;
    for (const ext of Object.keys(EXT_A_MIME)) {
        try {
            await fs.unlink(path.join(PHOTOS_DIR, `${id}.${ext}`));
        } catch {
            // no existía con esa extensión, seguimos
        }
    }
}
