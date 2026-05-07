(function () {
  const el = document.getElementById('livetiming-countdown');
  if (!el) return;

  const startTs = parseInt(el.dataset.start, 10) * 1000;
  const endTs   = parseInt(el.dataset.end, 10) * 1000;

  function formatDuration(ms) {
    if (ms <= 0) return '0s';
    const totalSeconds = Math.floor(ms / 1000);
    const days    = Math.floor(totalSeconds / 86400);
    const hours   = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const parts = [];
    if (days > 0)                       parts.push(days + 'j');
    if (hours > 0   || days > 0)        parts.push(hours + 'h');
    if (minutes > 0 || hours > 0 || days > 0) parts.push(minutes + 'min');
    parts.push(seconds + 's');
    return parts.join(' ');
  }

  function tick() {
    if (!startTs || !endTs) {
      el.textContent = 'Temps indisponible';
      return;
    }
    const now = Date.now();
    if (now < startTs) {
      el.textContent = 'Début dans ' + formatDuration(startTs - now);
    } else if (now <= endTs) {
      el.textContent = 'En cours – fin dans ' + formatDuration(endTs - now);
    } else {
      el.textContent = 'Session terminée';
    }
  }

  tick();
  setInterval(tick, 1000);
})();
