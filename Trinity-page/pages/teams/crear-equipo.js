const $ = (sel) => document.querySelector(sel);

const invitados = [];

document.addEventListener('DOMContentLoaded', () => {
    $('#eq-btn-agregar').addEventListener('click', agregarChip);
    $('#eq-invitar').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); agregarChip(); }
    });
    $('#eq-form').addEventListener('submit', onSubmit);
});

function agregarChip() {
    const input = $('#eq-invitar');
    const usuario = input.value.trim().replace(/^@/, '');
    if (!usuario) return;
    if (invitados.includes(usuario)) { input.value = ''; return; }

    invitados.push(usuario);
    input.value = '';
    renderChips();
}

function renderChips() {
    $('#eq-chips').innerHTML = invitados.map((u) => `
        <span class="invite-chip">@${escapeHtml(u)} <button type="button" data-quitar="${escapeHtml(u)}" aria-label="Quitar">×</button></span>
    `).join('');

    document.querySelectorAll('#eq-chips [data-quitar]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const idx = invitados.indexOf(btn.dataset.quitar);
            if (idx !== -1) invitados.splice(idx, 1);
            renderChips();
        });
    });
}

async function onSubmit(e) {
    e.preventDefault();
    const msgEl = $('#eq-msg');
    const btn   = $('#eq-btn-crear');
    const file  = $('#eq-logo').files[0];

    const nombre = $('#eq-nombre').value.trim();
    if (!nombre) {
        msgEl.textContent = 'Ingresá un nombre para el equipo.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Creando...';
    msgEl.textContent = '';
    msgEl.className = 'form-msg';

    try {
        const logo = file ? await leerComoDataUrl(file) : null;

        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/create.php`, {
            method: 'POST',
            body: JSON.stringify({
                nombre,
                disciplina: $('#eq-disciplina').value,
                descripcion: $('#eq-desc').value.trim(),
                logo,
            }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo crear el equipo.';
            msgEl.className = 'form-msg msg-error';
            btn.disabled = false;
            btn.textContent = 'Crear equipo';
            return;
        }

        await enviarInvitaciones(data.id);

        msgEl.textContent = '¡Equipo creado!';
        msgEl.className = 'form-msg msg-ok';
        setTimeout(() => { window.location.href = `equipo.html?id=${data.id}`; }, 800);
    } catch (err) {
        console.error('[crear-equipo.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
        btn.disabled = false;
        btn.textContent = 'Crear equipo';
    }
}

async function enviarInvitaciones(equipoId) {
    for (const usuario of invitados) {
        try {
            await apiFetch(`${API_BASE_URL}/../app/teams/invite.php`, {
                method: 'POST',
                body: JSON.stringify({ equipo_id: equipoId, usuario }),
            });
        } catch (err) {
            console.error('[crear-equipo.js] invitación a', usuario, err);
            // Si falla una invitación seguimos con las demás — el
            // capitán puede reintentar desde la página del equipo.
        }
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

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
