'use strict';

/* ============================================================
   Dienstplan – PWA
   Daten liegen vollständig lokal (localStorage).
   ============================================================ */

const LS = {
  settings: 'dp_settings',
  types: 'dp_types',
  entries: 'dp_entries', // { "YYYY-MM-DD": typeId }
};

const DEFAULT_TYPES = [
  { id: 'fruh',  name: 'Frühdienst', code: 'F', start: '06:00', end: '14:00', breakMin: 30, color: '#22d3ee', off: false },
  { id: 'spat',  name: 'Spätdienst', code: 'S', start: '14:00', end: '22:00', breakMin: 30, color: '#f59e0b', off: false },
  { id: 'nacht', name: 'Nachtdienst', code: 'N', start: '22:00', end: '06:00', breakMin: 45, color: '#a78bfa', off: false },
  { id: 'frei',  name: 'Frei', code: 'X', start: '', end: '', breakMin: 0, color: '#475569', off: true },
];

const DEFAULT_SETTINGS = { hourlyWage: 0, weekStart: 1 };

/* ---------- Storage helpers ---------- */
const store = {
  get(key, fallback) {
    try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; }
    catch { return fallback; }
  },
  set(key, val) { localStorage.setItem(key, JSON.stringify(val)); },
};

let settings = store.get(LS.settings, { ...DEFAULT_SETTINGS });
let types    = store.get(LS.types, null);
if (!types) { types = DEFAULT_TYPES.map(t => ({ ...t })); store.set(LS.types, types); }
let entries  = store.get(LS.entries, {});

let viewYear, viewMonth; // aktuell angezeigter Monat
(() => { const n = new Date(); viewYear = n.getFullYear(); viewMonth = n.getMonth(); })();

/* ---------- Utils ---------- */
const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));
const pad = n => String(n).padStart(2, '0');
const dateKey = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
const MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

function typeById(id) { return types.find(t => t.id === id) || null; }

function shiftHours(t) {
  if (!t || t.off || !t.start || !t.end) return 0;
  const [sh, sm] = t.start.split(':').map(Number);
  const [eh, em] = t.end.split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins <= 0) mins += 24 * 60; // über Mitternacht
  mins -= (t.breakMin || 0);
  return Math.max(0, mins / 60);
}

function fmtH(h) { return h.toFixed(1).replace('.', ',') + ' h'; }
function fmtMoney(v) { return v.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; }

let toastTimer;
function toast(msg) {
  const el = $('#toast'); el.textContent = msg; el.classList.add('show');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => el.classList.remove('show'), 2200);
}

/* ---------- Sheets ---------- */
function openSheet(id) { $('#' + id).classList.add('open'); }
function closeSheet(id) { $('#' + id).classList.remove('open'); }
$$('.sheet-backdrop').forEach(bd => {
  bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
  bd.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => bd.classList.remove('open')));
});

/* ============================================================
   TABS
   ============================================================ */
$$('nav.tabs button').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('nav.tabs button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const tab = btn.dataset.tab;
    $('#view-cal').classList.toggle('hidden', tab !== 'cal');
    $('#view-types').classList.toggle('hidden', tab !== 'types');
    $('#view-settings').classList.toggle('hidden', tab !== 'settings');
    if (tab === 'types') renderTypes();
    if (tab === 'settings') fillSettings();
  });
});

/* ============================================================
   KALENDER
   ============================================================ */
function renderCalendar() {
  $('#monthTitle').textContent = `${MONTHS[viewMonth]} ${viewYear}`;
  const grid = $('#calGrid');
  grid.innerHTML = '';

  const first = new Date(viewYear, viewMonth, 1);
  const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
  // Wochentag des 1. (0=So..6=Sa) -> Offset je nach Wochenstart
  let startDow = first.getDay();
  const ws = settings.weekStart; // 1=Mo, 0=So
  let lead = ws === 1 ? (startDow === 0 ? 6 : startDow - 1) : startDow;

  // Wochentags-Header anpassen
  renderWeekdayHeader();

  for (let i = 0; i < lead; i++) {
    const c = document.createElement('div'); c.className = 'day empty'; grid.appendChild(c);
  }

  const today = new Date();
  for (let d = 1; d <= daysInMonth; d++) {
    const key = dateKey(viewYear, viewMonth, d);
    const dow = new Date(viewYear, viewMonth, d).getDay();
    const cell = document.createElement('div');
    cell.className = 'day';
    if (dow === 0 || dow === 6) cell.classList.add('weekend');
    if (today.getFullYear() === viewYear && today.getMonth() === viewMonth && today.getDate() === d)
      cell.classList.add('today');

    const num = document.createElement('div'); num.className = 'num'; num.textContent = d; cell.appendChild(num);

    const t = typeById(entries[key]);
    if (t) {
      const chip = document.createElement('div');
      chip.className = 'chip'; chip.style.background = t.color; chip.textContent = t.code || t.name;
      cell.appendChild(chip);
      const h = shiftHours(t);
      if (h > 0) { const hr = document.createElement('div'); hr.className = 'hrs'; hr.textContent = fmtH(h); cell.appendChild(hr); }
    }

    cell.addEventListener('click', () => openDaySheet(d, key));
    grid.appendChild(cell);
  }

  renderSummary();
}

function renderWeekdayHeader() {
  const wd = settings.weekStart === 1
    ? ['Mo','Di','Mi','Do','Fr','Sa','So']
    : ['So','Mo','Di','Mi','Do','Fr','Sa'];
  $('.weekdays').innerHTML = wd.map(d => `<div>${d}</div>`).join('');
}

function renderSummary() {
  let totalH = 0, count = 0;
  const prefix = `${viewYear}-${pad(viewMonth + 1)}-`;
  for (const key in entries) {
    if (!key.startsWith(prefix)) continue;
    const t = typeById(entries[key]);
    if (!t) continue;
    const h = shiftHours(t);
    if (h > 0) { totalH += h; count++; }
    else if (!t.off) count++;
  }
  const wage = Number(settings.hourlyWage) || 0;
  $('#sumHours').textContent = fmtH(totalH);
  $('#sumPay').textContent = fmtMoney(totalH * wage);
  $('#sumShifts').textContent = String(count);
  $('#sumWage').textContent = wage > 0 ? fmtMoney(wage) : '–';
}

$('#prevMonth').addEventListener('click', () => {
  viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; } renderCalendar();
});
$('#nextMonth').addEventListener('click', () => {
  viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; } renderCalendar();
});

/* ---------- Tag-Sheet ---------- */
let activeDayKey = null;
function openDaySheet(day, key) {
  activeDayKey = key;
  $('#daySheetTitle').textContent = `${day}. ${MONTHS[viewMonth]} ${viewYear}`;
  const wrap = $('#daySheetOptions');
  wrap.innerHTML = '';
  types.forEach(t => {
    const btn = document.createElement('button');
    btn.className = 'opt';
    const h = shiftHours(t);
    btn.innerHTML = `<span class="dot" style="background:${t.color}"></span>
      <span style="flex:1"><b>${t.name}</b> <span class="muted small">${t.code ? '(' + t.code + ')' : ''}</span><br>
      <span class="muted small">${t.off ? 'frei' : (t.start + '–' + t.end + ' · ' + fmtH(h))}</span></span>
      ${entries[key] === t.id ? '✓' : ''}`;
    btn.addEventListener('click', () => {
      entries[key] = t.id; store.set(LS.entries, entries);
      closeSheet('daySheet'); renderCalendar();
    });
    wrap.appendChild(btn);
  });
  openSheet('daySheet');
}
$('#btnClearDay').addEventListener('click', () => {
  if (activeDayKey) { delete entries[activeDayKey]; store.set(LS.entries, entries); }
  closeSheet('daySheet'); renderCalendar();
});

/* ============================================================
   DIENSTARTEN
   ============================================================ */
function renderTypes() {
  const list = $('#typeList'); list.innerHTML = '';
  types.forEach(t => {
    const row = document.createElement('div');
    row.className = 'stype';
    const h = shiftHours(t);
    row.innerHTML = `<span class="dot" style="background:${t.color}"></span>
      <span class="meta"><div class="nm">${t.name} ${t.code ? '<span class="muted small">(' + t.code + ')</span>' : ''}</div>
      <div class="sub">${t.off ? 'Freier Tag · 0 h' : `${t.start}–${t.end} · Pause ${t.breakMin}min · ${fmtH(h)}`}</div></span>
      <button class="btn ghost small">Bearb.</button>`;
    row.querySelector('button').addEventListener('click', () => openTypeSheet(t.id));
    list.appendChild(row);
  });
}

let editingTypeId = null;
function openTypeSheet(id) {
  editingTypeId = id;
  const t = id ? typeById(id) : { name: '', code: '', start: '08:00', end: '16:00', breakMin: 0, color: '#0ea5e9', off: false };
  $('#typeSheetTitle').textContent = id ? 'Dienstart bearbeiten' : 'Neue Dienstart';
  $('#tfName').value = t.name; $('#tfCode').value = t.code; $('#tfStart').value = t.start;
  $('#tfEnd').value = t.end; $('#tfBreak').value = t.breakMin; $('#tfColor').value = t.color || '#0ea5e9';
  $('#tfOff').checked = !!t.off;
  $('#btnDeleteType').style.display = id ? '' : 'none';
  openSheet('typeSheet');
}
$('#btnAddType').addEventListener('click', () => openTypeSheet(null));

$('#btnSaveType').addEventListener('click', () => {
  const name = $('#tfName').value.trim();
  if (!name) { toast('Bitte einen Namen eingeben'); return; }
  const data = {
    name,
    code: $('#tfCode').value.trim(),
    start: $('#tfStart').value,
    end: $('#tfEnd').value,
    breakMin: Number($('#tfBreak').value) || 0,
    color: $('#tfColor').value,
    off: $('#tfOff').checked,
  };
  if (editingTypeId) {
    Object.assign(typeById(editingTypeId), data);
  } else {
    data.id = 't_' + Date.now().toString(36);
    types.push(data);
  }
  store.set(LS.types, types);
  closeSheet('typeSheet'); renderTypes(); renderCalendar();
});

$('#btnDeleteType').addEventListener('click', () => {
  if (!editingTypeId) return;
  types = types.filter(t => t.id !== editingTypeId);
  store.set(LS.types, types);
  closeSheet('typeSheet'); renderTypes(); renderCalendar();
});

/* ============================================================
   EINSTELLUNGEN
   ============================================================ */
function fillSettings() {
  $('#setWage').value = settings.hourlyWage || '';
  $('#setWeekStart').value = String(settings.weekStart);
}
$('#btnSaveSettings').addEventListener('click', () => {
  settings.hourlyWage = Number($('#setWage').value) || 0;
  settings.weekStart = Number($('#setWeekStart').value);
  store.set(LS.settings, settings);
  toast('Gespeichert'); renderCalendar();
});

/* ---------- Export / Import ---------- */
$('#btnExport').addEventListener('click', () => {
  const data = JSON.stringify({ settings, types, entries }, null, 2);
  const blob = new Blob([data], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `dienstplan-export-${dateKey(new Date().getFullYear(), new Date().getMonth(), new Date().getDate())}.json`;
  a.click(); URL.revokeObjectURL(a.href);
});
$('#btnImport').addEventListener('click', () => $('#importFile').click());
$('#importFile').addEventListener('change', e => {
  const file = e.target.files[0]; if (!file) return;
  const r = new FileReader();
  r.onload = () => {
    try {
      const d = JSON.parse(r.result);
      if (d.settings) { settings = d.settings; store.set(LS.settings, settings); }
      if (d.types) { types = d.types; store.set(LS.types, types); }
      if (d.entries) { entries = d.entries; store.set(LS.entries, entries); }
      renderCalendar(); renderTypes(); fillSettings(); toast('Import erfolgreich');
    } catch { toast('Datei konnte nicht gelesen werden'); }
  };
  r.readAsText(file);
});

/* ============================================================
   ARCHIV
   ============================================================ */
$('#btnArchive').addEventListener('click', () => {
  const months = {};
  for (const key in entries) {
    const ym = key.slice(0, 7); // YYYY-MM
    const t = typeById(entries[key]);
    if (!t) continue;
    if (!months[ym]) months[ym] = { hours: 0, count: 0 };
    const h = shiftHours(t);
    if (h > 0) { months[ym].hours += h; months[ym].count++; }
    else if (!t.off) months[ym].count++;
  }
  const keys = Object.keys(months).sort().reverse();
  const list = $('#archiveList'); list.innerHTML = '';
  if (!keys.length) { list.innerHTML = '<p class="muted small">Noch keine Einträge.</p>'; }
  const wage = Number(settings.hourlyWage) || 0;
  keys.forEach(ym => {
    const [y, m] = ym.split('-').map(Number);
    const info = months[ym];
    const btn = document.createElement('button');
    btn.className = 'opt';
    btn.innerHTML = `<span style="flex:1"><b>${MONTHS[m - 1]} ${y}</b><br>
      <span class="muted small">${fmtH(info.hours)} · ${info.count} Dienste · ${fmtMoney(info.hours * wage)}</span></span> ›`;
    btn.addEventListener('click', () => {
      viewYear = y; viewMonth = m - 1; closeSheet('archiveSheet');
      $$('nav.tabs button')[0].click(); renderCalendar();
    });
    list.appendChild(btn);
  });
  openSheet('archiveSheet');
});

/* ============================================================
   FOTO / OCR
   ============================================================ */
$('#btnPhoto').addEventListener('click', () => { resetOcr(); openSheet('photoSheet'); });
$('#btnPickPhoto').addEventListener('click', () => $('#photoInput').click());

function resetOcr() {
  $('#ocrProgressWrap').classList.add('hidden');
  $('#ocrResult').classList.add('hidden');
  $('#ocrProgress').value = 0;
  $('#ocrAssignments').innerHTML = '';
}

let ocrCandidates = []; // [{day, typeId}]

$('#photoInput').addEventListener('change', async e => {
  const file = e.target.files[0]; if (!file) return;
  resetOcr();
  $('#ocrProgressWrap').classList.remove('hidden');
  $('#ocrStatus').textContent = 'Bild wird geladen…';

  try {
    const result = await Tesseract.recognize(file, 'deu+eng', {
      logger: m => {
        if (m.status === 'recognizing text') {
          $('#ocrProgress').value = Math.round(m.progress * 100);
          $('#ocrStatus').textContent = `Erkenne Text… ${Math.round(m.progress * 100)}%`;
        } else {
          $('#ocrStatus').textContent = m.status;
        }
      }
    });
    const text = result.data.text || '';
    $('#ocrRaw').textContent = text || '(kein Text erkannt)';
    parseSchedule(text);
  } catch (err) {
    $('#ocrStatus').textContent = 'Fehler bei der Erkennung: ' + err.message;
  }
});

/* Versucht aus dem OCR-Text Paare (Tag -> Dienstart-Kürzel) zu finden.
   Heuristik: pro Zeile eine Tageszahl (1-31) + ein bekanntes Kürzel.
   Das Ergebnis muss vom Nutzer kontrolliert werden. */
function parseSchedule(text) {
  const lines = text.split(/\n+/);
  const codeMap = {};
  types.forEach(t => { if (t.code) codeMap[t.code.toUpperCase()] = t.id; });

  const found = [];
  const seenDays = new Set();
  const codePattern = Object.keys(codeMap).map(c => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');

  lines.forEach(line => {
    const dayMatch = line.match(/\b([1-9]|[12]\d|3[01])\b/);
    if (!dayMatch) return;
    const day = Number(dayMatch[1]);
    if (day < 1 || day > 31 || seenDays.has(day)) return;
    if (!codePattern) return;
    const re = new RegExp('\\b(' + codePattern + ')\\b', 'i');
    const cm = line.match(re);
    if (cm) {
      const typeId = codeMap[cm[1].toUpperCase()];
      if (typeId) { found.push({ day, typeId }); seenDays.add(day); }
    }
  });

  ocrCandidates = found;
  renderOcrResult();
}

function renderOcrResult() {
  $('#ocrProgressWrap').classList.add('hidden');
  $('#ocrResult').classList.remove('hidden');
  $('#ocrCount').textContent = ocrCandidates.length;
  const wrap = $('#ocrAssignments');
  wrap.innerHTML = '';

  if (!ocrCandidates.length) {
    wrap.innerHTML = `<p class="muted small">Es konnten keine Dienste automatisch zugeordnet werden.
      Stelle sicher, dass deine Dienstarten passende Kürzel haben (Tab „Dienste"), oder trage die Tage manuell im Kalender ein.</p>`;
    $('#btnApplyOcr').classList.add('hidden');
    return;
  }
  $('#btnApplyOcr').classList.remove('hidden');

  ocrCandidates.forEach((c, idx) => {
    const row = document.createElement('div');
    row.className = 'stype';
    const opts = types.map(t => `<option value="${t.id}" ${t.id === c.typeId ? 'selected' : ''}>${t.name}</option>`).join('');
    row.innerHTML = `<span style="width:54px"><input type="number" value="${c.day}" min="1" max="31" style="padding:6px"></span>
      <select style="flex:1">${opts}</select>
      <button class="btn ghost small">✕</button>`;
    const dayInput = row.querySelector('input');
    const sel = row.querySelector('select');
    dayInput.addEventListener('change', () => c.day = Number(dayInput.value));
    sel.addEventListener('change', () => c.typeId = sel.value);
    row.querySelector('button').addEventListener('click', () => { ocrCandidates.splice(idx, 1); renderOcrResult(); });
    wrap.appendChild(row);
  });
}

$('#btnApplyOcr').addEventListener('click', () => {
  let n = 0;
  ocrCandidates.forEach(c => {
    if (c.day >= 1 && c.day <= 31 && typeById(c.typeId)) {
      entries[dateKey(viewYear, viewMonth, c.day)] = c.typeId; n++;
    }
  });
  store.set(LS.entries, entries);
  closeSheet('photoSheet'); renderCalendar();
  toast(`${n} Dienste in ${MONTHS[viewMonth]} übernommen`);
});

/* ============================================================
   INIT + Service Worker
   ============================================================ */
renderCalendar();

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  });
}
