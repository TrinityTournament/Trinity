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
        if (data.formato !== 'eliminacion') return mostrarError('Este torneo no es de eliminación directa.');
        if (!resM.ok || data.rondas.length === 0) return mostrarError('Todavía no se generó el cuadro de este torneo.');

        document.title = `${t.titulo} — Llaves — Trinity`;
        $('#f-eyebrow').textContent = t.titulo;
        render(data);
    } catch (err) {
        console.error('[eliminacion.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#f-error-msg').textContent = msg;
    $('#f-error').hidden = false;
    $('#f-body').hidden = true;
}

function render(data) {
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

    $('#f-bracket').innerHTML = rondasHtml + campeonHtml;
    $('#f-body').hidden = false;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
