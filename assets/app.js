// Platzhalter für spätere Features:
// - Filter
// - Toggle (Messe / Arena)
// - Event-Dichte
// - Sticky Header

document.addEventListener('click', (e) => {
  const target = e.target.closest('a');
  if (!target) return;

  let clicks = parseInt(localStorage.getItem('gr_clicks') || '0', 10);
  clicks++;
  localStorage.setItem('gr_clicks', clicks);
});

// ----------------------------------
// GastroRadar – Overlay Ad Logic
// ----------------------------------

const OVERLAY_EVERY = window.GASTRO_OVERLAY_EVERY || 5;

// Klick zählen (alle echten Navigationen)
document.addEventListener('click', (e) => {
  const link = e.target.closest('a');
  if (!link) return;

  let clicks = parseInt(localStorage.getItem('gr_clicks') || '0', 10);
  clicks++;
  localStorage.setItem('gr_clicks', clicks);
});

// Overlay beim Laden prüfen
document.addEventListener('DOMContentLoaded', () => {
  const clicks = parseInt(localStorage.getItem('gr_clicks') || '0', 10);

  if (clicks > 0 && clicks % OVERLAY_EVERY === 0) {
    const overlay = document.getElementById('ad-overlay');
    if (overlay) {
      overlay.classList.remove('hidden');
    }
  }

  // Schließen-Button
  const closeBtn = document.querySelector('#ad-overlay .close');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      document.getElementById('ad-overlay').classList.add('hidden');
    });
  }
});

//console.log('Eventkalender App geladen');