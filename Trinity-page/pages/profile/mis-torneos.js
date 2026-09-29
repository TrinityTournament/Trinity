const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

const BANNER_PATH = `${API_BASE_URL}/../app/assets/get.php?path=cards/tournament-banner/`;
const BANNER_POR_DEPORTE = {
    'fútbol':       BANNER_PATH + 'FutbolBG.jpg',
    'futbol':       BANNER_PATH + 'FutbolBG.jpg',
    'brawl stars':  BANNER_PATH + 'BSBG.png',
    'clash royale': BANNER_PATH + 'ClashBG.jpeg',
    'fortnite':     BANNER_PATH + 'FortBG.jpg',
    'free fire':    BANNER_PATH + 'FreeBG.png',
    'minecraft':    BANNER_PATH + 'MineBG.jpg',
};

const ESTADOS_ACTIVOS = ['en_creacion', 'abierto', 'en_curso'];

let torneos = [];
let invitaciones = [];
let tabActual = 'activos';

document.addEventListener('DOMContentLoaded', init);

async function init() {
    $$('.round-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            $$('.round-tab').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            tabActual = tab.dataset.tab;
            render();
        });
    });

    try {
        const [resT, resI] = await Promise.all([
            apiFetch(`${API_BASE_URL}/../app/tournaments/get-my-participations.php`),
            apiFetch(`${API_BASE_URL}/../app/tournaments/invitations.php`),
        ]);

        if (resT.status === 401 || resI.status === 401) {
            $('#mt-grid').innerHTML = '<p class="no-tournaments">Iniciá sesión para ver tus torneos.</p>';
            return;
        }

        const dataT = await resT.json().catch(() => ({}));
        const dataI = await resI.json().catch(() => ({}));

        torneos = dataT.torneos || [];
        invitaciones = dataI.invitaciones || [];

        render();
    } catch (err) {
        console.error('[mis-torneos.js]', err);
        $('#mt-grid').innerHTML = '<p class="no-tournaments">No se pudo conectar con el servidor.</p>';
    }
}

function render() {
    if (tabActual === 'invitaciones') return renderInvitaciones();

    const activos = tabActual === 'activos';
    const lista = torneos.filter((t) => activos
        ? ESTADOS_ACTIVOS.includes(t.estado)
        : !ESTADOS_ACTIVOS.includes(t.estado));

    const grid = $('#mt-grid');
    if (lista.length === 0) {
        grid.innerHTML = `<p class="no-tournaments">${activos
            ? 'No estás inscripto en ningún torneo activo todavía. <a href="../nav/tournament/buscar.html">Buscar torneos →</a>'
            : 'Todavía no tenés torneos finalizados.'}</p>`;
        return;
    }

    grid.innerHTML = lista.map(tarjetaTorneo).join('');
}

function renderInvitaciones() {
    const grid = $('#mt-grid');
    if (invitaciones.length === 0) {
        grid.innerHTML = '<p class="no-tournaments">No tenés invitaciones pendientes.</p>';
        return;
    }

    grid.innerHTML = invitaciones.map((i) => `
        <article class="entity-card-wrap">
            <div class="entity-card" style="cursor:default;">
                <div class="entity-banner" style="background-image:linear-gradient(135deg, rgba(192,0,10,.5), rgba(0,0,0,.6)), url('${BANNER_POR_DEPORTE[normalizar(i.deporte)] || ''}'); background-size:cover; background-position:center;">
                    <span class="badge">Invitación</span>
                </div>
                <div class="entity-body">
                    <h3 class="entity-name">${escapeHtml(i.titulo)}</h3>
                    <div class="entity-meta"><span>Invita: @${escapeHtml(i.organizador_usuario)}</span></div>
                </div>
                <div class="entity-foot" style="gap:.6rem;">
                    <button class="btn-primary" style="flex:1;" data-inv="${i.id}" data-aceptar="1">Aceptar</button>
                    <button class="btn-secondary" style="flex:1;" data-inv="${i.id}" data-aceptar="0">Rechazar</button>
                </div>
            </div>
        </article>
    `).join('');

    $$('#mt-grid [data-inv]').forEach((btn) => {
        btn.addEventListener('click', () => responderInvitacion(
            Number(btn.dataset.inv),
            btn.dataset.aceptar === '1'
        ));
    });
}

async function responderInvitacion(id, aceptar) {
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/invitations.php`, {
            method: 'PATCH',
            body: JSON.stringify({ id, aceptar }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.error || 'No se pudo procesar la invitación.');
            return;
        }
        invitaciones = invitaciones.filter((i) => i.id !== id);
        if (aceptar) {
            // Refrescamos las participaciones para que aparezca en "Activos".
            const resT = await apiFetch(`${API_BASE_URL}/../app/tournaments/get-my-participations.php`);
            const dataT = await resT.json().catch(() => ({}));
            torneos = dataT.torneos || [];
        }
        render();
    } catch (err) {
        console.error('[mis-torneos.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

function tarjetaTorneo(t) {
    const banner = BANNER_POR_DEPORTE[normalizar(t.deporte)] || '';
    const cupo = t.max_participantes ? `${t.inscritos} / ${t.max_participantes} inscriptos` : `${t.inscritos} inscriptos`;
    return `
        <article class="entity-card-wrap">
            <a href="../nav/tournament/detalle.html?id=${t.id}" class="entity-card">
                <div class="entity-banner" style="background-image:linear-gradient(135deg, rgba(192,0,10,.5), rgba(0,0,0,.6)), url('${banner}'); background-size:cover; background-position:center;">
                    <span class="badge badge-${t.estado}">${escapeHtml(t.estado_label)}</span>
                </div>
                <div class="entity-body">
                    <h3 class="entity-name">${escapeHtml(t.titulo)}</h3>
                    <div class="entity-meta"><span>${escapeHtml(t.formato_label)}</span><span>·</span><span>${escapeHtml(cupo)}</span></div>
                </div>
                <div class="entity-foot">
                    <span>Organiza: ${escapeHtml(t.organizador_nombre)}</span>
                </div>
            </a>
        </article>
    `;
}

function normalizar(str) {
    return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
