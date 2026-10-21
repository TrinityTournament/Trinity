// Detecta automáticamente la ruta base a partir de dónde se cargó este script,
// así no depende de cómo se llame la carpeta del proyecto (Trinity-page,
// Trinity1erEntrega, etc.). Si por algún motivo no se puede detectar, usa
// cadena vacía (equivalente a rutas relativas a la raíz del sitio).
const navBaseURI = (() => {
    const script = document.currentScript
        || document.querySelector('script[src*="components/nav.js"]');
    if (!script) return '';
    return script.src.replace(/\/components\/nav\.js.*$/, '');
})();

// ── ESTADO DE NOTIFICACIONES ──────────────
// Va arriba de todo a propósito: injectNav() corre al cargar el script y puede
// llamar a setNavLoggedIn() (sesión en caché) antes de que se evalúe el resto.
const NOTIF_POLL_MS = 60000;
const NOTIF_ICONOS  = { torneo: '🏆', equipo: '👥', seguidor: '👤' };
const notifState    = { activa: false, timer: null, items: [], noLeidas: 0 };

// ── INYECTAR NAV ──────────────────────────

(function injectNav() {
    const nav = document.createElement('nav');
    nav.className = 'site-nav';
    nav.innerHTML = `
        <a href="${navBaseURI}/index.html" class="nav-logo">TRINITY</a>

        <button class="hamburger" id="hamburger">
            <span></span><span></span><span></span>
        </button>

        <ul class="nav-links">
            <li><a href="${navBaseURI}/pages/nav/tournament/tournament.html">Torneos</a></li>
            <li><a href="${navBaseURI}/pages/nav/ranking/ranking.html">Rankings</a></li>
            <li><a href="${navBaseURI}/pages/nav/news/news.html">Noticias</a></li>
            <li><a href="${navBaseURI}/pages/nav/contact/contact.html">Contacto</a></li>
        </ul>

        <div class="nav-actions">

            <!-- BUSCADOR DE USUARIOS -->
            <div class="nav-search" id="nav-search">
                <div class="nav-search-input-wrap">
                    <svg class="nav-search-icon" viewBox="0 0 20 20" fill="none">
                        <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="text"
                        id="nav-search-input"
                        class="nav-search-input"
                        placeholder="Buscar usuarios..."
                        autocomplete="off"
                    >
                </div>
                <div class="nav-search-results" id="nav-search-results"></div>
            </div>

            <!-- GUEST -->
            <div id="nav-guest">
                <a href="${navBaseURI}/pages/login/login.html" class="btn-login">Iniciar sesión</a>
                <a href="${navBaseURI}/pages/login/login.html?m=register" class="btn-register">Registrarse</a>
            </div>

            <!-- CAMPANA DE NOTIFICACIONES (visible solo con sesión) -->
            <div class="notif-menu" id="nav-notif">
                <button type="button" class="notif-trigger" id="notif-trigger"
                        aria-label="Notificaciones" aria-haspopup="true" aria-expanded="false">
                    <svg class="notif-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
                    </svg>
                    <span class="notif-badge" id="notif-badge" style="display:none;">0</span>
                </button>
                <div class="notif-dropdown" id="notif-dropdown">
                    <div class="notif-dropdown-header">
                        <span>Notificaciones</span>
                        <button type="button" class="notif-mark-all" id="notif-mark-all">Marcar todas como leídas</button>
                    </div>
                    <div class="notif-list" id="notif-list"><p class="notif-empty">Cargando...</p></div>
                </div>
            </div>

            <!-- USER LOGUEADO -->
            <div class="user-menu" id="nav-user">
                <div class="user-trigger" id="user-trigger">
                    <div class="user-avatar" id="nav-avatar">?</div>
                    <span class="user-name" id="nav-username">Usuario</span>
                    <span class="user-chevron">▼</span>
                </div>
                <div class="user-dropdown" id="user-dropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-avatar" id="dropdown-avatar">?</div>
                        <span class="dropdown-username" id="dropdown-username">Usuario</span>
                    </div>
                    <a href="${navBaseURI}/pages/profile/acc/view.html" class="dropdown-item">&nbsp; Perfil</a>
                    <a href="${navBaseURI}/pages/teams/mis-equipos.html" class="dropdown-item">&nbsp; Equipos</a>
                    <a href="${navBaseURI}/pages/admin/index.html" class="dropdown-item dropdown-item--admin" id="nav-admin-link" style="display:none;">&nbsp; Admin</a>
                    <a href="${navBaseURI}/pages/profile/cfg/edit.html" class="dropdown-item">&nbsp; Configuración</a>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item danger" id="nav-logout-btn">↩&nbsp; Cerrar sesión</button>
                </div>
            </div>
        </div>

        <!-- MOBILE MENU -->
        <div class="mobile-menu" id="mobile-menu">
            <!-- Buscador de usuarios en móvil -->
            <div class="mobile-search">
                <div class="nav-search-input-wrap" style="width:100%;max-width:100%;">
                    <svg class="nav-search-icon" viewBox="0 0 20 20" fill="none">
                        <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="text"
                        id="mobile-search-input"
                        class="nav-search-input"
                        placeholder="Buscar usuarios..."
                        autocomplete="off"
                    >
                </div>
                <div class="nav-search-results" id="mobile-search-results" style="position:static;width:100%;margin-top:4px;"></div>
            </div>
            <a href="${navBaseURI}/pages/nav/tournament/tournament.html">Torneos</a>
            <a href="${navBaseURI}/pages/nav/ranking/ranking.html">Rankings</a>
            <a href="${navBaseURI}/pages/nav/news/news.html">Noticias</a>
            <a href="${navBaseURI}/pages/nav/contact/contact.html">Contacto</a>
            <!-- Solo visible cuando NO hay sesión -->
            <div class="mobile-auth" id="mobile-auth">
                <a href="${navBaseURI}/pages/login/login.html" class="btn-login">Iniciar sesión</a>
                <a href="${navBaseURI}/pages/login/login.html?m=register" class="btn-register">Registrarse</a>
            </div>
        </div>
    `;

    // Insertar al inicio del body, envuelto en <header>
    const header = document.createElement('header');
    header.appendChild(nav);
    document.body.insertBefore(header, document.body.firstChild);

    // Eventos
    document.getElementById('hamburger').addEventListener('click', toggleHamburger); // 🍔 can i have
    document.getElementById('user-trigger').addEventListener('click', toggleDropdown); // a cheeseburger plz
    document.getElementById('nav-logout-btn').addEventListener('click', logout);
    wireNotifications();

    // Cerrar dropdown al clickear fuera
    document.addEventListener('click', e => {
        const menu = document.getElementById('nav-user');
        if (menu && !menu.contains(e.target)) {
            document.getElementById('user-trigger')?.classList.remove('open');
            document.getElementById('user-dropdown')?.classList.remove('open');
        }
    });

    // Cerrar hamburger al clickear fuera
    document.addEventListener('click', e => {
        const ham  = document.getElementById('hamburger');
        const menu = document.getElementById('mobile-menu');
        if (ham && menu && !ham.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
        }
    });

    // Búsqueda de usuarios (desktop + mobile)
    wireSearch('nav-search-input', 'nav-search-results');
    wireSearch('mobile-search-input', 'mobile-search-results');

    // Pintar de inmediato con el último estado conocido (evita parpadeo
    // "Iniciar sesión" -> "Usuario" mientras responde el servidor)...
    const saved = sessionStorage.getItem('trinity_user');
    if (saved) {
        try { setNavLoggedIn(JSON.parse(saved)); } catch {}
    }

    refreshSessionFromServer();
})();

// ── SESIÓN ─

async function refreshSessionFromServer() {
    try {
        const res = await fetch(`${navBaseURI}/../app/auth/check-session.php`, {
            credentials: 'include',
        });

        if (res.status === 401) {
            sessionStorage.removeItem('trinity_user');
            setNavLoggedOut();
            return;
        }

        const data = await res.json();
        if (data.usuario) {
            sessionStorage.setItem('trinity_user', JSON.stringify(data.usuario));
            setNavLoggedIn(data.usuario);
        }
    } catch {
        // Sin conexión al backend: dejamos el estado optimista que ya
        // se pintó desde sessionStorage, sin romper la navegación.
    }
}

function setNavLoggedOut() {
    const guestEl    = document.getElementById('nav-guest');
    const userEl     = document.getElementById('nav-user');
    const mobileAuth = document.getElementById('mobile-auth');
    if (guestEl)    guestEl.style.display  = '';
    if (userEl)     userEl.classList.remove('active');
    if (mobileAuth) mobileAuth.style.display = '';
    notifSetActive(false);
}

// ── BUSCADOR DE USUARIOS ───────────────────

function wireSearch(inputId, resultsId) {
    const input   = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    if (!input || !results) return;

    let timer   = null;
    let lastReq = 0;

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);

        if (q.length < 2) {
            results.innerHTML = '';
            results.classList.remove('open');
            return;
        }

        timer = setTimeout(() => runSearch(q), 250); // debounce
    });

    document.addEventListener('click', e => {
        if (!results.contains(e.target) && e.target !== input) {
            results.classList.remove('open');
        }
    });

    async function runSearch(q) {
        const reqId = ++lastReq;
        try {
            const res  = await fetch(`${navBaseURI}/../app/users/search-users.php?q=${encodeURIComponent(q)}`, {
                credentials: 'include',
            });
            const data = await res.json();
            if (reqId !== lastReq) return; // llegó una búsqueda más nueva antes

            const usuarios = data.users || [];
            results.innerHTML = usuarios.length
                ? usuarios.map(u => `
                    <a class="search-result-item" href="${navBaseURI}/pages/profile/acc/view.html?id=${u.id}">
                        <span class="search-result-avatar">${
                            u.foto_url
                                ? `<img src="${fotoUrlSrc(u.foto_url)}" alt="">`
                                : (u.usuario || '?')[0].toUpperCase()
                        }</span>
                        <span>
                            <div class="search-result-name">${escapeHtml(u.nombre)}</div>
                            <div class="search-result-user">@${escapeHtml(u.usuario)}</div>
                        </span>
                    </a>`).join('')
                : `<p class="search-empty">Sin resultados para "<strong>${escapeHtml(q)}</strong>"</p>`;
            results.classList.add('open');
        } catch {
            // Búsqueda falló silenciosamente: no bloqueamos la navegación.
        }
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// usuarios.foto_url es un id (no un data URL usable directo) — la
// imagen real se pide acá, que internamente llama a la API del bot
// donde vive guardada (ver app/users/photo.php).
function fotoUrlSrc(fotoId) {
    return fotoId ? `${navBaseURI}/../app/users/photo.php?id=${encodeURIComponent(fotoId)}` : null;
}

// ── NOTIFICACIONES ─────────────────────────
// Backend: app/notifications/get-notifications.php y mark-notifications-read.php.
// La campana se muestra solo con sesión iniciada y consulta cada 60 s mientras la
// pestaña esté visible. Se usa fetch directo (como el resto de nav.js) para no
// depender de que components/api.js esté cargado en cada página.

function wireNotifications() {
    const trigger = document.getElementById('notif-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        notifToggle();
    });
    document.getElementById('notif-mark-all').addEventListener('click', notifMarkAll);
    document.getElementById('notif-list').addEventListener('click', notifOnItemClick);

    // Cerrar al clickear fuera o con Escape
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('nav-notif');
        if (menu && !menu.contains(e.target)) notifClose();
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') notifClose(); });

    // Al volver a la pestaña se refresca enseguida
    document.addEventListener('visibilitychange', () => {
        if (notifState.activa && document.visibilityState === 'visible') notifLoad();
    });
}

function notifSetActive(on) {
    const menu = document.getElementById('nav-notif');
    if (!menu) return;
    menu.classList.toggle('active', on);

    if (on && !notifState.activa) {
        notifState.activa = true;
        notifLoad();
        notifState.timer = setInterval(() => {
            if (document.visibilityState === 'visible') notifLoad();
        }, NOTIF_POLL_MS);
    } else if (!on && notifState.activa) {
        notifState.activa = false;
        clearInterval(notifState.timer);
        notifState.timer = null;
        notifClose();
    }
}

function notifToggle() {
    const dd = document.getElementById('notif-dropdown');
    const abrir = !dd.classList.contains('open');
    dd.classList.toggle('open', abrir);
    document.getElementById('notif-trigger').classList.toggle('open', abrir);
    document.getElementById('notif-trigger').setAttribute('aria-expanded', String(abrir));
    if (abrir) notifLoad();
}

function notifClose() {
    document.getElementById('notif-dropdown')?.classList.remove('open');
    const t = document.getElementById('notif-trigger');
    if (t) { t.classList.remove('open'); t.setAttribute('aria-expanded', 'false'); }
}

async function notifLoad() {
    try {
        const res = await fetch(`${navBaseURI}/../app/notifications/get-notifications.php?limit=15`, {
            credentials: 'include',
        });
        if (res.status === 401) { notifSetActive(false); return; }
        if (!res.ok) return;

        const data = await res.json();
        notifState.items    = data.notificaciones || [];
        notifState.noLeidas = data.no_leidas || 0;
        notifRender();
    } catch {
        // Sin conexión: se deja lo que ya estaba dibujado.
    }
}

function notifRender() {
    const badge = document.getElementById('notif-badge');
    const list  = document.getElementById('notif-list');
    if (!badge || !list) return;

    const n = notifState.noLeidas;
    badge.textContent   = n > 99 ? '99+' : String(n);
    badge.style.display = n > 0 ? '' : 'none';
    document.getElementById('notif-trigger')
        .setAttribute('aria-label', n > 0 ? `Notificaciones (${n} sin leer)` : 'Notificaciones');
    document.getElementById('notif-mark-all').style.display = n > 0 ? '' : 'none';

    if (!notifState.items.length) {
        list.innerHTML = '<p class="notif-empty">No tenés notificaciones.</p>';
        return;
    }

    list.innerHTML = notifState.items.map((it) => {
        const icono  = NOTIF_ICONOS[String(it.tipo || '').split('_')[0]] || '🔔';
        const link   = notifLinkSeguro(it.link);
        const tag    = link ? 'a' : 'div';
        const href   = link ? ` href="${escapeHtml(navBaseURI + link)}"` : '';
        const clases = 'notif-item' + (it.leido ? '' : ' notif-item--unread');
        return `
            <${tag} class="${clases}"${href} data-id="${it.id}" data-leido="${it.leido ? 1 : 0}">
                <span class="notif-icon">${icono}</span>
                <span class="notif-info">
                    <div class="notif-title">${escapeHtml(it.titulo)}</div>
                    <div class="notif-msg">${escapeHtml(it.mensaje)}</div>
                    <div class="notif-time">${notifTiempo(it.creado_en)}</div>
                </span>
                ${it.leido ? '' : '<span class="notif-dot"></span>'}
            </${tag}>`;
    }).join('');
}

// Solo se aceptan links internos del sitio ("/pages/..."): nada de http(s):// ni "//".
function notifLinkSeguro(link) {
    return typeof link === 'string' && link.startsWith('/') && !link.startsWith('//') ? link : null;
}

function notifTiempo(iso) {
    const t = new Date(iso).getTime();
    if (Number.isNaN(t)) return '';
    const min = Math.max(0, Math.round((Date.now() - t) / 60000));
    if (min < 1)  return 'ahora';
    if (min < 60) return `hace ${min} min`;
    const h = Math.round(min / 60);
    if (h < 24)   return `hace ${h} h`;
    const d = Math.round(h / 24);
    if (d < 7)    return `hace ${d} ${d === 1 ? 'día' : 'días'}`;
    return new Date(t).toLocaleDateString('es-UY', { day: 'numeric', month: 'short' });
}

async function notifOnItemClick(e) {
    const item = e.target.closest('.notif-item');
    if (!item) return;

    const link = item.tagName === 'A' ? item.getAttribute('href') : null;
    if (link) e.preventDefault();   // se navega recién después de marcarla como leída

    if (item.dataset.leido === '0') {
        await notifPost({ id: Number(item.dataset.id) });
    }
    if (link) {
        window.location.href = link;
    } else {
        notifLoad();
    }
}

async function notifMarkAll() {
    await notifPost({ all: true });
    notifLoad();
}

// El CSRF se pide justo antes de escribir (igual que logout()).
async function notifPost(body) {
    try {
        const tokenRes = await fetch(`${navBaseURI}/../app/auth/csrf-token.php`, { credentials: 'include' });
        const { csrf_token } = await tokenRes.json();
        await fetch(`${navBaseURI}/../app/notifications/mark-notifications-read.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf_token },
            body: JSON.stringify(body),
        });
    } catch {
        // Si falla, la notificación sigue "sin leer" y se reintenta al próximo click.
    }
}

// ── AUTH ─────────────

function setNavLoggedIn(u) {
    const guestEl    = document.getElementById('nav-guest');
    const userEl     = document.getElementById('nav-user');
    const mobileAuth = document.getElementById('mobile-auth');
    if (guestEl)    guestEl.style.display  = 'none';
    if (userEl)     userEl.classList.add('active');
    if (mobileAuth) mobileAuth.style.display = 'none';
    notifSetActive(true);

    const letra = (u.usuario?.[0] || u.nombre?.[0] || '?').toUpperCase();

    const setTxt = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
    setTxt('nav-username',      u.usuario || u.nombre);
    setTxt('dropdown-username', u.usuario || u.nombre);

    const navAvatar      = document.getElementById('nav-avatar');
    const dropdownAvatar = document.getElementById('dropdown-avatar');
    if (u.foto_url) {
        if (navAvatar)      navAvatar.innerHTML      = `<img src="${fotoUrlSrc(u.foto_url)}" alt="Foto">`;
        if (dropdownAvatar) dropdownAvatar.innerHTML = `<img src="${fotoUrlSrc(u.foto_url)}" alt="Foto">`;
    } else {
        if (navAvatar)      navAvatar.textContent      = letra;
        if (dropdownAvatar) dropdownAvatar.textContent = letra;
    }

    // Mostrar link de Admin solo si el usuario tiene rol admin
    const adminLink = document.getElementById('nav-admin-link');
    if (adminLink) adminLink.style.display = u.rol === 'admin' ? '' : 'none';
}

async function logout() {
    try {
        // Necesitamos el csrf_token vigente: se pide justo antes de
        // cerrar sesión para no depender de que components/api.js esté
        // cargado en esta página.
        const tokenRes = await fetch(`${navBaseURI}/../app/auth/csrf-token.php`, { credentials: 'include' });
        const { csrf_token } = await tokenRes.json();

        await fetch(`${navBaseURI}/../app/auth/logout.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'X-CSRF-Token': csrf_token || '' },
        });
    } catch {
        // Si el logout del servidor falla igual limpiamos el estado local.
    }

    sessionStorage.removeItem('trinity_user');
    window.location.href = `${navBaseURI}/index.html`;
}

function toggleDropdown() {
    document.getElementById('user-trigger').classList.toggle('open');
    document.getElementById('user-dropdown').classList.toggle('open');
}

function toggleHamburger() {
    document.getElementById('mobile-menu').classList.toggle('open');
}