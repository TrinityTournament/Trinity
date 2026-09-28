const $ = (sel) => document.querySelector(sel);

document.addEventListener('DOMContentLoaded', () => {
    cargarPendientes();
    $('#admin-noticia-form').addEventListener('submit', onSubmit);
});

async function cargarPendientes() {
    const body = $('#noticias-pendientes-body');
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/news/moderate.php`);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            body.innerHTML = `<tr><td colspan="5">${escapeHtml(data.error || 'No se pudo cargar.')}</td></tr>`;
            return;
        }

        const noticias = data.noticias || [];
        if (noticias.length === 0) {
            body.innerHTML = '<tr><td colspan="5">No hay noticias pendientes de aprobación.</td></tr>';
            return;
        }

        body.innerHTML = noticias.map((n) => `
            <tr>
                <td>${escapeHtml(n.titulo)}</td>
                <td>@${escapeHtml(n.autor_usuario)}</td>
                <td>${escapeHtml(n.categoria)}</td>
                <td>${formatearFecha(n.creado_en)}</td>
                <td>
                    <button class="btn-approve" data-id="${n.id}" data-aprobar="1">Aprobar</button>
                    <button class="btn-reject" data-id="${n.id}" data-aprobar="0">Rechazar</button>
                </td>
            </tr>
        `).join('');

        document.querySelectorAll('#noticias-pendientes-body [data-id]').forEach((btn) => {
            btn.addEventListener('click', () => resolver(Number(btn.dataset.id), btn.dataset.aprobar === '1'));
        });
    } catch (err) {
        console.error('[admin-noticias.js]', err);
        body.innerHTML = '<tr><td colspan="5">No se pudo conectar con el servidor.</td></tr>';
    }
}

async function resolver(id, aprobar) {
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/news/moderate.php`, {
            method: 'PATCH',
            body: JSON.stringify({ id, aprobar }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.error || 'No se pudo procesar la solicitud.');
            return;
        }
        cargarPendientes();
    } catch (err) {
        console.error('[admin-noticias.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

async function onSubmit(e) {
    e.preventDefault();
    const msgEl = $('#admin-noticia-msg');
    const btn   = $('#admin-noticia-btn');
    const file  = $('#admin-noticia-imagen').files[0];

    const titulo    = $('#admin-noticia-titulo').value.trim();
    const contenido = $('#admin-noticia-contenido').value.trim();

    if (!titulo || !contenido) {
        msgEl.textContent = 'Completá el título y el contenido.';
        msgEl.className = 'news-msg msg-error';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Publicando...';
    msgEl.textContent = '';
    msgEl.className = 'news-msg';

    try {
        const imagen = file ? await leerComoDataUrl(file) : null;

        const res  = await apiFetch(`${API_BASE_URL}/../app/news/create.php`, {
            method: 'POST',
            body: JSON.stringify({
                titulo,
                contenido,
                categoria: $('#admin-noticia-categoria').value,
                resumen: $('#admin-noticia-resumen').value.trim(),
                imagen,
            }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo publicar la noticia.';
            msgEl.className = 'news-msg msg-error';
            btn.disabled = false;
            btn.textContent = 'Publicar';
            return;
        }

        msgEl.textContent = data.mensaje || 'Noticia publicada.';
        msgEl.className = 'news-msg msg-ok';
        $('#admin-noticia-form').reset();
        btn.disabled = false;
        btn.textContent = 'Publicar';
    } catch (err) {
        console.error('[admin-noticias.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'news-msg msg-error';
        btn.disabled = false;
        btn.textContent = 'Publicar';
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
