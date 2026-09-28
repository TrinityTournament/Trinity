const $ = (sel) => document.querySelector(sel);

const ESTADO_LABELS = { pendiente: 'Pendiente', publicada: 'Publicada', rechazada: 'Rechazada' };
const CATEGORIA_LABELS = {
    torneos: 'Torneos', actualizaciones: 'Actualizaciones', resultados: 'Resultados',
    comunidad: 'Comunidad', anuncios: 'Anuncios',
};

document.addEventListener('DOMContentLoaded', () => {
    cargarMisNoticias();
    $('#noticia-form').addEventListener('submit', onSubmit);
});

async function cargarMisNoticias() {
    const body = $('#mis-noticias-body');
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/news/mine.php`);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            body.innerHTML = `<tr><td colspan="4">${escapeHtml(data.error || 'No se pudo cargar.')}</td></tr>`;
            return;
        }

        const noticias = data.noticias || [];
        if (noticias.length === 0) {
            body.innerHTML = '<tr><td colspan="4">Todavía no enviaste ninguna noticia.</td></tr>';
            return;
        }

        body.innerHTML = noticias.map((n) => `
            <tr>
                <td>${escapeHtml(n.titulo)}</td>
                <td>${CATEGORIA_LABELS[n.categoria] || n.categoria}</td>
                <td><span class="badge badge-${n.estado === 'publicada' ? 'abierto' : n.estado === 'rechazada' ? 'cancelado' : 'en_creacion'}">${ESTADO_LABELS[n.estado] || n.estado}</span></td>
                <td>${formatearFecha(n.creado_en)}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('[organizador-noticias.js]', err);
        body.innerHTML = '<tr><td colspan="4">No se pudo conectar con el servidor.</td></tr>';
    }
}

async function onSubmit(e) {
    e.preventDefault();
    const msgEl = $('#noticia-msg');
    const btn   = $('#noticia-btn');
    const file  = $('#noticia-imagen').files[0];

    const titulo    = $('#noticia-titulo').value.trim();
    const contenido = $('#noticia-contenido').value.trim();

    if (!titulo || !contenido) {
        msgEl.textContent = 'Completá el título y el contenido.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Enviando...';
    msgEl.textContent = '';
    msgEl.className = 'form-msg';

    try {
        const imagen = file ? await leerComoDataUrl(file) : null;

        const res  = await apiFetch(`${API_BASE_URL}/../app/news/create.php`, {
            method: 'POST',
            body: JSON.stringify({
                titulo,
                contenido,
                categoria: $('#noticia-categoria').value,
                resumen: $('#noticia-resumen').value.trim(),
                imagen,
            }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo enviar la noticia.';
            msgEl.className = 'form-msg msg-error';
            btn.disabled = false;
            btn.textContent = 'Enviar para aprobación';
            return;
        }

        msgEl.textContent = data.mensaje || 'Noticia enviada.';
        msgEl.className = 'form-msg msg-ok';
        $('#noticia-form').reset();
        btn.disabled = false;
        btn.textContent = 'Enviar para aprobación';
        cargarMisNoticias();
    } catch (err) {
        console.error('[organizador-noticias.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
        btn.disabled = false;
        btn.textContent = 'Enviar para aprobación';
    }
}

function leerComoDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('No se pudo leer la imagen.'));
        reader.readAsDataURL(file);
    });
}

function formatearFecha(fechaSql) {
    const d = new Date((fechaSql || '').replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return fechaSql || '—';
    return d.toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
