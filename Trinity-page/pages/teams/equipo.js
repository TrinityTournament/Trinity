const $ = (sel) => document.querySelector(sel);

let equipoId = null;
let equipo = null;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    const params = new URLSearchParams(window.location.search);
    equipoId = params.get('id');

    if (!equipoId) {
        return mostrarError('No se indicó qué equipo mostrar.');
    }

    await cargar();
}

async function cargar() {
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/detail.php?id=${encodeURIComponent(equipoId)}`);
        const data = await res.json().catch(() => ({}));

        if (res.status === 401) {
            return mostrarError('Iniciá sesión para ver este equipo.');
        }
        if (!res.ok) {
            return mostrarError(data.error || 'No se pudo cargar el equipo.');
        }

        equipo = data;
        render();
    } catch (err) {
        console.error('[equipo.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#eq-error-msg').textContent = msg;
    $('#eq-error').hidden = false;
    $('#eq-body').hidden = true;
}

function render() {
    document.title = `${equipo.nombre} — Trinity`;

    $('#eq-logo').innerHTML = equipo.logo_url
        ? `<img src="${imgSrc(equipo.logo_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
        : iniciales(equipo.nombre);
    $('#eq-nombre').textContent = equipo.nombre;
    $('#eq-sub').textContent = `${equipo.disciplina} · Capitán: ${equipo.soy_capitan ? 'vos' : equipo.capitan_nombre}`;
    $('#eq-stat-miembros').textContent = equipo.miembros.length;

    $('#eq-actions').innerHTML = equipo.soy_capitan
        ? `<button type="button" class="btn-secondary" id="eq-toggle-invitar">Invitar miembro</button>
           <button type="button" class="btn-ghost" id="eq-toggle-editar">Editar equipo</button>`
        : (equipo.mi_estado === 'activo'
            ? `<button type="button" class="btn-secondary" id="eq-btn-salir">Salir del equipo</button>`
            : '');

    if (equipo.soy_capitan) {
        $('#eq-toggle-invitar').addEventListener('click', () => togglePanel('eq-panel-invitar'));
        $('#eq-toggle-editar').addEventListener('click', () => {
            $('#eq-edit-nombre').value = equipo.nombre;
            $('#eq-edit-disciplina').value = equipo.disciplina;
            $('#eq-edit-desc').value = equipo.descripcion || '';
            togglePanel('eq-panel-editar');
        });
        $('#eq-inv-btn').addEventListener('click', invitar);
        $('#eq-edit-btn').addEventListener('click', guardarEdicion);
        $('#eq-delete-btn').addEventListener('click', disolver);
    } else {
        const btnSalir = document.getElementById('eq-btn-salir');
        if (btnSalir) btnSalir.addEventListener('click', async () => quitarMiembro(await viewerIdPromise));
    }

    renderMiembros();

    $('#eq-error').hidden = true;
    $('#eq-body').hidden = false;
}

function togglePanel(id) {
    const el = $('#' + id);
    el.hidden = !el.hidden;
}

function renderMiembros() {
    const filas = [...equipo.miembros, ...equipo.invitados];

    $('#eq-miembros').innerHTML = filas.map((m) => {
        const avatar = m.foto_url
            ? `<img src="${imgSrc(m.foto_url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`
            : (m.nombre || '?').trim().charAt(0).toUpperCase();
        const rolLabel = { capitan: 'Capitán', jugador: 'Jugador', suplente: 'Suplente' }[m.rol] || m.rol;
        const estadoBadge = m.estado === 'activo'
            ? '<span class="badge badge-abierto">Activo</span>'
            : '<span class="badge badge-en_creacion">Invitado</span>';

        const puedeExpulsar = equipo.soy_capitan && m.rol !== 'capitan';

        return `
        <article class="member-row">
            <div class="member-avatar">${avatar}</div>
            <header class="member-info"><h3 class="member-name">${escapeHtml(m.nombre)} (@${escapeHtml(m.usuario)})</h3><p class="member-role">${rolLabel}</p></header>
            ${estadoBadge}
            ${puedeExpulsar ? `<button type="button" class="btn-ghost btn-sm" data-quitar="${m.id}">Quitar</button>` : ''}
        </article>`;
    }).join('') || '<p class="no-tournaments">Este equipo todavía no tiene miembros.</p>';

    document.querySelectorAll('#eq-miembros [data-quitar]').forEach((btn) => {
        btn.addEventListener('click', () => quitarMiembro(Number(btn.dataset.quitar)));
    });
}

async function invitar() {
    const msgEl = $('#eq-inv-msg');
    const usuario = $('#eq-inv-usuario').value.trim().replace(/^@/, '');
    if (!usuario) {
        msgEl.textContent = 'Ingresá el usuario a invitar.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/invite.php`, {
            method: 'POST',
            body: JSON.stringify({ equipo_id: Number(equipoId), usuario, rol: $('#eq-inv-rol').value }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo enviar la invitación.';
            msgEl.className = 'form-msg msg-error';
            return;
        }

        msgEl.textContent = data.mensaje || 'Invitación enviada.';
        msgEl.className = 'form-msg msg-ok';
        $('#eq-inv-usuario').value = '';
        cargar();
    } catch (err) {
        console.error('[equipo.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
    }
}

async function guardarEdicion() {
    const msgEl = $('#eq-edit-msg');
    const file  = $('#eq-edit-logo').files[0];
    const nombre = $('#eq-edit-nombre').value.trim();
    const disciplina = $('#eq-edit-disciplina').value.trim();

    if (!nombre || !disciplina) {
        msgEl.textContent = 'Completá el nombre y la disciplina.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    try {
        const logo = file ? await leerComoDataUrl(file) : undefined;
        const body = { id: Number(equipoId), nombre, disciplina, descripcion: $('#eq-edit-desc').value.trim() };
        if (logo !== undefined) body.logo = logo;

        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/update.php`, {
            method: 'PATCH',
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo guardar.';
            msgEl.className = 'form-msg msg-error';
            return;
        }

        msgEl.textContent = 'Cambios guardados.';
        msgEl.className = 'form-msg msg-ok';
        cargar();
    } catch (err) {
        console.error('[equipo.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
    }
}

async function quitarMiembro(usuarioId) {
    const esUnoMismo = usuarioId === await viewerIdPromise;
    if (!confirm(esUnoMismo ? '¿Seguro que querés salir del equipo?' : '¿Seguro que querés expulsar a este miembro?')) return;

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/remove-member.php?equipo_id=${equipoId}&usuario_id=${usuarioId}`, {
            method: 'DELETE',
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo completar la acción.');
            return;
        }

        if (esUnoMismo) {
            window.location.href = 'mis-equipos.html';
            return;
        }
        cargar();
    } catch (err) {
        console.error('[equipo.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

async function disolver() {
    if (!confirm('¿Seguro que querés disolver este equipo? Esta acción no se puede deshacer.')) return;

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/delete.php?id=${equipoId}`, { method: 'DELETE' });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo disolver el equipo.');
            return;
        }
        window.location.href = 'mis-equipos.html';
    } catch (err) {
        console.error('[equipo.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

// El detalle no trae "mi id" directo — lo resolvemos una sola vez
// contra check-session.php y reusamos la misma promesa en todos lados
// para no repetir el pedido ni pisarnos con condiciones de carrera.
const viewerIdPromise = (async () => {
    try {
        const res = await fetch(`${API_BASE_URL}/../app/auth/check-session.php`, { credentials: 'include' });
        if (res.ok) {
            const data = await res.json();
            return data.usuario?.id ?? null;
        }
    } catch { /* sin sesión */ }
    return null;
})();

function imgSrc(id) {
    return `${API_BASE_URL}/../app/users/photo.php?id=${encodeURIComponent(id)}`;
}

function iniciales(nombre) {
    return (nombre || '?').trim().split(/\s+/).slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
}

function leerComoDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('No se pudo leer la imagen.'));
        reader.readAsDataURL(file);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
