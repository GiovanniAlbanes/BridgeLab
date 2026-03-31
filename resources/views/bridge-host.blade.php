<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
<title>BridgeLab — Host</title>
<style>
  :root {
    --bg: #0b0f1a;
    --surface: #111827;
    --border: #1e293b;
    --text: #e2e8f0;
    --muted: #64748b;
    --online: #22c55e;
    --offline: #ef4444;
    --ch1: #3b82f6;
    --ch2: #22c55e;
    --ch3: #ef4444;
    --ch4: #f59e0b;
    --ch5: #a855f7;
    --ch6: #ec4899;
    --ch7: #06b6d4;
    --ch8: #84cc16;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: "Segoe UI", system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ── Header ──────────────────────────────────────── */
  header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    gap: 12px;
  }
  header h1 { font-size: 1.1rem; font-weight: 700; letter-spacing: .06em; flex-shrink: 0; }

  .pill {
    display: flex; align-items: center; gap: 7px;
    font-size: .75rem; font-weight: 600;
    padding: 4px 11px; border-radius: 999px;
    border: 1px solid var(--border); background: #0f172a;
    transition: color .3s, border-color .3s;
  }
  .pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--muted); transition: background .3s; }
  .pill.online  .dot  { background: var(--online); }
  .pill.online        { color: #bbf7d0; border-color: #166534; }
  .pill.offline .dot  { background: var(--offline); }
  .pill.offline       { color: #fecaca; border-color: #7f1d1d; }

  /* ── Phase banner ────────────────────────────────── */
  #phase-banner {
    text-align: center;
    font-size: .85rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
    padding: 8px 24px;
    border-bottom: 1px solid var(--border);
    transition: background .4s, color .4s, border-color .4s;
  }
  #phase-banner.idle   { background: #0f172a; color: var(--muted); border-color: var(--border); }
  #phase-banner.open   { background: #0c1e3a; color: #60a5fa; border-color: #1e3a6e;
                          animation: pulse-open 1s ease-in-out infinite alternate; }
  #phase-banner.locked { background: #1c0a0a; color: #f87171; border-color: #7f1d1d; }

  @keyframes pulse-open {
    from { background: #0c1e3a; }
    to   { background: #0f2d5a; }
  }

  /* ── Main layout ─────────────────────────────────── */
  main {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 360px;
    overflow: hidden;
  }

  /* ── Left column ─────────────────────────────────── */
  #left-col {
    display: flex; flex-direction: column; gap: 0;
    border-right: 1px solid var(--border);
    overflow-y: auto;
  }

  /* ── Teams grid ──────────────────────────────────── */
  #teams-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    border-bottom: 1px solid var(--border);
  }

  .team-card {
    padding: 16px 12px;
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    transition: background .3s;
    position: relative;
  }
  .team-card:last-child { border-right: none; }
  .team-card.winner-card { background: rgba(255,255,255,.04); }

  .team-ch {
    font-size: .65rem; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--muted);
  }
  .team-name-wrap { width: 100%; text-align: center; }
  .team-name {
    font-size: .9rem; font-weight: 700;
    background: transparent; border: none; outline: none;
    color: var(--text); text-align: center; width: 100%;
    border-bottom: 1px solid transparent;
    transition: border-color .2s;
    cursor: pointer;
  }
  .team-name:focus { border-bottom-color: var(--border); cursor: text; }
  .team-score {
    font-size: 1.8rem; font-weight: 900; line-height: 1;
    transition: color .3s;
  }
  .team-badge {
    font-size: .6rem; font-weight: 800; letter-spacing: .1em;
    padding: 2px 7px; border-radius: 999px; text-transform: uppercase;
    opacity: 0; transition: opacity .3s;
  }
  .team-card.winner-card .team-badge { opacity: 1; background: gold; color: #000; }
  .team-card.buzz-card .team-badge   { opacity: 1; background: #1e293b; color: var(--muted); }
  .team-card.early-card { background: rgba(251, 146, 60, .08); }
  .team-card.early-card .team-badge  { opacity: 1; background: #7c2d12; color: #fdba74; }
  .team-card.early-card .team-badge::before { content: 'ANTICIPO'; }

  /* ── Winner spotlight ────────────────────────────── */
  #winner-section {
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 8px; padding: 32px 24px;
    min-height: 200px;
  }
  #winner-label {
    font-size: .72rem; font-weight: 700; letter-spacing: .16em;
    text-transform: uppercase; color: var(--muted);
  }
  #winner-name  {
    font-size: 3.5rem; font-weight: 900; line-height: 1;
    color: var(--muted); transition: color .4s;
    text-align: center;
  }
  #winner-sub   { font-size: 1rem; font-weight: 600; color: var(--muted); transition: color .4s; }
  #winner-time  { font-size: .72rem; color: var(--muted); margin-top: 2px; }

  /* ── Right column ────────────────────────────────── */
  #right-col {
    display: flex; flex-direction: column; gap: 16px;
    padding: 20px 18px;
    overflow-y: auto;
  }

  .panel {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 16px;
  }
  .panel-title {
    font-size: .65rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--muted); margin-bottom: 12px;
  }

  /* ── Controls ────────────────────────────────────── */
  .btn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

  button {
    cursor: pointer; font-size: .8rem; font-weight: 700;
    padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);
    background: #0f172a; color: var(--text);
    transition: background .15s, border-color .15s, transform .1s;
  }
  button:hover   { background: #1e293b; }
  button:active  { transform: scale(.97); }
  button:disabled { opacity: .35; cursor: not-allowed; }

  .btn-open    { border-color: #1e3a6e; color: #93c5fd; }
  .btn-open:not(:disabled):hover   { background: #0c1e3a; }
  .btn-correct { border-color: #14532d; color: #86efac; }
  .btn-correct:not(:disabled):hover { background: #0a2318; }
  .btn-wrong   { border-color: #7f1d1d; color: #fca5a5; }
  .btn-wrong:not(:disabled):hover   { background: #1c0a0a; }
  .btn-danger  { border-color: #4b2020; color: #fca5a5; grid-column: span 2; }
  .btn-danger:hover  { background: #1c0a0a; }
  .btn-full    { grid-column: span 2; }
  .top-actions { display: flex; gap: 8px; align-items: center; }
  .btn-close-bridge {
    font-size: .75rem; padding: 5px 12px; border-radius: 8px;
    border-color: #4b2020; color: #fca5a5;
  }
  .btn-close-bridge:not(:disabled):hover { background: #1c0a0a; }

  /* ── Buzz list ───────────────────────────────────── */
  #buzz-list { display: flex; flex-direction: column; gap: 7px; }

  .buzz-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 11px; border-radius: 9px;
    background: #0f172a; border: 1px solid var(--border);
    animation: fadeIn .3s ease;
  }
  @keyframes fadeIn { from { opacity:0; transform:translateY(-5px); } to { opacity:1; transform:translateY(0); } }

  .buzz-order {
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 800; flex-shrink: 0;
    background: var(--border); color: var(--muted);
  }
  .buzz-row.first .buzz-order { background: gold; color: #000; }
  .buzz-row.early { border-color: #9a3412; background: rgba(124, 45, 18, .22); }
  .buzz-row.early .buzz-order { background: #ea580c; color: #fff7ed; }
  .buzz-info { flex: 1; min-width: 0; }
  .buzz-name { font-size: .85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .buzz-time { font-size: .67rem; color: var(--muted); }
  #empty-msg { color: var(--muted); font-size: .82rem; text-align: center; padding: 10px 0; }

  /* ── Channel colours ─────────────────────────────── */
  .ch-1 { color: var(--ch1); } .ch-2 { color: var(--ch2); }
  .ch-3 { color: var(--ch3); } .ch-4 { color: var(--ch4); }
  .ch-5 { color: var(--ch5); } .ch-6 { color: var(--ch6); }
  .ch-7 { color: var(--ch7); } .ch-8 { color: var(--ch8); }

  /* ── Countdown ───────────────────────────────────── */
  #phase-banner.countdown { background: #1a0e00; color: #fb923c; border-color: #92400e;
                             animation: pulse-countdown .5s ease-in-out infinite alternate; }
  @keyframes pulse-countdown {
    from { background: #1a0e00; }
    to   { background: #2d1600; }
  }
  .countdown-num {
    font-size: 7rem !important; font-weight: 900 !important; line-height: 1 !important;
    color: #fb923c !important;
    animation: countdown-pop .5s cubic-bezier(.36,.07,.19,.97) both;
  }
  @keyframes countdown-pop {
    0%   { transform: scale(1.4); opacity: 0; }
    60%  { transform: scale(.95); opacity: 1; }
    100% { transform: scale(1); }
  }

  @media (max-width: 800px) {
    main { grid-template-columns: 1fr; }
    #left-col { border-right: none; border-bottom: 1px solid var(--border); }
    #teams-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>
</head>
<body>

<header>
  <h1>⚡ BridgeLab</h1>
  <div style="flex:1"></div>
  <div class="pill offline" id="bridge-pill">
    <span class="dot"></span>
    <span id="bridge-text">Bridge offline</span>
  </div>
  <div class="top-actions">
    <button id="btn-launch" onclick="doLaunch()" style="font-size:.75rem;padding:5px 12px;border-radius:8px;">
      ▶ Avvia Bridge
    </button>
    <button id="btn-close-bridge" class="btn-close-bridge" onclick="doCloseBridge()">
      ✕ Chiudi Bridge
    </button>
  </div>
</header>

<div id="phase-banner" class="idle">In attesa</div>

<main>

  <!-- ── Left: teams + winner ─────────────────────── -->
  <div id="left-col">

    <div id="teams-grid">
      <!-- filled by JS -->
    </div>

    <section id="winner-section">
      <div id="winner-label">In attesa...</div>
      <div id="winner-name">–</div>
      <div id="winner-sub"></div>
      <div id="winner-time"></div>
    </section>

  </div>

  <!-- ── Right: controls + buzz order ─────────────── -->
  <section id="right-col">

    <div class="panel">
      <div class="panel-title">Controlli partita</div>
      <div class="btn-grid">
        <button class="btn-open btn-full" id="btn-open" onclick="doOpen()">
          ▶ Apri Buzzer
        </button>
        <button class="btn-correct" id="btn-correct" onclick="doCorrect()" disabled>
          ✓ Corretto
        </button>
        <button class="btn-wrong" id="btn-wrong" onclick="doWrong()" disabled>
          ✗ Sbagliato
        </button>
        <button onclick="doReset()" class="btn-danger">
          ↺ Reset Round
        </button>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Ordine di pressione</div>
      <div id="buzz-list">
        <div id="empty-msg">Nessuna pressione</div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Punteggi</div>
      <div class="btn-grid">
        <button onclick="doResetScores()" class="btn-danger">
          ↺ Azzera punteggi
        </button>
      </div>
    </div>

  </section>

</main>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const CH_COLORS = ['','#3b82f6','#22c55e','#ef4444','#f59e0b','#a855f7','#ec4899','#06b6d4','#84cc16'];
const PHASE_LABELS = { idle: 'In attesa', open: 'BUZZER APERTO', locked: 'BLOCCATO' };

let lastTeamsKey = '';
let lastBuzzKey  = '';
let teams = [];
let bridgeOnline = false;

// ── Countdown ─────────────────────────────────────────────────────────────────
let _cdInterval  = null;
let _cdEndMs     = 0;
let _cdLastSec   = -1; // per riapplicare l'animazione solo al cambio di secondo
let _cdStartedAt = null;

document.addEventListener('DOMContentLoaded', () => {

    fetch('/state')
        .then(r => r.json())
        .then(render);

    if (!window.Echo) {
        console.error('Echo NON caricato');
        return;
    }

    window.Echo.channel('bridge-state')
        .listen('.state.updated', (data) => {
            console.log('WS EVENT', data); // 👈 debug

            // 🔥 intercetta apertura buzzer
            if (data.state.phase === 'open' && !_cdInterval) {
                const openedMs = new Date(data.state.opened_at).getTime();
                startCountdown(openedMs);
            }

            render({
                bridge_online: data.bridge_online,
                state: data.state,
                teams: data.teams
            });
        });

    if (!window._pollStarted) {
        window._pollStarted = true;
        setInterval(async () => {
            try {
                const r = await fetch('/state');
                const data = await r.json();

                if (data.bridge_online !== bridgeOnline) {
                    render(data);
                }

            } catch(e) {}
        }, 3000); // 👈 anche 5s va benissimo
    }

});

function startCountdown(startMs) {
    if (!startMs) return;

    if (_cdStartedAt === startMs) return; // 👈 blocca restart

    _cdStartedAt = startMs;

    const end = startMs + 3000;

    if (_cdInterval) clearInterval(_cdInterval);

    _cdEndMs   = end;
    _cdLastSec = -1;

    _cdInterval = setInterval(tickCountdown, 80);
    tickCountdown();
}

function stopCountdown() {
    if (_cdInterval) {
        clearInterval(_cdInterval);
        _cdInterval = null;
    }
    _cdLastSec   = -1;
    _cdStartedAt = null; // 👈 fondamentale
}

function tickCountdown() {
  const remaining = _cdEndMs - Date.now();
  const banner    = document.getElementById('phase-banner');
  const wName     = document.getElementById('winner-name');
  const wLbl      = document.getElementById('winner-label');
  const wSub      = document.getElementById('winner-sub');
  const wTim      = document.getElementById('winner-time');

  if (remaining <= 0) {
    stopCountdown();
    banner.className   = 'open';
    banner.textContent = PHASE_LABELS['open'];
    wLbl.textContent   = '';
    wName.className    = '';
    wName.textContent  = 'Buzzer aperto';
    wName.style.color  = '#60a5fa';
    wSub.textContent   = '';
    wTim.textContent   = '';
    return;
  }

  const secs = Math.ceil(remaining / 1000);
  banner.className   = 'countdown';
  banner.textContent = `PREMI TRA ${secs}…`;

  if (secs !== _cdLastSec) {
    _cdLastSec = secs;
    wLbl.textContent  = 'Non premere ancora!';
    wName.className   = 'countdown-num';
    wName.textContent = secs;
    wName.style.color = '';
    wSub.textContent  = '';
    wTim.textContent  = '';
    // riapplica l'animazione CSS
    void wName.offsetWidth;
    wName.className = 'countdown-num';
  }
}

// ── Render ────────────────────────────────────────────────────────────────────
function render(data) {
  // Bridge pill
  const pill = document.getElementById('bridge-pill');
  bridgeOnline = !!data.bridge_online;
  document.getElementById('bridge-text').textContent = data.bridge_online ? 'Bridge online' : 'Bridge offline';
  pill.className = 'pill ' + (data.bridge_online ? 'online' : 'offline');
  document.getElementById('btn-launch').disabled = data.bridge_online;
  document.getElementById('btn-close-bridge').disabled = !data.bridge_online;

  const s = data.state;
  teams   = data.teams || [];

  // Phase banner (il countdown gestisce className/textContent da solo)
  if (!_cdInterval) {
    const banner = document.getElementById('phase-banner');
    banner.className   = s.phase;
    banner.textContent = PHASE_LABELS[s.phase] || s.phase;
  }

  // Buttons state
  const locked = s.phase === 'locked';
  document.getElementById('btn-open').disabled    = !bridgeOnline || s.phase === 'open';
  document.getElementById('btn-correct').disabled = !locked;
  document.getElementById('btn-wrong').disabled   = !locked;

  // Teams grid
  renderTeams(teams, s);

  // Winner spotlight
  renderWinner(s, teams);

  // Buzz list
  renderBuzzList(s.buzzes || [], s.early_buzzes || [], teams);
}

function renderTeams(teams, s) {
  const grid = document.getElementById('teams-grid');
  const key  = teams.map(t => `${t.channel}:${t.name}:${t.score}`).join('|')
             + s.phase
             + (s.winner?.channel ?? '')
             + '|buzz:' + (s.buzzes || []).map(b => `${b.channel}:${b.order}`).join(',')
             + '|early:' + (s.early_buzzes || []).map(b => `${b.channel}:${b.at}`).join(',');
  if (grid.dataset.key === key) return;
  grid.dataset.key = key;

  grid.innerHTML = teams.map(t => {
    const color    = CH_COLORS[t.channel] || '#e2e8f0';
    const isWinner = s.winner && s.winner.channel === t.channel;
    const isBuzz   = !isWinner && (s.buzzes || []).some(b => b.channel === t.channel);
    const isEarly  = !isWinner && !isBuzz && (s.early_buzzes || []).some(b => b.channel === t.channel);
    const badge    = isWinner ? '🏆 PRIMO' : (isBuzz ? `#${(s.buzzes || []).find(b => b.channel === t.channel)?.order}` : '');
    return `
    <div class="team-card ${isWinner ? 'winner-card' : (isBuzz ? 'buzz-card' : (isEarly ? 'early-card' : ''))}">
      <div class="team-ch ch-${t.channel}">CH ${t.channel}</div>
      <div class="team-name-wrap">
        <input class="team-name" type="text" value="${escHtml(t.name)}"
               data-ch="${t.channel}"
               onblur="saveTeamName(this)"
               onkeydown="if(event.key==='Enter')this.blur()">
      </div>
      <div class="team-score" style="color:${color}">${fmt(t.score)}</div>
      <div class="team-badge">${badge}</div>
    </div>`;
  }).join('');
}

function renderWinner(s, teams) {
  const wName = document.getElementById('winner-name');
  const wLbl  = document.getElementById('winner-label');
  const wSub  = document.getElementById('winner-sub');
  const wTim  = document.getElementById('winner-time');

  if (s.winner) {
    stopCountdown();
    const ch    = s.winner.channel;
    const team  = teams.find(t => t.channel === ch);
    const color = CH_COLORS[ch] || '#e2e8f0';
    wLbl.textContent  = '🏆 PRIMO';
    wName.className   = '';
    wName.textContent = team ? team.name : `Canale ${ch}`;
    wName.style.color = color;
    wSub.textContent  = `CH ${ch}`;
    wSub.style.color  = color;
    wTim.textContent  = fmtTime(s.winner.at);
  } else if (s.phase === 'open') {

      // 👇 countdown gestito SOLO via WebSocket
      if (_cdInterval) {
          return;
      }

      stopCountdown();

      wLbl.textContent  = '';
      wName.className   = '';
      wName.textContent = 'Buzzer aperto';
      wName.style.color = '#60a5fa';
      wSub.textContent  = '';
      wSub.style.color  = '';
      wTim.textContent  = '';
  } else {
    stopCountdown();
    wLbl.textContent  = 'In attesa...';
    wName.className   = '';
    wName.textContent = '–';
    wName.style.color = 'var(--muted)';
    wSub.textContent  = '';
    wSub.style.color  = '';
    wTim.textContent  = '';
  }
}

function renderBuzzList(buzzes, earlyBuzzes, teams) {
  const list = document.getElementById('buzz-list');
  const key  = 'buzz:' + buzzes.map(b => b.channel + ':' + b.order).join(',')
             + '|early:' + earlyBuzzes.map(b => b.channel + ':' + b.at).join(',');
  if (list.dataset.key === key) return;
  list.dataset.key = key;

  if (buzzes.length === 0 && earlyBuzzes.length === 0) {
    list.innerHTML = '<div id="empty-msg">Nessuna pressione</div>';
    return;
  }

  const validRows = buzzes.map(b => {
    const team  = teams.find(t => t.channel === b.channel);
    const name  = team ? team.name : `Canale ${b.channel}`;
    const color = CH_COLORS[b.channel] || '#e2e8f0';
    return `<div class="buzz-row${b.order === 1 ? ' first' : ''}">
      <div class="buzz-order">${b.order}</div>
      <div class="buzz-info">
        <div class="buzz-name" style="color:${color}">${escHtml(name)}</div>
        <div class="buzz-time">CH ${b.channel} · ${fmtTime(b.at)}</div>
      </div>
    </div>`;
  });

  const earlyRows = earlyBuzzes.map(b => {
    const team = teams.find(t => t.channel === b.channel);
    const name = team ? team.name : `Canale ${b.channel}`;
    return `<div class="buzz-row early">
      <div class="buzz-order">!</div>
      <div class="buzz-info">
        <div class="buzz-name" style="color:#fb923c">${escHtml(name)}</div>
        <div class="buzz-time">CH ${b.channel} Â· anticipo ${fmtTime(b.at)}</div>
      </div>
    </div>`;
  });

  list.innerHTML = [...validRows, ...earlyRows].join('');
}

// ── Team name editing ─────────────────────────────────────────────────────────
function saveTeamName(input) {
  const ch   = parseInt(input.dataset.ch);
  const name = input.value.trim() || `Squadra ${ch}`;
  input.value = name;

  const updated = teams.map(t => t.channel === ch ? { ...t, name } : t);
  post('/host/teams', { teams: updated });
}

// ── Actions ───────────────────────────────────────────────────────────────────
async function doLaunch() {
  const btn = document.getElementById('btn-launch');
  btn.disabled = true; btn.textContent = '…';
  try {
    const res  = await fetch('/bridge/launch', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf },
    });
    const data = await res.json();
    if (!data.ok) {
      alert('Errore avvio bridge:\n' + (data.error ?? 'risposta non valida'));
    }
  } catch(e) {
    alert('Errore di rete: ' + e.message);
  }
  setTimeout(() => { btn.disabled = false; btn.textContent = '▶ Avvia Bridge'; }, 3000);
}

async function doCloseBridge() {
  const btn = document.getElementById('btn-close-bridge');
  btn.disabled = true; btn.textContent = '…';
  try {
    await fetch('/bridge/close', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf },
    });
  } catch(e) {}
  setTimeout(() => { btn.textContent = '✕ Chiudi Bridge'}, 1500);
}

async function doOpen()        { if (!bridgeOnline) return; await post('/host/open'); }
async function doCorrect()     { await post('/host/correct'); }
async function doWrong()       { await post('/host/wrong'); }
async function doReset()       { await post('/host/reset'); }
async function doResetScores() { await post('/host/reset-scores'); }

async function post(url, body = {}) {
  try {
    await fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body:    JSON.stringify(body),
    });
  } catch (e) { console.error(e); }
}

// ── Util ──────────────────────────────────────────────────────────────────────
function fmt(n) {
  const v = parseFloat(n) || 0;
  return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(2));
}

function fmtTime(iso) {
  if (!iso) return '';
  try { return new Date(iso).toLocaleTimeString('it-IT'); }
  catch { return iso; }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

</script>
</body>
</html>
