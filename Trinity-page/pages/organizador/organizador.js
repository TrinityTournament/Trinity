document.addEventListener('DOMContentLoaded', cargarMisTorneos);

async function cargarMisTorneos() {
    const body = document.getElementById('mis-torneos-body');
    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/users/get-my-tournaments.php`);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            body.innerHTML = `<tr><td colspan="6">${escapeHtml(data.error || 'No se pudieron cargar tus torneos.')}</td></tr>`;
            return;
        }

        const torneos = data.torneos || [];
        if (torneos.length === 0) {
            body.innerHTML = '<tr><td colspan="6">Todavía no organizaste ningún torneo.</td></tr>';
            return;
        }

        body.innerHTML = torneos.map((t) => {
            const cupo = t.max_participantes ? `${t.inscritos}/${t.max_participantes}` : `${t.inscritos}`;
            const accion = t.estado === 'en_creacion' || t.estado === 'abierto' ? 'Gestionar'
                : t.estado === 'en_curso' ? 'Cargar resultados'
                : 'Ver';
            return `
                <tr>
                    <td>${escapeHtml(t.titulo)}</td>
                    <td>${escapeHtml(t.deporte)}</td>
                    <td>${escapeHtml(t.formato_label)}</td>
                    <td><span class="badge badge-${t.estado}">${escapeHtml(t.estado_label)}</span></td>
                    <td class="num">${cupo}</td>
                    <td><a href="../nav/tournament/detalle.html?id=${t.id}" class="btn-ghost btn-sm">${accion}</a></td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        console.error('[organizador.js]', err);
        body.innerHTML = '<tr><td colspan="6">No se pudo conectar con el servidor.</td></tr>';
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
