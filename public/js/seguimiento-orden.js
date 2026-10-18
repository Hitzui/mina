(() => {
    'use strict';
    let updating = false;
    const update = async () => {
        const summary = document.querySelector('[data-tracking-summary]');
        if (!summary || updating || document.hidden) return;
        updating = true;
        try {
            const response = await fetch(summary.dataset.trackingUrl, {
                headers: { Accept: 'application/json' }, cache: 'no-store',
                signal: AbortSignal.timeout(20000),
            });
            if (!response.ok) throw new Error('No se pudo actualizar');
            const data = await response.json();
            const doc = new DOMParser().parseFromString(data.html, 'text/html');
            const replacement = doc.querySelector('[data-tracking-summary]');
            if (!replacement) throw new Error('Respuesta incompleta');
            summary.replaceWith(replacement);
        } catch {
            const status = summary.querySelector('[data-tracking-status]');
            if (status && !status.dataset.stale) {
                status.dataset.stale = 'true';
                status.textContent += ' No se pudo renovar la consulta; se conserva el último resultado. Se reintentará automáticamente.';
            }
        } finally {
            updating = false;
        }
    };
    window.setInterval(update, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
})();
