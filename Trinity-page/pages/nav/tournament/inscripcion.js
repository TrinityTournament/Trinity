const $ = (sel) => document.querySelector(sel);

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

let torneoId = null;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    const params = new URLSearchParams(window.location.search);
    torneoId = params.get('id');

    if (!torneoId) {
        return mostrarError('No se indicó a qué torneo querés inscribirte.');
    }

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/detail.php?id=${encodeURIComponent(torneoId)}`);
        const t    = await res.json().catch(() => ({}));

        if (!res.ok) {
            return mostrarError(t.error || 'No se pudo cargar el torneo.');
        }
        if (!t.logueado) {
            return mostrarError('Tenés que iniciar sesión para inscribirte a un torneo.');
        }
        if (t.es_organizador) {
            return mostrarError('Sos el organizador de este torneo: no podés inscribirte como participante.');
        }
        if (t.ya_inscripto) {
            return mostrarError('Ya estás inscripto en este torneo.');
        }
        if (!t.inscripcion_abierta) {
            return mostrarError('Este torneo no está abierto para inscripciones en este momento.');
        }
        if (t.cupo_lleno) {
            return mostrarError('El cupo de este torneo ya está completo.');
        }

        render(t);
    } catch (err) {
        console.error('[inscripcion.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#ins-error-msg').textContent = msg;
    $('#ins-error').hidden = false;
    $('#ins-body').hidden = true;
}

function render(t) {
    document.title = `Inscripción — ${t.titulo} — Trinity`;

    const banner = BANNER_POR_DEPORTE[normalizar(t.deporte)];
    if (banner) {
        $('#ins-banner').style.backgroundImage =
            `linear-gradient(135deg, rgba(192,0,10,.5), rgba(0,0,0,.6)), url('${banner}')`;
    }

    $('#ins-badge-estado').textContent = t.estado_label;
    $('#ins-badge-estado').className = `badge badge-${t.estado}`;
    $('#ins-titulo').textContent = t.titulo;
    $('#ins-formato').textContent = t.formato_label;
    $('#ins-cupo').textContent = t.max_participantes ? `${t.inscritos} / ${t.max_participantes}` : `${t.inscritos} inscriptos`;
    $('#ins-fecha').textContent = t.fecha_inicio ? formatearFechaCorta(t.fecha_inicio) : 'A confirmar';

    $('#ins-form').addEventListener('submit', (e) => onSubmit(e, t));
    $('#ins-body').hidden = false;
}

async function onSubmit(e, t) {
    e.preventDefault();
    const msgEl = $('#ins-msg');
    const btn   = $('#ins-btn-confirmar');

    if (!$('#ins-reglamento').checked) {
        msgEl.textContent = 'Tenés que aceptar el reglamento para inscribirte.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Inscribiendo...';
    msgEl.textContent = '';
    msgEl.className = 'form-msg';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/register.php`, {
            method: 'POST',
            body: JSON.stringify({ torneo_id: Number(torneoId) }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo completar la inscripción.';
            msgEl.className = 'form-msg msg-error';
            btn.disabled = false;
            btn.textContent = 'Confirmar inscripción';
            return;
        }

        msgEl.textContent = data.mensaje || '¡Listo! Quedaste inscripto.';
        msgEl.className = 'form-msg msg-ok';
        btn.textContent = 'Inscripto ✓';
        $('#ins-btn-cancelar').textContent = 'Ver torneo';
        $('#ins-btn-cancelar').href = `detalle.html?id=${torneoId}`;

        setTimeout(() => { window.location.href = `detalle.html?id=${torneoId}`; }, 1200);
    } catch (err) {
        console.error('[inscripcion.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
        btn.disabled = false;
        btn.textContent = 'Confirmar inscripción';
    }
}

function normalizar(str) {
    return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}

function formatearFechaCorta(fechaSql) {
    const d = new Date(fechaSql.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return fechaSql;
    return d.toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' });
}
