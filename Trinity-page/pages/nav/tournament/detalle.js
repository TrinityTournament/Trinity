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

let torneoActual = null;

document.addEventListener('DOMContentLoaded', () => {
    wireGestion();
    init();
});

// El JSON usa como clave el nombre del juego en minúsculas y sin tildes
// ("brawl stars", "clash royale", "futbol"...). Si el juego no tiene
// entrada, se usa "default". Si tampoco hay "default", la carta se oculta.
let funFactsPromise = null;

function cargarFunFacts() {
    if (!funFactsPromise) {
        funFactsPromise = fetch('funfacts.json', { cache: 'no-store' })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .catch((err) => {
                console.error('[detalle.js] datos curiosos', err);
                funFactsPromise = null; // reintenta en el próximo render
                return null;
            });
    }
    return funFactsPromise;
}

async function mostrarFunFacts(deporte) {
    const card = $('#fun-facts');
    const data = await cargarFunFacts();

    const entrada = data && (data[normalizar(deporte)] || data.default);
    const lista = Array.isArray(entrada)
        ? entrada
        : entrada
            ? [entrada]
            : [];

    const valido = lista.filter(f =>
        f && typeof f.titulo === 'string' &&
        typeof f.texto === 'string' &&
        (f.titulo.trim() || f.texto.trim())
    );

    const elegido = valido.length
        ? valido[Math.floor(Math.random() * valido.length)]
        : null;

    const titulo = (elegido?.titulo || '').trim();
    const texto = (elegido?.texto || '').trim();

    if (!titulo && !texto) {
        card.hidden = true;
        return;
    }

    $('#fun-facts-title').textContent = titulo;
    $('#fun-facts-text').textContent  = texto;
    card.hidden = false;
}

async function init() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    if (!id) {
        return mostrarError('No se indicó qué torneo mostrar.');
    }

    try {
        // fetch directo (y no apiFetch): es un GET público y apiFetch responde a todo
        // 403 con una alerta + excepción, y un torneo privado ES un 403 esperable
        // que acá se muestra como mensaje en la página.
        const res  = await fetch(`${API_BASE_URL}/../app/tournaments/detail.php?id=${encodeURIComponent(id)}`, { credentials: 'include' });
        const data = await res.json().catch(() => ({}));

        if (res.status === 403) {
            return mostrarError(`${data.error || 'No tenés acceso a este torneo.'} Si te invitaron, iniciá sesión con tu cuenta.`);
        }
        if (!res.ok) {
            return mostrarError(data.error || 'No se pudo cargar el torneo.');
        }

        render(data);
    } catch (err) {
        console.error('[detalle.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#t-titulo').textContent = 'Torneo no disponible';
    $('#t-error-msg').textContent = msg;
    $('#t-error').hidden = false;
    $('#t-body').hidden = true;
    $('#fun-facts').hidden = true;
}

function render(t) {
    torneoActual = t;
    document.title = `${t.titulo} — Trinity`;

    const banner = BANNER_POR_DEPORTE[normalizar(t.deporte)];
    if (banner) {
        $('#tournament-banner').style.backgroundImage =
            `linear-gradient(180deg, rgba(10,0,0,.55), rgba(10,0,0,.95)), url('${banner}')`;
    }

    mostrarFunFacts(t.deporte);

    $('#t-badge-estado').textContent = t.estado_label;
    $('#t-badge-estado').className = `badge badge-${t.estado}`;
    $('#t-titulo').textContent = t.titulo;

    const fechaTxt = t.fecha_inicio ? formatearFecha(t.fecha_inicio) : 'Sin fecha aún';
    const cupoTxt  = t.max_participantes ? `${t.inscritos} / ${t.max_participantes} inscritos` : `${t.inscritos} inscritos`;
    $('#t-meta').innerHTML = `
        <span>${t.emoji} ${escapeHtml(t.formato_label)}</span>
        <span>👥 ${escapeHtml(cupoTxt)}</span>
        <span>📅 ${escapeHtml(fechaTxt)}</span>
        <span>🧑‍💼 Organiza: ${escapeHtml(t.organizador_nombre)}</span>
    `;

    $('#t-actions').innerHTML = buildActionsHtml(t);
    wireActions(t);
    renderOrgActions(t);
    renderGestion(t);

    $('#t-descripcion').textContent = t.descripcion || 'El organizador todavía no cargó una descripción.';
    $('#t-deporte').textContent   = t.deporte;
    $('#t-formato').textContent   = t.formato_label;
    $('#t-cupo').textContent      = t.max_participantes ? `${t.max_participantes} participantes` : 'Sin límite';
    $('#t-inscritos').textContent = cupoTxt;
    $('#t-fecha').textContent     = fechaTxt.replace(/^Inicia /, '');
    $('#t-organizador').innerHTML = `<a href="../../profile/acc/view.html?id=${t.organizador_id}">@${escapeHtml(t.organizador_usuario)}</a>`;
    $('#t-estado-dd').innerHTML   = `<span class="badge badge-${t.estado}">${escapeHtml(t.estado_label)}</span>`;

    renderParticipantes(t);

    $('#t-error').hidden = true;
    $('#t-body').hidden  = false;

    cargarPartidos(t);
}

function buildActionsHtml(t) {
    const volver = '<a href="buscar.html" class="btn-secondary">← Volver a torneos</a>';

    if (t.es_organizador) {
        return volver; // sus acciones están en "Información", más abajo
    }
    if (!t.logueado) {
        return `<a href="../../login/login.html" class="btn-primary">Iniciá sesión para inscribirte</a>${volver}`;
    }
    if (t.ya_inscripto) {
        return `<button type="button" class="btn-secondary" id="btn-cancelar-inscripcion">Cancelar inscripción</button>${volver}`;
    }
    // Invitación pendiente (también en un borrador: el organizador invita antes de abrir).
    if (t.invitacion_id && (t.estado === 'en_creacion' || t.estado === 'abierto')) {
        return `
            <button type="button" class="btn-primary" id="btn-aceptar-invitacion">Aceptar invitación</button>
            <button type="button" class="btn-secondary" id="btn-rechazar-invitacion">Rechazar</button>
            ${volver}`;
    }
    if (!t.inscripcion_abierta) {
        return `<button type="button" class="btn-primary" disabled>Inscripciones cerradas</button>${volver}`;
    }
    if (t.cupo_lleno) {
        return `<button type="button" class="btn-primary" disabled>Cupo completo</button>${volver}`;
    }
    return `<a href="inscripcion.html?id=${t.id}" class="btn-primary">Inscribirme</a>${volver}`;
}

// Responde la invitación pendiente del usuario (aceptar / rechazar).
async function responderInvitacion(t, aceptar, btn) {
    const textoOriginal = btn.textContent;
    document.querySelectorAll('#btn-aceptar-invitacion, #btn-rechazar-invitacion').forEach((b) => { b.disabled = true; });
    btn.textContent = 'Un momento...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/invitations.php`, {
            method: 'PATCH',
            body: JSON.stringify({ id: t.invitacion_id, aceptar }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo responder la invitación.');
            document.querySelectorAll('#btn-aceptar-invitacion, #btn-rechazar-invitacion').forEach((b) => { b.disabled = false; });
            btn.textContent = textoOriginal;
            return;
        }

        // Si rechaza un torneo privado ya no tiene acceso a verlo: se lo lleva al listado.
        if (!aceptar && t.visibilidad === 'privado') {
            window.location.href = 'tournament.html';
            return;
        }
        init();
    } catch (err) {
        console.error('[detalle.js]', err);
        alert('No se pudo conectar con el servidor.');
        document.querySelectorAll('#btn-aceptar-invitacion, #btn-rechazar-invitacion').forEach((b) => { b.disabled = false; });
        btn.textContent = textoOriginal;
    }
}

function wireActions(t) {
    const aceptar  = document.getElementById('btn-aceptar-invitacion');
    const rechazar = document.getElementById('btn-rechazar-invitacion');
    if (aceptar)  aceptar.addEventListener('click',  () => responderInvitacion(t, true,  aceptar));
    if (rechazar) rechazar.addEventListener('click', () => {
        if (confirm('¿Rechazar la invitación a este torneo?')) responderInvitacion(t, false, rechazar);
    });

    const btn = document.getElementById('btn-cancelar-inscripcion');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        if (!confirm('¿Seguro que querés cancelar tu inscripción a este torneo?')) return;
        btn.disabled = true;
        btn.textContent = 'Cancelando...';
        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/register.php?torneo_id=${t.id}`, { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || 'No se pudo cancelar la inscripción.');
                btn.disabled = false;
                btn.textContent = 'Cancelar inscripción';
                return;
            }
            init();
        } catch (err) {
            console.error('[detalle.js]', err);
            alert('No se pudo conectar con el servidor.');
            btn.disabled = false;
            btn.textContent = 'Cancelar inscripción';
        }
    });
}

// ── Acciones exclusivas del organizador ─────────────────────
// Qué puede hacer según el estado del torneo:
//   en_creacion → abrir inscripciones, iniciar, eliminar (si está vacío) / cancelar (si ya tiene gente)
//   abierto     → iniciar, cancelar
//   en_curso    → cargar resultados, cancelar
//   finalizado / cancelado → nada
function renderOrgActions(t) {
    const el = $('#t-org-actions');
    if (!t.es_organizador) {
        el.innerHTML = '';
        return;
    }

    const partes = [];

    if (t.estado === 'en_creacion' || t.estado === 'abierto') {
        const puedeIniciar = t.inscritos >= 2;
        if (t.estado === 'en_creacion') {
            partes.push('<p class="field-hint" style="width:100%;margin:0;">Este torneo es un borrador: todavía nadie puede inscribirse. Invitá jugadores o abrí las inscripciones.</p>');
            partes.push('<button type="button" class="btn-primary" id="btn-abrir-torneo">Abrir inscripciones</button>');
        }
        partes.push(`<button type="button" class="${t.estado === 'en_creacion' ? 'btn-secondary' : 'btn-primary'}" id="btn-iniciar-torneo" ${puedeIniciar ? '' : 'disabled'}>Iniciar torneo</button>`);
        if (!puedeIniciar) {
            partes.push('<span class="field-hint">Necesitás al menos 2 participantes para iniciar.</span>');
        }
    } else if (t.estado === 'en_curso') {
        partes.push(`<a href="resultados.html?id=${t.id}" class="btn-primary">Cargar resultados</a>`);
    }

    if (t.estado === 'en_creacion' && t.inscritos === 0) {
        partes.push('<button type="button" class="btn-ghost btn-danger-ghost" id="btn-borrar-borrador">Eliminar borrador</button>');
    } else if (['en_creacion', 'abierto', 'en_curso'].includes(t.estado)) {
        partes.push('<button type="button" class="btn-ghost btn-danger-ghost" id="btn-cancelar-torneo">Cancelar torneo</button>');
    }

    el.innerHTML = partes.join('\n');

    on('#btn-iniciar-torneo', () => !$('#btn-iniciar-torneo').disabled && iniciarTorneo());
    on('#btn-abrir-torneo', abrirTorneo);
    on('#btn-cancelar-torneo', cancelarTorneo);
    on('#btn-borrar-borrador', borrarBorrador);
}

function on(sel, handler) {
    const el = $(sel);
    if (el) el.addEventListener('click', handler);
}

// Pide a un endpoint de organizador, bloquea el botón mientras tanto y
// recarga la ficha al terminar (salvo que se indique otra cosa en onOk).
async function accionOrganizador(btn, { url, method = 'POST', body, confirmar, errorMsg, onOk }) {
    if (confirmar && !confirm(confirmar)) return;

    const textoOriginal = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Un momento...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/${url}`, {
            method,
            ...(body ? { body: JSON.stringify(body) } : {}),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || errorMsg);
            btn.disabled = false;
            btn.textContent = textoOriginal;
            return;
        }
        onOk ? onOk(data) : init();
    } catch (err) {
        console.error('[detalle.js]', err);
        alert('No se pudo conectar con el servidor.');
        btn.disabled = false;
        btn.textContent = textoOriginal;
    }
}

function abrirTorneo(ev) {
    const t = torneoActual;
    const aviso = t.visibilidad === 'publico'
        ? ` Como es público, se avisará a los usuarios interesados en ${t.deporte}.`
        : '';
    accionOrganizador(ev.currentTarget, {
        url: 'open.php',
        body: { torneo_id: t.id },
        confirmar: `¿Abrir las inscripciones de "${t.titulo}"?${aviso}`,
        errorMsg: 'No se pudo abrir el torneo.',
    });
}

function cancelarTorneo(ev) {
    const t = torneoActual;
    accionOrganizador(ev.currentTarget, {
        url: 'cancel.php',
        body: { torneo_id: t.id },
        confirmar: `¿Cancelar "${t.titulo}"? Se avisará a los participantes y no se puede deshacer.`,
        errorMsg: 'No se pudo cancelar el torneo.',
    });
}

function borrarBorrador(ev) {
    const t = torneoActual;
    accionOrganizador(ev.currentTarget, {
        url: `delete.php?torneo_id=${t.id}`,
        method: 'DELETE',
        confirmar: `¿Eliminar el borrador "${t.titulo}"? No se puede deshacer.`,
        errorMsg: 'No se pudo eliminar el borrador.',
        onOk: () => { window.location.href = '../../organizador/index.html'; },
    });
}

async function iniciarTorneo() {
    if (!confirm('¿Iniciar el torneo? Una vez iniciado no se pueden agregar ni sacar participantes.')) return;

    const btn = $('#btn-iniciar-torneo');
    btn.disabled = true;
    btn.textContent = 'Iniciando...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/start.php`, {
            method: 'POST',
            body: JSON.stringify({ torneo_id: torneoActual.id }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo iniciar el torneo.');
            btn.disabled = false;
            btn.textContent = 'Iniciar torneo';
            return;
        }

        init();
    } catch (err) {
        console.error('[detalle.js]', err);
        alert('No se pudo conectar con el servidor.');
        btn.disabled = false;
        btn.textContent = 'Iniciar torneo';
    }
}

// ── Panel "Gestionar torneo": invitar jugadores y editar ─────
const FORMATO_A_ETIQUETA = {
    liga:        'Liga',
    eliminacion: 'Eliminación directa',
    suizo:       'Sistema suizo',
};

let invitarTimer = null;
let ultimaBusqueda = new Map();

// Los elementos del panel existen siempre en el HTML: se enganchan una sola vez.
function wireGestion() {
    $('#t-invite-q').addEventListener('input', () => {
        clearTimeout(invitarTimer);
        const q = $('#t-invite-q').value.trim();
        if (q.length < 2) {
            $('#t-invite-results').innerHTML = '';
            return;
        }
        invitarTimer = setTimeout(() => buscarInvitables(q), 300);
    });

    $('#t-invite-results').addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-invitar]');
        if (btn) invitar(btn);
    });

    $('#t-edit-form').addEventListener('submit', guardarEdicion);
}

function renderGestion(t) {
    const editable = t.es_organizador && (t.estado === 'en_creacion' || t.estado === 'abierto');
    $('#gestionar').hidden     = !editable;
    $('#nav-gestionar').hidden = !editable;
    if (!editable) return;

    $('#t-invite-hint').textContent = t.visibilidad === 'privado'
        ? 'Este torneo es privado: solo participan los jugadores que invites.'
        : 'El torneo es público, pero también podés invitar jugadores directamente.';

    $('#t-invite-q').value = '';
    $('#t-invite-results').innerHTML = '';

    $('#te-titulo').value     = t.titulo || '';
    $('#te-cupo').value       = t.max_participantes || '';
    $('#te-fecha').value      = (t.fecha_inicio || '').slice(0, 10);
    $('#te-formato').value    = FORMATO_A_ETIQUETA[t.formato] || 'Liga';
    $('#te-visibilidad').value = t.visibilidad === 'privado' ? 'Privado (solo con invitación)' : 'Público';
    $('#te-desc').value       = t.descripcion || '';
    setMsgEdicion('');

    renderInvitaciones(t);
}

function setMsgEdicion(texto, tipo) {
    const el = $('#te-msg');
    el.textContent = texto;
    el.classList.remove('msg-error', 'msg-ok');
    if (tipo) el.classList.add(tipo === 'ok' ? 'msg-ok' : 'msg-error');
}

function renderInvitaciones(t) {
    const body  = $('#t-invitaciones-body');
    const lista = t.invitaciones || [];
    if (!lista.length) {
        body.innerHTML = '<tr><td colspan="2">Todavía no enviaste invitaciones.</td></tr>';
        return;
    }
    const etiqueta = { pendiente: 'Pendiente', aceptada: 'Aceptada', rechazada: 'Rechazada' };
    body.innerHTML = lista.map((i) => `
        <tr>
            <td><a href="../../profile/acc/view.html?id=${i.invitado_id}">${escapeHtml(i.nombre)} (@${escapeHtml(i.usuario)})</a></td>
            <td><span class="badge badge-${escapeHtml(i.estado)}">${escapeHtml(etiqueta[i.estado] || i.estado)}</span></td>
        </tr>
    `).join('');
}

async function buscarInvitables(q) {
    const ul = $('#t-invite-results');
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/users/search-users.php?q=${encodeURIComponent(q)}`);
        const data = await res.json().catch(() => ({}));

        // Si el usuario siguió escribiendo, esta respuesta ya quedó vieja.
        if (q !== $('#t-invite-q').value.trim()) return;

        if (!res.ok) {
            ul.innerHTML = `<li class="invite-empty">${escapeHtml(data.error || 'No se pudo buscar.')}</li>`;
            return;
        }

        const t = torneoActual;
        const excluidos = new Set([
            Number(t.organizador_id),
            ...(t.participantes || []).map((p) => Number(p.id)),
            ...(t.invitaciones  || []).map((i) => Number(i.invitado_id)),
        ]);
        const lista = (data.users || []).filter((u) => !excluidos.has(Number(u.id)));

        ultimaBusqueda = new Map(lista.map((u) => [Number(u.id), u]));

        ul.innerHTML = lista.length
            ? lista.map((u) => `
                <li>
                    <span class="invite-who">${escapeHtml(u.nombre)} (@${escapeHtml(u.usuario)})</span>
                    <button type="button" class="btn-secondary btn-sm" data-invitar="${Number(u.id)}">Invitar</button>
                </li>`).join('')
            : '<li class="invite-empty">No hay usuarios para invitar con esa búsqueda.</li>';
    } catch (err) {
        console.error('[detalle.js]', err);
        ul.innerHTML = '<li class="invite-empty">No se pudo conectar con el servidor.</li>';
    }
}

async function invitar(btn) {
    const id   = Number(btn.dataset.invitar);
    const user = ultimaBusqueda.get(id);

    btn.disabled = true;
    btn.textContent = 'Enviando...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/invite-tournament.php`, {
            method: 'POST',
            body: JSON.stringify({ torneo_id: torneoActual.id, invitado_id: id }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo enviar la invitación.');
            btn.disabled = false;
            btn.textContent = 'Invitar';
            return;
        }

        btn.textContent = 'Invitado ✓';
        torneoActual.invitaciones = torneoActual.invitaciones || [];
        torneoActual.invitaciones.unshift({
            invitado_id: id,
            nombre:  user ? user.nombre  : '',
            usuario: user ? user.usuario : '',
            estado:  'pendiente',
        });
        renderInvitaciones(torneoActual);
    } catch (err) {
        console.error('[detalle.js]', err);
        alert('No se pudo conectar con el servidor.');
        btn.disabled = false;
        btn.textContent = 'Invitar';
    }
}

async function guardarEdicion(ev) {
    ev.preventDefault();
    const btn  = $('#btn-guardar-edicion');
    const cupo = parseInt($('#te-cupo').value, 10);

    btn.disabled = true;
    setMsgEdicion('Guardando...');

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/update.php`, {
            method: 'POST',
            body: JSON.stringify({
                torneo_id:         torneoActual.id,
                titulo:            $('#te-titulo').value,
                descripcion:       $('#te-desc').value,
                formato:           $('#te-formato').value,
                max_participantes: Number.isFinite(cupo) ? cupo : 0,
                fecha_inicio:      $('#te-fecha').value,
                visibilidad:       $('#te-visibilidad').value,
            }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            setMsgEdicion(data.error || 'No se pudieron guardar los cambios.', 'error');
            btn.disabled = false;
            return;
        }

        await init();                         // recarga la ficha con los datos nuevos
        setMsgEdicion('Cambios guardados.', 'ok');
        btn.disabled = false;
    } catch (err) {
        console.error('[detalle.js]', err);
        setMsgEdicion('No se pudo conectar con el servidor.', 'error');
        btn.disabled = false;
    }
}

function renderParticipantes(t) {
    const lista = t.participantes;
    const puedeQuitar = t.es_organizador && (t.estado === 'en_creacion' || t.estado === 'abierto');
    $('#t-th-quitar').hidden = !puedeQuitar;

    const body = $('#t-participantes-body');
    if (!lista || lista.length === 0) {
        body.innerHTML = `<tr><td colspan="${puedeQuitar ? 4 : 3}">Todavía no hay nadie inscripto. ¡Sé el primero!</td></tr>`;
        return;
    }

    body.innerHTML = lista.map((p, i) => `
        <tr>
            <td class="num">${i + 1}</td>
            <td><a href="../../profile/acc/view.html?id=${p.id}">${escapeHtml(p.nombre)} (@${escapeHtml(p.usuario)})</a></td>
            <td>${formatearFechaCorta(p.inscrito_en)}</td>
            ${puedeQuitar ? `<td><button type="button" class="btn-ghost btn-sm" data-quitar="${p.id}">Quitar</button></td>` : ''}
        </tr>
    `).join('');

    if (puedeQuitar) {
        document.querySelectorAll('#t-participantes-body [data-quitar]').forEach((btn) => {
            btn.addEventListener('click', () => quitarParticipante(Number(btn.dataset.quitar)));
        });
    }
}

async function quitarParticipante(usuarioId) {
    if (!confirm('¿Sacar a este participante del torneo?')) return;
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/remove-participant.php?torneo_id=${torneoActual.id}&usuario_id=${usuarioId}`, { method: 'DELETE' });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.error || 'No se pudo sacar al participante.');
            return;
        }
        init();
    } catch (err) {
        console.error('[detalle.js]', err);
        alert('No se pudo conectar con el servidor.');
    }
}

// ── Calendario / Resultados / Posiciones / Llaves ───────────
async function cargarPartidos(t) {
    if (t.estado !== 'en_curso' && t.estado !== 'finalizado') {
        return;
    }

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/matches.php?torneo_id=${t.id}`);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            return;
        }
        renderMatches(t, data);
    } catch (err) {
        console.error('[detalle.js] matches', err);
    }
}

function renderMatches(t, data) {
    const todos = data.rondas.flatMap((r) => r.partidos.map((p) => ({ ...p, ronda_etiqueta: r.etiqueta })));
    const pendientes = todos.filter((p) => p.estado === 'pendiente' && p.participante1_id && p.participante2_id);
    const jugados = todos.filter((p) => p.estado !== 'pendiente');

    if (pendientes.length > 0) {
        $('#nav-calendario').hidden = false;
        $('#calendario').hidden = false;
        $('#t-calendario-body').innerHTML = pendientes.slice(0, 6).map((p) => `
            <tr>
                <td>${escapeHtml(p.ronda_etiqueta)}</td>
                <td>${nombreParticipanteEnPartido(p, 1)}</td>
                <td class="num">vs</td>
                <td>${nombreParticipanteEnPartido(p, 2)}</td>
            </tr>
        `).join('');
    }

    if (jugados.length > 0) {
        $('#nav-resultados').hidden = false;
        $('#resultados').hidden = false;
        const recientes = [...jugados].sort((a, b) => b.id - a.id).slice(0, 6);
        $('#t-resultados-body').innerHTML = recientes.map((p) => `
            <tr>
                <td>${escapeHtml(p.ronda_etiqueta)}</td>
                <td>${nombreParticipanteEnPartido(p, 1)}</td>
                <td class="num">${resultadoTexto(p)}</td>
                <td>${nombreParticipanteEnPartido(p, 2)}</td>
            </tr>
        `).join('');
    }

    if (data.standings) {
        $('#nav-posiciones').hidden = false;
        $('#posiciones').hidden = false;
        $('#t-link-posiciones').href = `formatos/${t.formato}.html?id=${t.id}`;
        $('#t-posiciones-body').innerHTML = data.standings.slice(0, 8).map((s, i) => `
            <tr>
                <td class="num">${i + 1}</td>
                <td>${escapeHtml(s.nombre)}</td>
                <td class="num">${s.pj}</td>
                <td class="num">${s.pg}</td>
                <td class="num">${s.pe}</td>
                <td class="num">${s.pp}</td>
                <td class="num">${s.pts}</td>
            </tr>
        `).join('');
    }

    if (t.formato === 'eliminacion' && data.rondas.length > 0) {
        $('#nav-llaves').hidden = false;
        $('#llaves').hidden = false;
        $('#t-link-llaves').href = `formatos/eliminacion.html?id=${t.id}`;
        $('#t-llaves-preview').innerHTML = renderBracketCompacto(data);
    }
}

function nombreParticipanteEnPartido(p, lado) {
    const id = lado === 1 ? p.participante1_id : p.participante2_id;
    const nombre = lado === 1 ? p.participante1_nombre : p.participante2_nombre;
    if (!id) return '<span style="color:var(--text-muted);">Bye</span>';
    return escapeHtml(nombre);
}

function resultadoTexto(p) {
    if (p.estado === 'wo') return 'W.O.';
    if (p.resultado1 === null || p.resultado2 === null) return '—';
    return `${p.resultado1} — ${p.resultado2}`;
}

function renderBracketCompacto(data) {
    const rondasHtml = data.rondas.map((r) => `
        <section class="bracket-round">
            <h3 class="bracket-round-title">${escapeHtml(r.etiqueta)}</h3>
            ${r.partidos.map((p) => `
                <article class="bracket-pair">
                    <div class="bracket-slot ${p.participante1_id && p.ganador_id === p.participante1_id ? 'winner' : (p.estado === 'pendiente' ? 'pending' : '')}">
                        <span>${p.participante1_nombre ? escapeHtml(p.participante1_nombre) : (p.participante2_id ? 'Por definir' : '—')}</span>
                        <span class="num">${p.resultado1 ?? (p.estado === 'pendiente' ? '—' : '')}</span>
                    </div>
                    <div class="bracket-slot ${p.participante2_id && p.ganador_id === p.participante2_id ? 'winner' : (p.estado === 'pendiente' ? 'pending' : '')}">
                        <span>${p.participante2_nombre ? escapeHtml(p.participante2_nombre) : (p.participante1_id ? 'Por definir' : '—')}</span>
                        <span class="num">${p.resultado2 ?? (p.estado === 'pendiente' ? '—' : '')}</span>
                    </div>
                </article>
            `).join('')}
        </section>
    `).join('');

    const campeonHtml = data.campeon ? `
        <section class="bracket-round">
            <h3 class="bracket-round-title">Campeón</h3>
            <div class="bracket-champion"><span>🏆</span><span>${escapeHtml(data.campeon.nombre)}</span></div>
        </section>
    ` : '';

    return `<div class="bracket">${rondasHtml}${campeonHtml}</div>`;
}

function normalizar(str) {
    return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}

function formatearFecha(fechaSql) {
    const d = new Date(fechaSql.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return fechaSql;
    return `Inicia ${d.toLocaleDateString('es-UY', { day: 'numeric', month: 'short', year: 'numeric' })}`;
}

function formatearFechaCorta(fechaSql) {
    const d = new Date(fechaSql.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return fechaSql;
    return d.toLocaleDateString('es-UY', { day: 'numeric', month: 'short' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}