const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

document.addEventListener('DOMContentLoaded', cargar);

async function cargar() {
    const grid = $('#eq-grid');
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/mine.php`);
        const data = await res.json().catch(() => ({}));

        if (res.status === 401) {
            grid.innerHTML = '<p class="no-tournaments">Iniciá sesión para ver tus equipos.</p>';
            return;
        }
        if (!res.ok) {
            grid.innerHTML = `<p class="no-tournaments">${escapeHtml(data.error || 'No se pudieron cargar tus equipos.')}</p>`;
            return;
        }

        render(data.equipos || []);
    } catch (err) {
        console.error('[mis-equipos.js]', err);
        grid.innerHTML = '<p class="no-tournaments">No se pudo conectar con el servidor.</p>';
    }
}

function render(equipos) {
    const grid = $('#eq-grid');
    const tarjetaNueva = `
        <a href="crear-equipo.html" class="entity-card entity-card-new">
            <div class="empty-state" style="border:none; padding:2.5rem 1rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                <strong>Crear un equipo nuevo</strong>
                <span>Invitá compañeros y empiecen a competir juntos.</span>
            </div>
        </a>`;

    if (equipos.length === 0) {
        grid.innerHTML = tarjetaNueva;
        return;
    }

    grid.innerHTML = equipos.map(tarjetaEquipo).join('') + tarjetaNueva;

    $$('#eq-grid [data-inv-equipo]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            responderInvitacion(Number(btn.dataset.invEquipo), btn.dataset.aceptar === '1');
        });
    });
}

function tarjetaEquipo(e) {
    const logo = e.logo_url
        ? `<img src="${API_BASE_URL}/../app/users/photo.php?id=${encodeURIComponent(e.logo_url)}" alt="" style="width:100%;height:100%;object-fit:cover;">`
        : iniciales(e.nombre);

    if (e.estado === 'invitado') {
        return `
        <article class="entity-card-wrap">
            <div class="entity-card" style="cursor:default;">
                <div class="entity-banner team-banner"><div class="team-logo">${logo}</div></div>
                <div class="entity-body">
                    <h3 class="entity-name">${escapeHtml(e.nombre)}</h3>
                    <div class="entity-meta"><span>${escapeHtml(e.disciplina)}</span><span>·</span><span>Te invitó ${escapeHtml(e.capitan_nombre)}</span></div>
                </div>
                <div class="entity-foot" style="gap:.6rem;">
                    <button class="btn-primary" style="flex:1;" data-inv-equipo="${e.id}" data-aceptar="1">Aceptar</button>
                    <button class="btn-secondary" style="flex:1;" data-inv-equipo="${e.id}" data-aceptar="0">Rechazar</button>
                </div>
            </div>
        </article>`;
    }

    return `
        <article class="entity-card-wrap">
            <a href="equipo.html?id=${e.id}" class="entity-card">
                <div class="entity-banner team-banner"><div class="team-logo">${logo}</div></div>
                <div class="entity-body">
                    <h3 class="entity-name">${escapeHtml(e.nombre)}</h3>
                    <div class="entity-meta"><span>${escapeHtml(e.disciplina)}</span><span>·</span><span>${e.miembros} miembro${e.miembros === 1 ? '' : 's'}</span></div>
                </div>
                <div class="entity-foot">
                    <span>Capitán: ${e.rol === 'capitan' ? 'vos' : escapeHtml(e.capitan_nombre)}</span>
                </div>
            </a>
        </article>`;
}

async function responderInvitacion(equipoId, aceptar) {
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/teams/respond-invite.php`, {
            method: 'POST',
            body: JSON.stringify({ equipo_id: equipoId, aceptar }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.error || 'No se pudo procesar la invitación.');
            return;
        }
        cargar();
    } catch (err) {
        console.error('[mis-equipos.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

function iniciales(nombre) {
    return (nombre || '?').trim().split(/\s+/).slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
