/** Storage de assets estaticos del sitio
 *  Las imagenes del sitio; banners; iconos; logos: viven en el bot de WhatsApp en 
 *  lugar de la pagina como tal, esto con el fin de ahorrar espacio.
 *  El PHP las pide acá mediante una llamada a la API cada vez que hace falta 
 *  mostrarlas (app/assets/get.php, vía AssetClient.php) Nunca se copian en Trinity-page
 *  ni se cachean en el servidor PHP. */

import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const assetsDir = path.join(__dirname, '..', '..', 'assets');

const extAMime = {
    '.ico': 'image/x-icon',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
}

/** Busca un archivo dentro de WhatsApp/assets/ a partir de una ruta
 *  relativa (ej: cards/tournament-banner/ClashBG.jpeg).
 *  Devuelve { mime, buffer } o null si no existe o si la ruta es invalida. 
 *  
 *  Cuenta con protección contra path traversal: se normaliza la ruta y se exige
 *  que el resultado siga estando DENTRO de assetsDir — un intento de 
 *  pedir ../../../../../archivoFueraDeAssets' o similar devuelve null sin tocar
 *  los ficheros que están por fuera.
 */

export async function readAsset(relPath) {
    if (!relPath || typeof relPath !== 'string') return null;
    if (relPath.includes('\0')) return null; // Null byte inyection

    const ext = path.extname(relPath).toLowerCase();
    const mime = extAMime[ext];
    if (!mime) return null; // Solo se sirven extensiones listadas.

    const resolved = path.normalize(path.join(assetsDir, relPath));
    const rootWithMark = assetsDir + path.sep;
    if (!resolved.startsWith(rootWithMark)) {
        return null; // Se escapó de assets/ con "../"
    }

    try {
        const buffer = await fs.readFile(resolved);
        return { mime, buffer };
    } catch {
        return null; 
    }
}