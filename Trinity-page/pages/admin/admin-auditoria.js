// ══════════════════════════════════════════════════════════
//  TRINITY — Panel admin: consulta de auditoría (solo lectura)
//  Endpoint: app/admin/auditoria.php  (ver docs/auditoria.md)
// ══════════════════════════════════════════════════════════
(() => {
    const $ = (sel) => document.querySelector(sel);

    let paginaActual = 1;
    let totalPaginas = 1;

    document.addEventListener('DOMContentLoaded', () => {
        if (!$('#aud-body')) return;

        $('#aud-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargar(1); });
        $('#aud-limpiar').addEventListener('click', () => { $('#aud-filtros').reset(); cargar(1); });
        $('#aud-prev').addEventListener('click', () => cargar(paginaActual - 1));
        $('#aud-next').addEventListener('click', () => cargar(paginaActual + 1));
        $('#aud-body').addEventListener('click', alternarDetalle);

        cargar(1);
    });

    function parametros(pagina) {
        const q = new URLSearchParams({ pagina: String(pagina), por_pagina: '25' });
        const campos = {
            tabla: '#aud-tabla', accion: '#aud-accion', registro_id: '#aud-registro',
            usuario_id: '#aud-usuario', desde: '#aud-desde', hasta: '#aud-hasta',
        };
        for (const [clave, sel] of Object.entries(campos)) {
            const v = ($(sel).value || '').trim();
            if (v) q.set(clave, v);
        }
        return q;
    }

    async function cargar(pagina) {
        const body = $('#aud-body');
        body.innerHTML = '<tr><td colspan="8" class="table-loading">Cargando...</td></tr>';

        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/admin/auditoria.php?${parametros(pagina)}`);
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                body.innerHTML = `<tr><td colspan="8" class="table-loading">${escapeHtml(data.error || 'No se pudo cargar la auditoría.')}</td></tr>`;
                return;
            }

            paginaActual = data.pagina;
            totalPaginas = data.paginas;
            llenarTablas(data.tablas);

            $('#aud-sub').textContent = `${data.total} evento${data.total === 1 ? '' : 's'} registrado${data.total === 1 ? '' : 's'}`;
            $('#aud-pagina').textContent = `Página ${paginaActual} de ${totalPaginas}`;
            $('#aud-prev').disabled = paginaActual <= 1;
            $('#aud-next').disabled = paginaActual >= totalPaginas;

            if (!data.eventos.length) {
                body.innerHTML = '<tr><td colspan="8" class="table-loading">No hay eventos con esos filtros.</td></tr>';
                return;
            }

            body.innerHTML = data.eventos.map(filaHtml).join('');
        } catch (err) {
            body.innerHTML = '<tr><td colspan="8" class="table-loading">Error de conexión.</td></tr>';
        }
    }

    // El <select> de tablas se llena una sola vez con lo que devuelve la API.
    function llenarTablas(tablas) {
        const sel = $('#aud-tabla');
        if (sel.options.length > 1 || !Array.isArray(tablas)) return;
        tablas.forEach((t) => sel.add(new Option(t, t)));
    }

    function filaHtml(e) {
        const quien = e.usuario_id
            ? `@${escapeHtml(e.usuario_nombre || '¿?')} <small>(#${e.usuario_id})</small>`
            : '<span class="aud-sistema">sistema / consola</span>';
        const cascada = (e.datos_antes && e.datos_antes._cascada) || (e.datos_despues && e.datos_despues._cascada);
        const nota = cascada ? ` <small class="aud-cascada" title="Borrado/desvinculado en cascada por ${escapeHtml(cascada)}">cascada</small>` : '';
        const json = JSON.stringify({ antes: e.datos_antes, despues: e.datos_despues }, null, 2);

        return `
        <tr>
            <td>${e.id}</td>
            <td>${escapeHtml(e.creado_en)}</td>
            <td>${quien}</td>
            <td>${escapeHtml(e.tabla)}</td>
            <td>#${e.registro_id}</td>
            <td><span class="badge aud-${e.accion.toLowerCase()}">${e.accion}</span>${nota}</td>
            <td>${escapeHtml(e.ip || '—')}</td>
            <td><button type="button" class="btn-secondary-admin aud-ver" data-id="${e.id}">Ver</button></td>
        </tr>
        <tr class="aud-detalle" id="aud-det-${e.id}" hidden>
            <td colspan="8"><pre>${escapeHtml(json)}</pre></td>
        </tr>`;
    }

    function alternarDetalle(ev) {
        const btn = ev.target.closest('.aud-ver');
        if (!btn) return;
        const fila = document.getElementById(`aud-det-${btn.dataset.id}`);
        if (!fila) return;
        fila.hidden = !fila.hidden;
        btn.textContent = fila.hidden ? 'Ver' : 'Ocultar';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }
})();
