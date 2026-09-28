const $ = (sel) => document.querySelector(sel);

let torneoId = null;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    const params = new URLSearchParams(window.location.search);
    torneoId = params.get('id');
    if (!torneoId) return mostrarError('No se indicó qué torneo gestionar.');

    $('#r-volver').href = `detalle.html?id=${torneoId}`;

    await cargar();
}

async function cargar() {
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/results-manager.php?torneo_id=${torneoId}`);
        const data = await res.json().catch(() => ({}));

        if (res.status === 401) return mostrarError('Iniciá sesión para gestionar este torneo.');
        if (!res.ok) return mostrarError(data.error || 'No se pudo cargar la información del torneo.');

        document.title = `Resultados — ${data.titulo} — Trinity`;
        $('#r-eyebrow').textContent = data.titulo;

        if (data.estado !== 'en_curso') {
            return mostrarError(data.estado === 'finalizado'
                ? 'Este torneo ya finalizó — no se pueden cargar más resultados.'
                : 'Este torneo todavía no está en curso.');
        }

        render(data);
    } catch (err) {
        console.error('[resultados.js]', err);
        mostrarError('No se pudo conectar con el servidor.');
    }
}

function mostrarError(msg) {
    $('#r-error-msg').textContent = msg;
    $('#r-error').hidden = false;
    $('#r-body').hidden = true;
}

function render(data) {
    $('#r-siguiente-ronda').hidden = !data.puede_generar_siguiente_ronda;
    if (data.puede_generar_siguiente_ronda) {
        $('#r-btn-siguiente-ronda').onclick = generarSiguienteRonda;
    }

    renderPendientes(data.pendientes);
    renderHistorial(data.historial);

    $('#r-error').hidden = true;
    $('#r-body').hidden = false;
}

function renderPendientes(lista) {
    const el = $('#r-pendientes');
    if (lista.length === 0) {
        el.innerHTML = '<p class="no-tournaments">No hay enfrentamientos pendientes de resultado ahora mismo.</p>';
        return;
    }

    el.innerHTML = lista.map((p) => `
        <form class="result-row" data-partido="${p.id}">
            <div class="result-match">
                <span class="result-round">${escapeHtml(p.ronda_etiqueta)}</span>
                <span>${escapeHtml(p.participante1_nombre)}</span>
                <input class="field-input score-input" type="number" min="0" placeholder="0" data-r="1">
                <span class="num">vs</span>
                <input class="field-input score-input" type="number" min="0" placeholder="0" data-r="2">
                <span>${escapeHtml(p.participante2_nombre)}</span>
            </div>
            <button type="submit" class="btn-primary btn-sm">Guardar</button>
        </form>
    `).join('');

    el.querySelectorAll('form[data-partido]').forEach((form) => {
        form.addEventListener('submit', onSubmitResultado);
    });
}

async function onSubmitResultado(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const partidoId = Number(form.dataset.partido);
    const r1 = form.querySelector('[data-r="1"]').value;
    const r2 = form.querySelector('[data-r="2"]').value;

    if (r1 === '' || r2 === '') {
        alert('Completá el resultado de ambos participantes.');
        return;
    }

    const btn = form.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/record-result.php`, {
            method: 'POST',
            body: JSON.stringify({ torneo_id: Number(torneoId), partido_id: partidoId, resultado1: r1, resultado2: r2 }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo guardar el resultado.');
            btn.disabled = false;
            btn.textContent = 'Guardar';
            return;
        }

        await cargar();
    } catch (err) {
        console.error('[resultados.js]', err);
        alert('No se pudo conectar con el servidor.');
        btn.disabled = false;
        btn.textContent = 'Guardar';
    }
}

async function generarSiguienteRonda() {
    const btn = $('#r-btn-siguiente-ronda');
    btn.disabled = true;
    btn.textContent = 'Generando...';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/next-round.php`, {
            method: 'POST',
            body: JSON.stringify({ torneo_id: Number(torneoId) }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            alert(data.error || 'No se pudo generar la siguiente ronda.');
            btn.disabled = false;
            btn.textContent = 'Generar siguiente ronda';
            return;
        }

        await cargar();
    } catch (err) {
        console.error('[resultados.js]', err);
        alert('No se pudo conectar con el servidor.');
        btn.disabled = false;
        btn.textContent = 'Generar siguiente ronda';
    }
}

function renderHistorial(lista) {
    const body = $('#r-historial-body');
    if (lista.length === 0) {
        body.innerHTML = '<tr><td colspan="4">Todavía no se cargó ningún resultado.</td></tr>';
        return;
    }

    body.innerHTML = lista.map((p) => `
        <tr>
            <td>${escapeHtml(p.ronda_etiqueta)}</td>
            <td>${p.participante1_nombre ? escapeHtml(p.participante1_nombre) : '<span style="color:var(--text-muted);">Bye</span>'}</td>
            <td class="num">${p.estado === 'wo' ? 'W.O.' : `${p.resultado1} — ${p.resultado2}`}</td>
            <td>${p.participante2_nombre ? escapeHtml(p.participante2_nombre) : '<span style="color:var(--text-muted);">Bye</span>'}</td>
        </tr>
    `).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
