const API_BASE = "https://api.openf1.org/v1";
const YEAR = 2025;

// SECTIONS
const homeSection = document.getElementById("home-section");
const calendarSection = document.getElementById("calendar-section");
const detailsSection = document.getElementById("details-section");
const liveSection = document.getElementById("live-section");

// NAVIGATION (header)
document.getElementById("nav-home").addEventListener("click", (e) => {
  e.preventDefault();
  showHome();
});

document.getElementById("nav-calendar").addEventListener("click", (e) => {
  e.preventDefault();
  showCalendar();
});

document.getElementById("nav-live").addEventListener("click", (e) => {
  e.preventDefault();
  showLive();
});

// BOUTONS ACCUEIL
document.getElementById("home-calendar-btn").addEventListener("click", () => {
  showCalendar();
});

document.getElementById("home-live-btn").addEventListener("click", () => {
  showLive();
});

// BOUTONS RETOUR
document
  .getElementById("back-to-home-from-calendar")
  .addEventListener("click", () => {
    showHome();
  });

document
  .getElementById("back-to-home-from-live")
  .addEventListener("click", () => {
    showHome();
  });

document.getElementById("back-to-calendar").addEventListener("click", () => {
  showCalendar();
});

// ZONES D'AFFICHAGE
const calendarListDiv = document.getElementById("calendar-list");
const meetingInfoDiv = document.getElementById("meeting-info");
const sessionsTableBody = document.querySelector("#sessions-table tbody");

// DONNÉES EN MÉMOIRE
let calendarLoaded = false;
let meetings = [];
let meetingsByKey = {};
let allSessions = [];
let weekendRanges = {};
let currentMeetingKey = null;

/* ------------------ NAVIGATION ENTRE PAGES ------------------ */

function hideAllSections() {
  homeSection.classList.add("hidden");
  calendarSection.classList.add("hidden");
  detailsSection.classList.add("hidden");
  liveSection.classList.add("hidden");
}

function showHome() {
  hideAllSections();
  homeSection.classList.remove("hidden");
}

function showCalendar() {
  hideAllSections();
  calendarSection.classList.remove("hidden");

  if (!calendarLoaded) {
    loadMeetingsAndSessions(YEAR);
  }
}

function showDetails(meetingKey) {
  hideAllSections();
  detailsSection.classList.remove("hidden");
  currentMeetingKey = meetingKey;
  renderMeetingDetails(meetingKey);
}

function showLive() {
  hideAllSections();
  liveSection.classList.remove("hidden");
}

/* ------------------ OUTILS DATE / TEMPS ------------------ */

// Parse "08:00:00" ou "-03:00:00" en nombre de minutes d'écart avec GMT
function parseGmtOffsetToMinutes(offset) {
  if (!offset) return 0;
  const sign = offset.startsWith("-") ? -1 : 1;
  const clean = offset.replace("+", "").replace("-", "");
  const parts = clean.split(":");
  const hours = parseInt(parts[0], 10) || 0;
  const minutes = parseInt(parts[1], 10) || 0;
  const seconds = parseInt(parts[2], 10) || 0;
  return sign * (hours * 60 + minutes + Math.round(seconds / 60));
}

// Ajoute un offset (en minutes) à une date UTC pour obtenir l'heure locale du circuit
function utcToLocalWithOffset(utcDate, offsetMinutes) {
  return new Date(utcDate.getTime() + offsetMinutes * 60000);
}

// Formate un intervalle de dates en français (ex : "1 janv. - 3 janv. 2025")
function formatWeekendRange(startDate, endDate) {
  if (!startDate || !endDate) return "";

  const sameMonth =
    startDate.getMonth() === endDate.getMonth() &&
    startDate.getFullYear() === endDate.getFullYear();

  if (sameMonth) {
    const startStr = startDate.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
    });
    const endStr = endDate.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
    return `${startStr} - ${endStr}`;
  } else {
    const startStr = startDate.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
    const endStr = endDate.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
    return `${startStr} - ${endStr}`;
  }
}

/* ------------------ CHARGEMENT DES DONNÉES ------------------ */

async function loadMeetingsAndSessions(year) {
  calendarLoaded = true;
  calendarListDiv.innerHTML = "<p>Chargement du calendrier 2025...</p>";

  try {
    const [meetingsRes, sessionsRes] = await Promise.all([
      fetch(`${API_BASE}/meetings?year=${year}`),
      fetch(`${API_BASE}/sessions?year=${year}`),
    ]);

    const meetingsData = await meetingsRes.json();
    const sessionsData = await sessionsRes.json();

    // Trie les meetings par date de début (UTC)
    meetings = meetingsData.sort(
      (a, b) => new Date(a.date_start) - new Date(b.date_start)
    );

    // Index par meeting_key
    meetingsByKey = {};
    meetings.forEach((m) => {
      meetingsByKey[m.meeting_key] = m;
    });

    // Stocke toutes les sessions
    allSessions = sessionsData;

    // Calcule les dates de week-end par meeting (en heure locale du circuit)
    weekendRanges = computeWeekendRanges(allSessions);

    // Affiche le calendrier
    renderCalendar();
  } catch (err) {
    console.error(err);
    calendarListDiv.innerHTML =
      "<p>Erreur lors du chargement du calendrier.</p>";
  }
}

// Calcule, pour chaque meeting, la date de début/fin du week-end en heure locale
function computeWeekendRanges(sessions) {
  const map = {};

  sessions.forEach((session) => {
    if (!session.meeting_key || !session.date_start || !session.date_end) {
      return;
    }

    const utcStart = new Date(session.date_start);
    const utcEnd = new Date(session.date_end);
    const offsetMinutes = parseGmtOffsetToMinutes(session.gmt_offset);

    const localStart = utcToLocalWithOffset(utcStart, offsetMinutes);
    const localEnd = utcToLocalWithOffset(utcEnd, offsetMinutes);

    const key = session.meeting_key;

    if (!map[key]) {
      map[key] = { start: localStart, end: localEnd };
    } else {
      if (localStart < map[key].start) map[key].start = localStart;
      if (localEnd > map[key].end) map[key].end = localEnd;
    }
  });

  return map;
}

/* ------------------ RENDU DU CALENDRIER ------------------ */

function renderCalendar() {
  if (!meetings.length) {
    calendarListDiv.innerHTML =
      "<p>Aucun meeting trouvé pour 2025 (vérifie plus tard).</p>";
    return;
  }

  calendarListDiv.innerHTML = "";

  meetings.forEach((meeting) => {
    const mk = meeting.meeting_key;
    const range = weekendRanges[mk];

    let startDateLocal;
    let endDateLocal;

    if (range) {
      startDateLocal = range.start;
      endDateLocal = range.end;
    } else {
      // Fallback : 3 jours à partir de date_start
      const utcStart = new Date(meeting.date_start);
      const offsetMinutes = parseGmtOffsetToMinutes(meeting.gmt_offset);
      startDateLocal = utcToLocalWithOffset(utcStart, offsetMinutes);
      endDateLocal = new Date(startDateLocal.getTime() + 2 * 24 * 60 * 60000);
    }

    const weekendText = formatWeekendRange(startDateLocal, endDateLocal);

    const circuitName =
      meeting.circuit_short_name || meeting.location || "Circuit inconnu";
    const countryName = meeting.country_name || "Pays inconnu";

    const card = document.createElement("button");
    card.className = "meeting-card";
    card.dataset.meetingKey = mk;

    card.innerHTML = `
      <div class="meeting-line"><strong>Circuit :</strong> ${circuitName}</div>
      <div class="meeting-line"><strong>Pays :</strong> ${countryName}</div>
      <div class="meeting-line"><strong>Week-end :</strong> ${weekendText}</div>
    `;

    card.addEventListener("click", () => {
      showDetails(mk);
    });

    calendarListDiv.appendChild(card);
  });
}

/* ------------------ PAGE DÉTAIL D'UN GP ------------------ */

function renderMeetingDetails(meetingKey) {
  const meeting = meetingsByKey[meetingKey];
  if (!meeting) {
    meetingInfoDiv.innerHTML = "<p>Meeting introuvable.</p>";
    sessionsTableBody.innerHTML = "";
    return;
  }

  const mk = meeting.meeting_key;
  const range = weekendRanges[mk];

  let startDateLocal;
  let endDateLocal;
  let weekendText = "";

  if (range) {
    startDateLocal = range.start;
    endDateLocal = range.end;
    weekendText = formatWeekendRange(startDateLocal, endDateLocal);
  }

  const location = meeting.location || "";
  const circuitName =
    meeting.circuit_short_name || meeting.meeting_name || "Circuit inconnu";
  const countryName = meeting.country_name || "";

  meetingInfoDiv.innerHTML = `
    <h2>${meeting.meeting_name}</h2>
    <p><strong>Circuit :</strong> ${circuitName}</p>
    <p><strong>Lieu :</strong> ${location} (${countryName})</p>
    ${
      weekendText
        ? `<p><strong>Week-end :</strong> ${weekendText}</p>`
        : ""
    }
  `;

  // Affiche les sessions pour ce meeting
  renderSessionsForMeeting(meetingKey);
}

function renderSessionsForMeeting(meetingKey) {
  sessionsTableBody.innerHTML = "<tr><td colspan='6'>Chargement...</td></tr>";

  // Filtre les sessions de ce meeting dans toutes celles de l'année
  const sessions = allSessions
    .filter((s) => s.meeting_key === meetingKey)
    .sort((a, b) => new Date(a.date_start) - new Date(b.date_start));

  if (!sessions.length) {
    sessionsTableBody.innerHTML =
      "<tr><td colspan='6'>Aucune session trouvée pour ce meeting.</td></tr>";
    return;
  }

  sessionsTableBody.innerHTML = "";

  sessions.forEach((session) => {
    const utcStart = new Date(session.date_start);
    const offsetMinutes = parseGmtOffsetToMinutes(session.gmt_offset);

    // Heure locale du circuit
    const localStart = utcToLocalWithOffset(utcStart, offsetMinutes);

    const localDayStr = localStart.toLocaleDateString("fr-FR", {
      weekday: "long",
      day: "2-digit",
      month: "long",
    });
    const localTimeStr = localStart.toLocaleTimeString("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
    });

    // Heure française (Europe/Paris)
    const frDayStr = utcStart.toLocaleDateString("fr-FR", {
      weekday: "long",
      day: "2-digit",
      month: "long",
      timeZone: "Europe/Paris",
    });
    const frTimeStr = utcStart.toLocaleTimeString("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
      timeZone: "Europe/Paris",
    });

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${session.session_name}</td>
      <td>${session.session_type}</td>
      <td>${localDayStr}</td>
      <td>${localTimeStr}</td>
      <td>${frDayStr}</td>
      <td>${frTimeStr}</td>
    `;
    sessionsTableBody.appendChild(tr);
  });
}

/* ------------------ DÉMARRAGE ------------------ */

// On commence par la page d'accueil
showHome();
