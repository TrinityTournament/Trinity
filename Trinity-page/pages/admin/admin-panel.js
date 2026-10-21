// ══════════════════════════════════════════════════════════
//  TRINITY — Panel admin: usuarios, solicitudes de organizador y torneos
//
//  Endpoints:
//    app/admin/usuarios.php                  GET lista · PATCH { id, rol }
//    app/admin/solicitudes.php               GET lista · POST { id, accion }
//    app/tournaments/get-all-tournament.php  GET lista de torneos
//  (Noticias y Auditoría tienen sus propios archivos: admin-noticias.js y
//   admin-auditoria.js.)
//
//  El servidor es el que protege los datos (cada endpoint exige rol admin);
//  el control de acá solo evita mostrarle el panel a quien no corresponde.
// ══════════════════════════════════════════════════════════
(() => {
    const $ = (sel) => document.querySelector(sel);

    const ROLES = { admin: 'Admin', organizador: 'Organizador', participante: 'Participante' };
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    const state = { yo: null, usuarios: [], solicitudes: [], torneos: [] };
    let toastTimer = null;

    // Oculto hasta confirmar que la sesión es de un admin (evita el "flash" del panel).
    document.body.style.visibility = 'hidden';
    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        if (!(await verificarAdmin())) return;
        document.body.style.visibility = '';

        wireNav();
        $('#usr-buscar').addEventListener('input', renderUsuarios);
        $('#usr-rol').addEventListener('change', renderUsuarios);
        $('#usr-body').addEventListener('change', onCambioRol);
        $('#sol-estado').addEventListener('change', renderSolicitudes);
        $('#sol-body').addEventListener('click', onResolverSolicitud);
        $('#tor-estado').addEventListener('change', renderTorneos);
        $('#tor-buscar').addEventListener('input', renderTorneos);

        await Promise.all([cargarUsuarios(), cargarSolicitudes(), cargarTorneos()]);
    }

    // ── SESIÓN ──────────────────────────────────────────────
    async function verificarAdmin() {
        try {
            const res = await fetch(`${API_BASE_URL}/../app/auth/check-session.php`, { credentials: 'include' });
            if (res.status === 401) return irA('pages/login/login.html');

            const data = await res.json();
            if (!data.usuario || data.usuario.rol !== 'admin') return irA('index.html');

            state.yo = data.usuario;
            $('#admin-name').textContent   = data.usuario.nombre || data.usuario.usuario;
            $('#admin-avatar').textContent = (data.usuario.nombre || data.usuario.usuario || 'A')[0].toUpperCase();
            return true;
        } catch {
            // Sin conexión con el backend: se deja ver la página y cada tabla
            // muestra su propio error de conexión.
            document.body.style.visibility = '';
            toast('No se pudo verificar la sesión.', true);
            state.yo = { id: 0 };
            return true;
        }
    }

    function irA(ruta) {
        window.alert = () => {};   // evita los avisos de los otros scripts mientras redirige
        window.location.href = `${API_BASE_URL}/${ruta}`;
        return false;
    }

    // ── NAVEGACIÓN LATERAL ──────────────────────────────────
    function wireNav() {
        const links = [...document.querySelectorAll('.sidebar-nav .nav-item')];
        const activar = (hash) => links.forEach((a) => a.classList.toggle('active', a.getAttribute('href') === hash));

        links.forEach((a) => a.addEventListener('click', () => activar(a.getAttribute('href'))));

        if ('IntersectionObserver' in window) {
            // Resalta la sección que cruza una franja a ~30 % desde arriba de la pantalla.
            const obs = new IntersectionObserver((entries) => {
                const e = entries.find((x) => x.isIntersecting);
                if (e) activar(`#${e.target.id}`);
            }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });
            document.querySelectorAll('.main .section').forEach((s) => obs.observe(s));
        }
    }

    // ── USUARIOS ────────────────────────────────────────────
    async function cargarUsuarios() {
        const body = $('#usr-body');
        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/admin/usuarios.php`);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                body.innerHTML = filaMsg(5, data.error || 'No se pudieron cargar los usuarios.');
                $('#usr-sub').textContent = '—';
                return;
            }
            state.usuarios = data.usuarios || [];
            renderUsuarios();
        } catch {
            body.innerHTML = filaMsg(5, 'Error de conexión.');
        }
    }

    function renderUsuarios() {
        const q   = norm($('#usr-buscar').value);
        const rol = $('#usr-rol').value;
        const lista = state.usuarios.filter((u) =>
            (!rol || u.rol === rol) &&
            (!q || norm(`${u.nombre} ${u.usuario} ${u.email || ''}`).includes(q)));

        const total = state.usuarios.length;
        $('#usr-sub').textContent = `${total} usuario${total === 1 ? '' : 's'} registrado${total === 1 ? '' : 's'}`
            + (lista.length !== total ? ` · mostrando ${lista.length}` : '');

        $('#usr-body').innerHTML = lista.length
            ? lista.map(filaUsuario).join('')
            : filaMsg(5, 'Ningún usuario coincide con la búsqueda.');
    }

    function filaUsuario(u) {
        const esYo = state.yo && u.id === state.yo.id;
        const opciones = Object.entries(ROLES)
            .map(([v, l]) => `<option value="${v}"${u.rol === v ? ' selected' : ''}>${l}</option>`).join('');

        return `
        <tr data-id="${u.id}">
            <td><div class="user-cell">
                <div class="user-cell-avatar">${esc((u.nombre || u.usuario || '?')[0].toUpperCase())}</div>
                <div><p class="user-cell-name">${esc(u.nombre)}</p><p class="user-cell-user">@${esc(u.usuario)}</p></div>
            </div></td>
            <td>${esc(u.email || '—')}</td>
            <td><select class="rol-select" data-id="${u.id}" data-actual="${esc(u.rol)}"
                        aria-label="Rol de @${esc(u.usuario)}"${attrDeshabilitado(esYo)}>${opciones}</select></td>
            <td>${Number(u.torneos_jugados) || 0}</td>
            <td>${fmtFecha(u.creado_en)}</td>
        </tr>`;
    }
    const attrDeshabilitado = (esYo) => esYo ? ' disabled title="No podés cambiar tu propio rol"' : '';

    async function onCambioRol(e) {
        const sel = e.target.closest('.rol-select');
        if (!sel) return;

        const id     = Number(sel.dataset.id);
        const previo = sel.dataset.actual;
        const nuevo  = sel.value;
        const u      = state.usuarios.find((x) => x.id === id);
        if (!u || nuevo === previo) return;

        // Cambios delicados: se piden confirmados.
        if (nuevo === 'admin' && !window.confirm(`¿Dar permisos de administrador a @${u.usuario}?`)) { sel.value = previo; return; }
        if (previo === 'admin' && !window.confirm(`¿Quitarle el rol de administrador a @${u.usuario}?`)) { sel.value = previo; return; }

        sel.classList.add('saving');
        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/admin/usuarios.php`, {
                method: 'PATCH',
                body: JSON.stringify({ id, rol: nuevo }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                sel.value = previo;
                toast(data.error || 'No se pudo cambiar el rol.', true);
            } else {
                u.rol = nuevo;
                sel.dataset.actual = nuevo;
                toast(`@${u.usuario} ahora es ${ROLES[nuevo].toLowerCase()}.`);
            }
        } catch {
            sel.value = previo;
            toast('No se pudo cambiar el rol.', true);
        } finally {
            sel.classList.remove('saving');
        }
    }

    // ── SOLICITUDES DE ORGANIZADOR ──────────────────────────
    // Es la misma aprobación que el bot hace con /aprobar N y /rechazar N por WhatsApp.
    async function cargarSolicitudes() {
        const body = $('#sol-body');
        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/admin/solicitudes.php`);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                body.innerHTML = filaMsg(5, data.error || 'No se pudieron cargar las solicitudes.');
                $('#sol-sub').textContent = '—';
                return;
            }
            state.solicitudes = data.solicitudes || [];
            renderSolicitudes();
        } catch {
            body.innerHTML = filaMsg(5, 'Error de conexión.');
        }
    }

    function renderSolicitudes() {
        const estado = $('#sol-estado').value;
        const lista  = state.solicitudes.filter((s) => !estado || s.estado === estado);
        const pend   = state.solicitudes.filter((s) => s.estado === 'pendiente').length;

        $('#sol-sub').textContent = pend
            ? `${pend} pendiente${pend === 1 ? '' : 's'} de resolver · ${state.solicitudes.length} en total`
            : `Sin pendientes · ${state.solicitudes.length} en total`;

        const badge = $('#nav-solicitudes-badge');
        badge.textContent   = String(pend);
        badge.style.display = pend ? '' : 'none';

        $('#sol-body').innerHTML = lista.length
            ? lista.map(filaSolicitud).join('')
            : filaMsg(5, estado === 'pendiente' ? 'No hay solicitudes pendientes.' : 'No hay solicitudes con ese estado.');
    }

    function filaSolicitud(s) {
        const pendiente = s.estado === 'pendiente';
        const quien = s.resuelto_por_usuario ? `<small class="aud-cascada"> por @${esc(s.resuelto_por_usuario)}</small>` : '';
        return `
        <tr>
            <td><div class="user-cell">
                <div class="user-cell-avatar">${esc((s.nombre || s.usuario || '?')[0].toUpperCase())}</div>
                <div><p class="user-cell-name">${esc(s.nombre)}</p><p class="user-cell-user">@${esc(s.usuario)}</p></div>
            </div></td>
            <td>${Number(s.torneos_jugados) || 0}</td>
            <td>${fmtFecha(s.creado_en)}</td>
            <td><span class="badge badge-${esc(s.estado)}">${esc(s.estado)}</span>${quien}</td>
            <td>${pendiente
                ? `<div class="acciones-celda">
                       <button type="button" class="btn-approve" data-id="${s.id}" data-accion="aprobar">Aprobar</button>
                       <button type="button" class="btn-reject"  data-id="${s.id}" data-accion="rechazar">Rechazar</button>
                   </div>`
                : '—'}</td>
        </tr>`;
    }

    async function onResolverSolicitud(e) {
        const btn = e.target.closest('[data-accion]');
        if (!btn) return;

        const fila = btn.closest('tr');
        fila.querySelectorAll('button').forEach((b) => { b.disabled = true; });

        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/admin/solicitudes.php`, {
                method: 'POST',
                body: JSON.stringify({ id: Number(btn.dataset.id), accion: btn.dataset.accion }),
            });
            const data = await res.json().catch(() => ({}));
            toast(data.mensaje || data.error || (res.ok ? 'Listo.' : 'No se pudo resolver la solicitud.'), !res.ok);

            // Se recarga siempre: si ya la había resuelto otro admin (web o WhatsApp) la lista se corrige sola.
            await cargarSolicitudes();
            if (res.ok && btn.dataset.accion === 'aprobar') await cargarUsuarios();   // cambió un rol
        } catch {
            toast('No se pudo resolver la solicitud.', true);
            fila.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        }
    }

    // ── TORNEOS ─────────────────────────────────────────────
    async function cargarTorneos() {
        const body = $('#tor-body');
        try {
            const res  = await apiFetch(`${API_BASE_URL}/../app/tournaments/get-all-tournament.php`);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                body.innerHTML = filaMsg(6, data.error || 'No se pudieron cargar los torneos.');
                $('#tor-sub').textContent = '—';
                return;
            }
            state.torneos = data.torneos || [];
            renderTorneos();
        } catch {
            body.innerHTML = filaMsg(6, 'Error de conexión.');
        }
    }

    function renderTorneos() {
        const estado = $('#tor-estado').value;
        const q      = norm($('#tor-buscar').value);
        const lista  = state.torneos.filter((t) =>
            (!estado || t.estado === estado) &&
            (!q || norm(`${t.titulo} ${t.organizador_nombre} ${t.organizador_usuario}`).includes(q)));

        const total = state.torneos.length;
        $('#tor-sub').textContent = `${total} torneo${total === 1 ? '' : 's'} creado${total === 1 ? '' : 's'}`
            + (lista.length !== total ? ` · mostrando ${lista.length}` : '');

        $('#tor-body').innerHTML = lista.length
            ? lista.map(filaTorneo).join('')
            : filaMsg(6, 'Ningún torneo coincide con los filtros.');
    }

    function filaTorneo(t) {
        return `
        <tr>
            <td><a class="admin-link" href="../nav/tournament/detalle.html?id=${t.id}">${esc(t.titulo)}</a></td>
            <td>${esc(t.deporte)}</td>
            <td><span class="badge badge-${esc(t.estado)}">${esc(t.estado_label || t.estado)}</span></td>
            <td>${esc(t.organizador_nombre)} <small class="user-cell-user">@${esc(t.organizador_usuario)}</small></td>
            <td>${fmtFecha(t.fecha_inicio)}</td>
            <td>${Number(t.inscritos) || 0} / ${Number(t.max_participantes) || 0}</td>
        </tr>`;
    }

    // ── UTILIDADES ──────────────────────────────────────────
    function filaMsg(cols, texto) {
        return `<tr><td colspan="${cols}" class="table-loading">${esc(texto)}</td></tr>`;
    }

    function toast(texto, error = false) {
        const el = $('#admin-toast');
        el.textContent = texto;
        el.classList.toggle('err', !!error);
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 4000);
    }

    // "2026-05-12 14:30:00" o "2026-05-12" → "12 may 2026" (sin convertir zona horaria).
    function fmtFecha(s) {
        const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${Number(m[3])} ${MESES[Number(m[2]) - 1]} ${m[1]}` : '—';
    }

    // Búsqueda sin distinguir mayúsculas ni tildes.
    function norm(str) {
        return String(str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML.replace(/"/g, '&quot;');
    }
})();
