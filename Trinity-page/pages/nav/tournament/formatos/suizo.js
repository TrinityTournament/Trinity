const $ = (sel) => document.querySelector(sel);

let vistaData = null;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (!id) return mostrarError('No se indicó qué torneo mostrar.');

    $('#f-volver').href = `../detalle.html?id=${id}`;

    try {
        const [resT, resM] = await Promise.all([
            apiFetch(`${API_BASE_URL}/../app/tournaments/detail.php?id=${id}`),
            apiFetch(`${API_BASE_URL}/../app/tournaments/matches.php?torneo_id=${id}`),
        ]);
        const t = await resT.json().catch(() => ({}));
        const data = await resM.json().catch(() => ({}));

        if (!resT.ok) return mostrarError(t.error || 'No se pudo cargar el torneo.');
        if (data.formato !== 'suizo') return mostrarError('Este torneo no es formato suizo.');
        if (!resM.ok || data.rondas.length === 0) return mostrarError('Todavía no se generó la primera ronda de este torneo.');

        document.title = `${t.titulo} — Sistema suizo — Trinity`;
        $('#f-eyebrow').textContent = t.titulo;
        vistaData = data;
        renderTabs(data);
        renderRonda(data.rondas[data.rondas.length - 1].numero);
        renderPuntajes(data.standings);
        $('#f-body').hidden = false;
    } catch (err) {
        console.error('[suizo.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#f-error-msg').textContent = msg;
    $('#f-error').hidden = false;
    $('#f-body').hidden = true;
}

function renderTabs(data) {
    const total = data.total_rondas_suizo || data.rondas.length;
    const generadas = data.rondas.map((r) => r.numero);

    let html = '';
    for (let i = 1; i <= total; i++) {
        const generada = generadas.includes(i);
        html += `<span class="round-tab${!generada ? ' disabled' : ''}" data-ronda="${i}">R${i}</span>`;
    }
    $('#f-round-tabs').innerHTML = html;

    document.querySelectorAll('#f-round-tabs .round-tab:not(.disabled)').forEach((tab) => {
        tab.addEventListener('click', () => renderRonda(Number(tab.dataset.ronda)));
    });
}

function renderRonda(numero) {
    document.querySelectorAll('#f-round-tabs .round-tab').forEach((tab) => {
        tab.classList.toggle('active', Number(tab.dataset.ronda) === numero);
    });

    const ronda = vistaData.rondas.find((r) => r.numero === numero);
    $('#f-ronda-titulo').textContent = `Emparejamientos — Ronda ${numero} de ${vistaData.total_rondas_suizo || vistaData.rondas.length}`;

    const puntosPorId = {};
    vistaData.standings.forEach((s) => { puntosPorId[s.id] = s.pts; });

    if (!ronda) {
        $('#f-mesas-body').innerHTML = '<tr><td colspan="5">Esta ronda todavía no se generó.</td></tr>';
        return;
    }

    $('#f-mesas-body').innerHTML = ronda.partidos.map((p, i) => `
        <tr>
            <td class="num">${i + 1}</td>
            <td>${nombreConPuntos(p.participante1_id, p.participante1_nombre, puntosPorId)}</td>
            <td class="num">vs</td>
            <td>${p.participante2_id ? nombreConPuntos(p.participante2_id, p.participante2_nombre, puntosPorId) : '<span style="color:var(--text-muted);">Bye</span>'}</td>
            <td>${estadoBadge(p)}</td>
        </tr>
    `).join('');
}

function nombreConPuntos(id, nombre, puntosPorId) {
    const pts = puntosPorId[id] ?? 0;
    return `${escapeHtml(nombre)} (${pts})`;
}

function estadoBadge(p) {
    if (p.estado === 'pendiente') return '<span class="badge badge-en_creacion">Por jugar</span>';
    if (p.estado === 'wo') return '<span class="badge badge-abierto">Bye</span>';
    return `<span class="badge badge-abierto">${p.resultado1} — ${p.resultado2}</span>`;
}

function renderPuntajes(standings) {
    $('#f-puntajes-body').innerHTML = standings.map((s, i) => `
        <tr>
            <td class="num">${i + 1}</td>
            <td>${escapeHtml(s.nombre)}</td>
            <td class="num">${s.pj}</td>
            <td class="num">${s.pts}</td>
        </tr>
    `).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
