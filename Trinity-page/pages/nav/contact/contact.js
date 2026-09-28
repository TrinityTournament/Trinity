// Esto lo habria puesto en el html directo pero quizá Joaquin me mata.

function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.contact-form').addEventListener('submit', onSubmitContacto);
});

async function onSubmitContacto(e) {
    e.preventDefault();

    const $ = (sel) => document.querySelector(sel);
    const msgEl = $('#ct-msg');
    const btn   = $('#ct-btn');

    const nombre   = $('#ct-nombre').value.trim();
    const contacto = $('#ct-contacto').value.trim();
    const asunto   = $('#ct-asunto').value.trim();
    const mensaje  = $('#ct-mensaje').value.trim();

    if (!nombre || !contacto || !asunto || !mensaje) {
        msgEl.textContent = 'Completá todos los campos antes de enviar.';
        msgEl.className = 'form-msg msg-error';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Enviando...';
    msgEl.textContent = '';
    msgEl.className = 'form-msg';

    try {
        const res  = await apiFetch(`${API_BASE_URL}/../app/contact/send.php`, {
            method: 'POST',
            body: JSON.stringify({ nombre, contacto, asunto, mensaje }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            msgEl.textContent = data.error || 'No se pudo enviar tu mensaje. Probá de nuevo.';
            msgEl.className = 'form-msg msg-error';
            btn.disabled = false;
            btn.textContent = 'Enviar mensaje';
            return;
        }

        msgEl.textContent = data.mensaje || '¡Gracias! Recibimos tu mensaje.';
        msgEl.className = 'form-msg msg-ok';
        document.querySelector('.contact-form').reset();
        btn.textContent = 'Mensaje enviado ✓';
    } catch (err) {
        console.error('[contact.js]', err);
        msgEl.textContent = 'No se pudo conectar con el servidor.';
        msgEl.className = 'form-msg msg-error';
        btn.disabled = false;
        btn.textContent = 'Enviar mensaje';
    }
}