const $ = (sel) => document.querySelector(sel);

const BANNER_PATH = '../../../assets/cards/tournament-banner/';
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

document.addEventListener('DOMContentLoaded', init);

async function init() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    if (!id) {
        return mostrarError('No se indicó qué torneo mostrar.');
    }

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/detail.php?id=${encodeURIComponent(id)}`);
        const data = await res.json().catch(() => ({}));

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
}

function render(t) {
    torneoActual = t;
    document.title = `${t.titulo} — Trinity`;

    const banner = BANNER_POR_DEPORTE[normalizar(t.deporte)];
    if (banner) {
        $('#tournament-banner').style.backgroundImage =
            `linear-gradient(180deg, rgba(10,0,0,.55), rgba(10,0,0,.95)), url('${banner}')`;
    }

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
    if (!t.inscripcion_abierta) {
        return `<button type="button" class="btn-primary" disabled>Inscripciones cerradas</button>${volver}`;
    }
    if (t.cupo_lleno) {
        return `<button type="button" class="btn-primary" disabled>Cupo completo</button>${volver}`;
    }
    return `<a href="inscripcion.html?id=${t.id}" class="btn-primary">Inscribirme</a>${volver}`;
}

function wireActions(t) {
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
function renderOrgActions(t) {
    const el = $('#t-org-actions');
    if (!t.es_organizador) {
        el.innerHTML = '';
        return;
    }

    if (t.estado === 'en_creacion' || t.estado === 'abierto') {
        const puedeIniciar = t.inscritos >= 2;
        el.innerHTML = `
            <button type="button" class="btn-primary" id="btn-iniciar-torneo" ${puedeIniciar ? '' : 'disabled'}>Iniciar torneo</button>
            ${puedeIniciar ? '' : '<span class="field-hint">Necesitás al menos 2 participantes para iniciar.</span>'}
        `;
        if (puedeIniciar) {
            $('#btn-iniciar-torneo').addEventListener('click', iniciarTorneo);
        }
    } else if (t.estado === 'en_curso') {
        el.innerHTML = `<a href="resultados.html?id=${t.id}" class="btn-primary">Cargar resultados</a>`;
    } else {
        el.innerHTML = '';
    }
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
