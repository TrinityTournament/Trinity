const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

const CATEGORIA_INFO = {
    torneos:         { label: 'Torneo',         badge: 'badge-torneo' },
    actualizaciones: { label: 'Actualización',  badge: 'badge-actualizacion' },
    resultados:      { label: 'Resultados',     badge: 'badge-resultado' },
    comunidad:       { label: 'Comunidad',      badge: 'badge-comunidad' },
    anuncios:        { label: 'Anuncio',        badge: 'badge-anuncio' },
};

let noticias = [];

document.addEventListener('DOMContentLoaded', init);

async function init() {
    $$('#news-filters .filter-pill').forEach((pill) => {
        pill.addEventListener('click', () => {
            $$('#news-filters .filter-pill').forEach((p) => p.classList.remove('active'));
            pill.classList.add('active');
            cargar(pill.dataset.categoria);
        });
    });

    $('#news-modal-close').addEventListener('click', cerrarModal);
    $('#news-modal').addEventListener('click', (e) => {
        if (e.target.id === 'news-modal') cerrarModal();
    });

    cargar('');
}

async function cargar(categoria) {
    const grid = $('#news-grid');
    grid.innerHTML = '<p class="no-tournaments">Cargando noticias...</p>';
    $('#news-featured-section').hidden = true;

    try {
        const url = categoria
            ? `${API_BASE_URL}/../app/news/list.php?categoria=${encodeURIComponent(categoria)}`
            : `${API_BASE_URL}/../app/news/list.php`;
        const res  = await apiFetch(url);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            grid.innerHTML = `<p class="no-tournaments">${escapeHtml(data.error || 'No se pudieron cargar las noticias.')}</p>`;
            return;
        }

        noticias = data.noticias || [];
        render();
    } catch (err) {
        console.error('[news.js]', err);
        grid.innerHTML = '<p class="no-tournaments">No se pudo conectar con el servidor.</p>';
    }
}

function render() {
    const grid = $('#news-grid');

    if (noticias.length === 0) {
        grid.innerHTML = '<p class="no-tournaments">Todavía no hay noticias en esta categoría.</p>';
        return;
    }

    const [destacada, ...resto] = noticias;
    renderDestacada(destacada);

    if (resto.length === 0) {
        grid.innerHTML = '';
        return;
    }

    grid.innerHTML = resto.map((n) => {
        const info = CATEGORIA_INFO[n.categoria] || CATEGORIA_INFO.anuncios;
        return `
        <article class="entity-card news-card" data-id="${n.id}" style="cursor:pointer;">
            <div class="news-card-image">
                ${n.imagen_url
                    ? `<img src="${imgSrc(n.imagen_url)}" alt="">`
                    : `<div class="news-featured-placeholder">${emojiCategoria(n.categoria)}</div>`}
            </div>
            <div class="entity-body">
                <span class="badge ${info.badge}">${info.label}</span>
                <p class="entity-name news-card-title">${escapeHtml(n.titulo)}</p>
                <p class="entity-meta">📅 ${formatearFecha(n.publicado_en)}</p>
            </div>
        </article>`;
    }).join('');

    $$('#news-grid [data-id]').forEach((card) => {
        card.addEventListener('click', () => abrirModal(Number(card.dataset.id)));
    });
}

function renderDestacada(n) {
    const info = CATEGORIA_INFO[n.categoria] || CATEGORIA_INFO.anuncios;
    $('#news-featured').innerHTML = `
        <div class="news-featured-image">
            ${n.imagen_url
                ? `<img src="${imgSrc(n.imagen_url)}" alt="" style="width:100%;height:100%;object-fit:cover;">`
                : `<div class="news-featured-placeholder">${emojiCategoria(n.categoria)}</div>`}
        </div>
        <div class="news-featured-body">
            <span class="badge ${info.badge}">${info.label}</span>
            <h2 class="news-featured-title">${escapeHtml(n.titulo)}</h2>
            <p class="news-featured-text">${escapeHtml(n.resumen || resumirTexto(n.contenido))}</p>
            <button type="button" class="btn-primary" data-id="${n.id}">Leer más</button>
        </div>
    `;
    $('#news-featured [data-id]').addEventListener('click', () => abrirModal(n.id));
    $('#news-featured-section').hidden = false;
}

async function abrirModal(id) {
    const body = $('#news-modal-body');
    body.innerHTML = '<p>Cargando...</p>';
    $('#news-modal').hidden = false;

    // Ya la tenemos en memoria (viene del listado) — evitamos otro pedido.
    const n = noticias.find((x) => x.id === id);
    if (!n) return;

    const info = CATEGORIA_INFO[n.categoria] || CATEGORIA_INFO.anuncios;
    body.innerHTML = `
        <span class="badge ${info.badge}">${info.label}</span>
        <h2 class="news-featured-title" style="margin-top:.6rem;">${escapeHtml(n.titulo)}</h2>
        <p class="news-modal-meta">📅 ${formatearFecha(n.publicado_en)} · Por ${escapeHtml(n.autor_nombre)}</p>
        ${n.imagen_url ? `<img src="${imgSrc(n.imagen_url)}" alt="">` : ''}
        <p>${escapeHtml(n.contenido)}</p>
    `;
}

function cerrarModal() {
    $('#news-modal').hidden = true;
}

function imgSrc(imagenId) {
    return `${API_BASE_URL}/../app/users/photo.php?id=${encodeURIComponent(imagenId)}`;
}

function emojiCategoria(categoria) {
    return { torneos: '🏆', actualizaciones: '🛠️', resultados: '📊', comunidad: '🎮', anuncios: '📣' }[categoria] || '📰';
}

function resumirTexto(texto) {
    const limpio = (texto || '').trim();
    return limpio.length > 200 ? limpio.slice(0, 200) + '…' : limpio;
}

function formatearFecha(fechaSql) {
    if (!fechaSql) return 'Reciente';
    const d = new Date(fechaSql.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return fechaSql;
    return d.toLocaleDateString('es-UY', { day: 'numeric', month: 'long', year: 'numeric' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
