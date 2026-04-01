<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@vite(['resources/js/app.js'])
<title>BridgeLab — Proiezione</title>
<style>
  /* ══════════════════════════════════════════════════════
     COLORE CARD VINCITORE — modifica qui
  ══════════════════════════════════════════════════════ */
  :root {
    --winner-bg:      #f59e0b;   /* sfondo card */
    --winner-text:    #0b0f1a;   /* testo sulla card */
    --winner-accent:  #fef3c7;   /* label "PRIMO!" */
  }

  * { box-sizing:border-box; margin:0; padding:0; }

  body {
    font-family:"Segoe UI",system-ui,sans-serif;
    background:#0b0f1a; color:#e2e8f0;
    height:100vh; overflow:hidden;
    display:flex; flex-direction:column;
  }

  /* ── Waiting ─────────────────────────────────────── */
  #waiting {
    flex:1; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:16px;
  }
  #waiting .logo { font-size:3rem; font-weight:900; letter-spacing:.12em; color:#1e293b; }
  #waiting .sub  { font-size:1rem; color:#334155; }

  /* ── Question view ───────────────────────────────── */
  #question-view { display:none; flex:1; flex-direction:column; height:100vh; position:relative; }
  #question-view.active { display:flex; }

  /* ── Layout: NO media ────────────────────────────── */
  .layout-text {
    flex:1; display:flex; flex-direction:column;
    padding:52px 80px; gap:32px;
  }
  .layout-text #q-text {
    font-size:2.8rem; font-weight:800; line-height:1.25;
    color:#f8fafc; border-bottom:2px solid #1e293b; padding-bottom:28px;
  }
  .layout-text #answers-wrap {
    flex:1; display:flex; flex-direction:column; gap:14px; justify-content:center;
  }
  .layout-text .answer-card {
    display:flex; align-items:center; gap:20px;
    background:#111827; border:1px solid #1e293b;
    border-radius:14px; padding:20px 28px;
    flex:1; max-height:120px;
  }

  /* ── Layout: WITH media ──────────────────────────── */
  .layout-media {
    flex:1; display:grid;
    grid-template-columns: 1fr 380px;
    height:100vh;
  }
  .layout-media .left {
    display:flex; flex-direction:column;
    padding:48px 40px 40px 64px; gap:24px;
    border-right:1px solid #1e293b; overflow:hidden;
  }
  .layout-media #q-text {
    font-size:2rem; font-weight:800; line-height:1.25;
    color:#f8fafc; flex-shrink:0;
  }
  .layout-media #q-media {
    flex:1; min-height:0; border-radius:16px; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
    background:#0f172a;
  }
  .layout-media #q-media img  { width:100%; height:100%; object-fit:contain; border-radius:16px; display:block; }
  .layout-media #q-media video { width:100%; height:100%; object-fit:contain; border-radius:16px; display:block; }
  .layout-media .right {
    display:flex; flex-direction:column;
    padding:48px 40px 40px 36px; gap:14px; justify-content:center;
    overflow-y:auto;
  }
  .layout-media .answer-card {
    display:flex; align-items:center; gap:16px;
    background:#111827; border:1px solid #1e293b;
    border-radius:12px; padding:16px 20px;
  }

  /* ── Shared answer styles ────────────────────────── */
  .answer-num {
    width:36px; height:36px; border-radius:50%; flex-shrink:0;
    background:#1e293b; display:flex; align-items:center; justify-content:center;
    font-size:.85rem; font-weight:800; color:#94a3b8;
  }
  .layout-text .answer-num { width:44px; height:44px; font-size:1rem; }
  .answer-text { font-size:1.05rem; font-weight:600; line-height:1.3; color:#e2e8f0; }
  .layout-text .answer-text { font-size:1.3rem; }

  /* ── Countdown (alto destra) ─────────────────────── */
  #countdown-box {
    display:none;
    position:fixed; top:28px; right:36px; z-index:200;
    flex-direction:column; align-items:center;
    animation:cdFadeIn .25s ease;
  }
  #countdown-box.show { display:flex; }
  @keyframes cdFadeIn { from { opacity:0; transform:scale(.7); } to { opacity:1; transform:scale(1); } }
  #countdown-num {
    font-size:7rem; font-weight:900; line-height:1;
    color:#fb923c;
    text-shadow: 0 0 60px rgba(251,146,60,.6);
    animation:cdPop .45s cubic-bezier(.36,.07,.19,.97) both;
  }
  @keyframes cdPop {
    0%   { transform:scale(1.5); opacity:0; }
    60%  { transform:scale(.92); opacity:1; }
    100% { transform:scale(1); }
  }
  #countdown-label {
    font-size:.7rem; font-weight:800; letter-spacing:.18em;
    text-transform:uppercase; color:#fb923c; opacity:.7;
    margin-top:4px;
  }

  /* ── Answer highlight ────────────────────────────── */
  .answer-card.ans-correct { border-color:#16a34a !important; background:#052e16 !important; }
  .answer-card.ans-wrong   { border-color:#dc2626 !important; background:#1c0505 !important; }

  /* ── Winner bar (basso, non invadente) ───────────── */
  #winner-overlay {
    display:none;
    position:fixed; bottom:0; left:0; right:0; z-index:100;
    background:var(--winner-bg);
    padding:18px 60px;
    align-items:center; gap:24px;
    box-shadow:0 -4px 40px rgba(0,0,0,.5);
    animation:slideUp .3s ease;
  }
  #winner-overlay.show { display:flex; }

  /* Layout con media: card piccola in basso a destra sotto le risposte */
  #winner-overlay.has-media {
    left:auto; right:20px; bottom:24px;
    width:340px; border-radius:14px;
    padding:14px 20px;
    box-shadow:0 8px 32px rgba(0,0,0,.5);
  }
  #winner-overlay.has-media .winner-name { font-size:1.4rem; }

  @keyframes slideUp {
    from { transform:translateY(100%); opacity:0; }
    to   { transform:translateY(0);    opacity:1; }
  }

  .winner-label {
    font-size:.75rem; font-weight:900; letter-spacing:.22em;
    text-transform:uppercase; color:var(--winner-accent);
    background:rgba(0,0,0,.2); padding:4px 14px; border-radius:999px;
    white-space:nowrap; flex-shrink:0;
  }
  .winner-name {
    font-size:2.2rem; font-weight:900; line-height:1;
    color:var(--winner-text); flex:1; text-align:center;
  }
  .winner-ch {
    font-size:.9rem; font-weight:700; color:var(--winner-text);
    opacity:.55; flex-shrink:0;
  }
</style>
</head>
<body>

<div id="waiting">
  <div class="logo">⚡ BRIDGELAB</div>
  <div class="sub">In attesa della domanda...</div>
</div>

<div id="question-view">
  <!-- contenuto iniettato dal JS -->
</div>

<!-- countdown fisso in alto a destra -->
<div id="countdown-box">
  <div id="countdown-num">3</div>
  <div id="countdown-label">Non premere!</div>
</div>

<!-- winner bar in basso: non viene mai sovrascritto da innerHTML -->
<div id="winner-overlay">
  <div class="winner-name" id="winner-name-text">—</div>
</div>

<script>
// ── Countdown ─────────────────────────────────────────────────────────────────
let _cdInterval  = null;
let _cdEndMs     = 0;
let _cdLastSec   = -1;
let _cdOpenedAt  = null;

function startCountdown(openedAt, fromLive = false, durationMs = 3000, color = '#fb923c') {
  if (_cdOpenedAt === openedAt) return; // già in corso per questo open
  _cdOpenedAt = openedAt;
  _cdEndMs    = fromLive ? Date.now() + durationMs : new Date(openedAt).getTime() + durationMs;
  _cdLastSec  = -1;

  // Applica colore
  const numEl = document.getElementById('countdown-num');
  numEl.style.color      = color;
  numEl.style.textShadow = `0 0 60px ${color}99`;
  document.getElementById('countdown-label').style.color = color;

  if (_cdInterval) clearInterval(_cdInterval);
  _cdInterval = setInterval(tickCd, 80);
  tickCd();
}

function stopCountdown() {
  if (_cdInterval) { clearInterval(_cdInterval); _cdInterval = null; }
  document.getElementById('countdown-box').classList.remove('show');
}

function tickCd() {
  const remaining = _cdEndMs - Date.now();
  if (remaining <= 0) { stopCountdown(); return; }
  const secs = Math.ceil(remaining / 1000);
  document.getElementById('countdown-box').classList.add('show');
  if (secs !== _cdLastSec) {
    _cdLastSec = secs;
    const el = document.getElementById('countdown-num');
    el.textContent = secs;
    // riapplica animazione
    el.style.animation = 'none';
    void el.offsetWidth;
    el.style.animation = '';
  }
}

const COLOR_PRESETS = {
  yellow: { bg: '#f59e0b', text: '#0b0f1a' },
  green:  { bg: '#22c55e', text: '#052e16' },
  blue:   { bg: '#3b82f6', text: '#eff6ff' },
  red:    { bg: '#ef4444', text: '#fff1f2' },
};

let currentQuestionId = null;
let hasMedia          = false;
let _teams            = [];
let _state            = {};

// ── Applicazione stato ────────────────────────────────────────────────────────
function applyProjection(dq) {
  // ── Risposta selezionata ──────────────────────────
    const sel = dq.selected_answer;
    document.querySelectorAll('.answer-card').forEach(c => c.classList.remove('ans-correct', 'ans-wrong'));
    if (sel) {
      const card = document.getElementById(`ans-${sel.id}`);
      if (card) card.classList.add(sel.is_correct ? 'ans-correct' : 'ans-wrong');
    }

    applyWinner(dq.winner_color);
    if (dq.winner_color) applyColor(dq.winner_color);

    // ── Domanda ──────────────────────────────────────
    const q = dq.question;
    if (!q) {
      if (currentQuestionId !== null) {
        currentQuestionId = null;
        document.getElementById('waiting').style.display = 'flex';
        document.getElementById('question-view').classList.remove('active');
      }
    } else {
      if (q.id !== currentQuestionId) {
        currentQuestionId = q.id;
        // Nuova domanda: cancella vincitore e risposta colorata localmente
        _state = Object.assign({}, _state, { winner: null });
        renderQuestion(q);
        document.getElementById('waiting').style.display = 'none';
        document.getElementById('question-view').classList.add('active');
      }
    }
}

function renderQuestion(q) {
  hasMedia = !!q.media_url;
  const answers = q.answers || [];

  const answerCards = answers.map((a, i) => `
    <div class="answer-card" id="ans-${a.id}">
      <div class="answer-num">${i + 1}</div>
      <div class="answer-text">${esc(a.text)}</div>
    </div>
  `).join('');

  const view    = document.getElementById('question-view');
  const overlay = document.getElementById('winner-overlay');

  if (hasMedia) {
    const mediaHtml = q.media_type === 'video'
      ? `<video src="${q.media_url}" autoplay loop muted playsinline></video>`
      : `<img src="${q.media_url}" alt="">`;

    view.innerHTML = `
      <div class="layout-media">
        <div class="left">
          <div id="q-text">${esc(q.text)}</div>
          <div id="q-media">${mediaHtml}</div>
        </div>
        <div class="right">${answerCards}</div>
      </div>`;
  } else {
    view.innerHTML = `
      <div class="layout-text">
        <div id="q-text">${esc(q.text)}</div>
        <div id="answers-wrap">${answerCards}</div>
      </div>`;
  }

}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Boot: carica stato iniziale, poi ascolta WebSocket ───────────────────────
async function init() {
  try {
    const [rq, rs] = await Promise.all([fetch('/project/active'), fetch('/state')]);
    const dq = await rq.json();
    const ds = await rs.json();
    _teams = ds.teams || [];
    _state = ds.state || {};
    applyProjection(dq);
    if (_state.phase === 'open' && _state.opened_at) {
      const dur   = (_state.countdown_duration || 3) * 1000;
      const color = _state.countdown_color || '#fb923c';
      startCountdown(_state.opened_at, false, dur, color);
    }
  } catch(e) {}

  if (!window.Echo) { console.error('Echo non caricato'); return; }

  // Proiezione: domanda attiva + risposta selezionata + colore
  window.Echo.channel('projection')
    .listen('.projection.updated', (data) => {
      applyProjection(data);
    });

  // Stato bridge: vincitore + squadre + countdown
  window.Echo.channel('bridge-state')
    .listen('.state.updated', (data) => {
      _teams = data.teams || [];
      _state = data.state || {};
      applyWinner();
      if (_state.phase === 'open' && _state.opened_at) {
        const dur   = (_state.countdown_duration || 3) * 1000;
        const color = _state.countdown_color || '#fb923c';
        startCountdown(_state.opened_at, true, dur, color);
      } else {
        stopCountdown();
      }
    });
}

function applyWinner(color) {
  const channel = _state?.winner?.channel ?? null;
  const overlay = document.getElementById('winner-overlay');
  if (channel) {
    const team = _teams.find(t => t.channel === channel);
    document.getElementById('winner-name-text').textContent = team ? team.name : `Canale ${channel}`;
    overlay.classList.add('show');
    overlay.classList.toggle('has-media', hasMedia);
    if (color) applyColor(color);
  } else {
    overlay.classList.remove('show');
  }
}

function applyColor(key) {
  const c = COLOR_PRESETS[key] || COLOR_PRESETS.yellow;
  const overlay = document.getElementById('winner-overlay');
  overlay.style.background = c.bg;
  overlay.style.color      = c.text;
}

init();
</script>
</body>
</html>
