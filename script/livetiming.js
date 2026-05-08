// Polling live timing — met à jour météo, statut piste et tours toutes les 30s
// Chargé uniquement quand la session est live ou à venir (cf. livetiming.php)
(function () {
  const countdown   = document.getElementById('livetiming-countdown');
  const sessionKey  = countdown ? parseInt(countdown.dataset.sessionKey, 10) : 0;
  if (!sessionKey) return;

  const elLaps        = document.getElementById('lt-laps');
  const elTrackStatus = document.getElementById('lt-track-status');
  const elTrackTemp   = document.getElementById('lt-track-temp');
  const elAirTemp     = document.getElementById('lt-air-temp');
  const elHumidity    = document.getElementById('lt-humidity');
  const elWind        = document.getElementById('lt-wind');

  function fmt(val, unit) {
    return val !== null ? val + unit : 'N/A';
  }

  async function refresh() {
    try {
      const res = await fetch('api/livetiming_status.php?session_key=' + sessionKey);
      if (!res.ok) return;
      const json = await res.json();
      if (json.error || !json.data) return;

      const d = json.data;

      if (elTrackTemp)   elTrackTemp.textContent   = fmt(d.trackTemp, '°C');
      if (elAirTemp)     elAirTemp.textContent      = fmt(d.airTemp, '°C');
      if (elHumidity)    elHumidity.textContent     = fmt(d.humidity, '%');
      if (elWind)        elWind.textContent         = fmt(d.windSpeed, ' km/h');

      if (elTrackStatus) {
        elTrackStatus.textContent = d.trackStatus;
        elTrackStatus.className   = 'track-status-badge ' + d.trackStatusClass;
      }

      if (elLaps) {
        const current = d.currentLap ?? '—';
        const total   = d.totalLaps  ?? '—';
        elLaps.textContent = current + '/' + total;
      }
    } catch (_) {
      // Silencieux : on réessaie au prochain tick
    }
  }

  setInterval(refresh, 30_000);
})();
