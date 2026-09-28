const $ = (sel) => document.querySelector(sel);

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
        if (data.formato !== 'liga') return mostrarError('Este torneo no es formato liga.');
        if (!resM.ok || data.rondas.length === 0) return mostrarError('Todavía no se generó el calendario de este torneo.');

        document.title = `${t.titulo} — Posiciones — Trinity`;
        $('#f-eyebrow').textContent = t.titulo;
        render(data);
    } catch (err) {
        console.error('[liga.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#f-error-msg').textContent = msg;
    $('#f-error').hidden = false;
    $('#f-body').hidden = true;
}

function render(data) {
    $('#f-tabla-body').innerHTML = data.standings.map((s, i) => `
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

    $('#f-calendario').innerHTML = data.rondas.map((r) => `
        <h3 class="bracket-round-title" style="text-align:left;margin:1.25rem 0 .5rem;">${escapeHtml(r.etiqueta)}</h3>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead><tr><th>Local</th><th></th><th>Visitante</th><th>Resultado</th></tr></thead>
                <tbody>
                    ${r.partidos.map((p) => `
                        <tr>
                            <td>${escapeHtml(p.participante1_nombre || '—')}</td>
                            <td class="num">vs</td>
                            <td>${escapeHtml(p.participante2_nombre || '—')}</td>
                            <td class="num">${resultadoTexto(p)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `).join('');

    $('#f-body').hidden = false;
}

function resultadoTexto(p) {
    if (p.estado === 'pendiente') return '—';
    if (p.resultado1 === null || p.resultado2 === null) return '—';
    return `${p.resultado1} — ${p.resultado2}`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
