const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

let miId = null;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    try {
        const res = await fetch(`${API_BASE_URL}/../app/auth/check-session.php`, { credentials: 'include' });
        if (res.ok) {
            const data = await res.json();
            miId = data.usuario?.id ?? null;
        }
    } catch { /* sin sesión, no pasa nada */ }

    $$('.filter-pill').forEach((pill) => {
        pill.addEventListener('click', () => {
            $$('.filter-pill').forEach((p) => p.classList.remove('active'));
            pill.classList.add('active');
            cargarRanking(pill.dataset.disciplina);
        });
    });

    cargarRanking('');
}

async function cargarRanking(disciplina) {
    const podium = $('#podium');
    const lista  = $('#rank-list');
    podium.innerHTML = '';
    lista.innerHTML  = '<p class="no-tournaments">Cargando ranking...</p>';

    try {
        const url = disciplina
            ? `${API_BASE_URL}/../app/rankings/get-ranking.php?disciplina=${encodeURIComponent(disciplina)}`
            : `${API_BASE_URL}/../app/rankings/get-ranking.php`;
        const res  = await apiFetch(url);
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            lista.innerHTML = `<p class="no-tournaments">${escapeHtml(data.error || 'No se pudo cargar el ranking.')}</p>`;
            return;
        }

        render(data.jugadores || []);
    } catch (err) {
        console.error('[ranking.js]', err);
        lista.innerHTML = '<p class="no-tournaments">No se pudo conectar con el servidor.</p>';
    }
}

function render(jugadores) {
    const podium = $('#podium');
    const lista  = $('#rank-list');

    if (jugadores.length === 0) {
        podium.innerHTML = '';
        lista.innerHTML = '<p class="no-tournaments">Todavía nadie ganó un torneo en esta disciplina.</p>';
        return;
    }

    const top3  = jugadores.slice(0, 3);
    const resto = jugadores.slice(3);

    // El podio se arma visualmente 2do-1ro-3ro (ver CSS .podium-spot--N).
    const orden = [top3[1], top3[0], top3[2]].filter(Boolean);
    const medallas = { 1: '🥇', 2: '🥈', 3: '🥉' };

    podium.innerHTML = orden.map((j) => `
        <article class="podium-spot podium-spot--${j.posicion}${j.id === miId ? ' is-you' : ''}">
            <span class="podium-medal">${medallas[j.posicion]}</span>
            ${avatarHtml(j, 'podium-avatar')}
            <p class="podium-name">${escapeHtml(j.nombre)}${j.id === miId ? ' <span class="podium-you-tag">Vos</span>' : ''}</p>
            <p class="podium-stat"><strong>${j.torneos_ganados}</strong> torneo${j.torneos_ganados === 1 ? '' : 's'} ganado${j.torneos_ganados === 1 ? '' : 's'}</p>
        </article>
    `).join('');

    if (resto.length === 0) {
        lista.innerHTML = '';
        return;
    }

    lista.innerHTML = resto.map((j) => `
        <article class="rank-row${j.id === miId ? ' is-you' : ''}">
            <span class="rank-pos">${j.posicion}</span>
            ${avatarHtml(j, 'rank-avatar')}
            <span class="rank-name">${escapeHtml(j.nombre)}${j.id === miId ? ' <span class="podium-you-tag">Vos</span>' : ''}</span>
            <div class="rank-stats"><span class="rank-stat-num">${j.torneos_ganados}</span><span class="rank-stat-label">ganados</span></div>
        </article>
    `).join('');
}

function avatarHtml(j, claseBase) {
    if (j.foto_url) {
        const src = `${API_BASE_URL}/../app/users/photo.php?id=${encodeURIComponent(j.foto_url)}`;
        return `<div class="${claseBase}"><img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>`;
    }
    return `<div class="${claseBase}">${(j.nombre || '?').trim().charAt(0).toUpperCase()}</div>`;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}
