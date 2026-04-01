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
    --bg:      #0b0f1a;
    --surface: #111827;
    --border:  #1e293b;
    --text:    #e2e8f0;
    --muted:   #64748b;
    --online:  #22c55e;
    --offline: #ef4444;
    --ch1: #3b82f6; --ch2: #22c55e; --ch3: #ef4444; --ch4: #f59e0b;
    --ch5: #a855f7; --ch6: #ec4899; --ch7: #06b6d4; --ch8: #84cc16;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: "Segoe UI", system-ui, sans-serif;
    background: var(--bg); color: var(--text);
    height: 100vh; overflow: hidden;
    display: flex; flex-direction: column;
  }

  /* ── Header ──────────────────────────────────────── */
  header {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    flex-shrink: 0;
  }
  header h1 { font-size: 1rem; font-weight: 700; letter-spacing: .06em; flex-shrink: 0; }

  .pill {
    display: flex; align-items: center; gap: 7px;
    font-size: .72rem; font-weight: 600;
    padding: 3px 10px; border-radius: 999px;
    border: 1px solid var(--border); background: #0f172a;
    transition: color .3s, border-color .3s;
  }
  .pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--muted); transition: background .3s; }
  .pill.online  { color: #bbf7d0; border-color: #166534; }
  .pill.online  .dot { background: var(--online); }
  .pill.offline { color: #fecaca; border-color: #7f1d1d; }
  .pill.offline .dot { background: var(--offline); }

  .hdr-spacer { flex: 1; }
  .hdr-actions { display: flex; gap: 8px; align-items: center; }

  /* ── Phase banner ────────────────────────────────── */
  #phase-banner {
    text-align: center; flex-shrink: 0;
    font-size: .8rem; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
    padding: 7px 24px; border-bottom: 1px solid var(--border);
    transition: background .4s, color .4s, border-color .4s;
  }
  #phase-banner.idle     { background: #0f172a; color: var(--muted); border-color: var(--border); }
  #phase-banner.open     { background: #0c1e3a; color: #60a5fa; border-color: #1e3a6e;
                            animation: pulse-open 1s ease-in-out infinite alternate; }
  #phase-banner.locked   { background: #1c0a0a; color: #f87171; border-color: #7f1d1d; }
  #phase-banner.countdown{ background: #1a0e00; color: #fb923c; border-color: #92400e;
                            animation: pulse-cd .5s ease-in-out infinite alternate; }
  @keyframes pulse-open { from { background:#0c1e3a; } to { background:#0f2d5a; } }
  @keyframes pulse-cd   { from { background:#1a0e00; } to { background:#2d1600; } }

  /* ── 3-column card layout ────────────────────────── */
  main {
    flex: 1; min-height: 0;
    display: grid;
    grid-template-columns: 250px 1fr 270px;
    overflow: hidden;
  }

  .card {
    display: flex; flex-direction: column;
    border-right: 1px solid var(--border);
    overflow: hidden; min-height: 0;
  }
  .card:last-child { border-right: none; }

  .card-header {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 9px 14px; flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    font-size: .62rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--muted);
  }

  .card-body {
    flex: 1; overflow-y: auto; min-height: 0;
  }

  .card-footer {
    flex-shrink: 0;
    padding: 10px 12px;
    border-top: 1px solid var(--border);
    background: var(--surface);
  }

  /* section separator inside card-body */
  .sect {
    padding: 8px 14px 4px;
    font-size: .58rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--muted);
    border-bottom: 1px solid var(--border);
    background: var(--bg);
    flex-shrink: 0;
  }

  /* ── Winner bar (inside card 1) ──────────────────── */
  #winner-section {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px; flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    min-height: 40px;
  }
  #winner-label {
    font-size: .58rem; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--muted); flex-shrink: 0;
  }
  #winner-name {
    font-size: 1rem; font-weight: 900; flex: 1;
    color: var(--muted); transition: color .4s;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  #winner-sub {
    font-size: .7rem; font-weight: 600; color: var(--muted);
    transition: color .4s; flex-shrink: 0;
  }

  /* ── Team rows ───────────────────────────────────── */
  .team-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    transition: background .3s; flex-shrink: 0;
  }
  .team-row.winner-row { background: rgba(255,255,255,.04); }
  .team-row.early-row  { background: rgba(251,146,60,.07); }

  .team-ch {
    font-size: .58rem; font-weight: 800; letter-spacing: .1em;
    text-transform: uppercase; flex-shrink: 0; width: 28px;
  }
  .team-name-wrap { flex: 1; min-width: 0; }
  .team-name {
    font-size: .88rem; font-weight: 700;
    background: transparent; border: none; outline: none;
    color: var(--text); width: 100%;
    border-bottom: 1px solid transparent; transition: border-color .2s;
  }
  .team-name:focus { border-bottom-color: var(--border); }

  .team-score {
    font-size: 1.1rem; font-weight: 900; line-height: 1;
    flex-shrink: 0; width: 38px; text-align: right; transition: color .3s;
  }

  .team-badge {
    font-size: .55rem; font-weight: 800; letter-spacing: .08em;
    padding: 2px 6px; border-radius: 999px; text-transform: uppercase;
    flex-shrink: 0; min-width: 48px; text-align: center;
    min-height: 18px; line-height: 14px;
    opacity: 0; transition: opacity .3s;
  }
  .team-row.winner-row .team-badge { opacity: 1; background: gold; color: #000; }
  .team-row.buzz-row   .team-badge { opacity: 1; background: #1e293b; color: var(--muted); }
  .team-row.early-row  .team-badge { opacity: 1; background: #7c2d12; color: #fdba74; }
  .team-row.early-row  .team-badge::before { content: 'PRESTO'; }

  /* ── Buzz list ───────────────────────────────────── */
  #buzz-list { display: flex; flex-direction: column; }

  .buzz-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px;
    border-bottom: 1px solid var(--border);
    animation: fadeIn .3s ease;
  }
  @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; } }

  .buzz-order {
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .65rem; font-weight: 800;
    background: var(--border); color: var(--muted);
  }
  .buzz-row.first .buzz-order { background: gold; color: #000; }
  .buzz-row.early { background: rgba(124,45,18,.18); }
  .buzz-row.early .buzz-order { background: #ea580c; color: #fff7ed; }

  .buzz-info { flex: 1; min-width: 0; }
  .buzz-name { font-size: .82rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .buzz-time { font-size: .64rem; color: var(--muted); }
  .empty-hint { color: var(--muted); font-size: .8rem; padding: 12px 14px; }

  /* ── Question list ───────────────────────────────── */
  #questions-panel { display: flex; flex-direction: column; gap: 0; }

  .q-btn {
    background: transparent; border: none; border-bottom: 1px solid var(--border);
    border-radius: 0; padding: 10px 14px; cursor: pointer;
    text-align: left; color: var(--text);
    font-size: .82rem; font-weight: 600;
    transition: background .15s;
    display: flex; align-items: center; gap: 10px; width: 100%;
  }
  .q-btn:hover  { background: #111827; }
  .q-btn.active { background: #0c1e3a; color: #93c5fd; }
  .q-btn .q-num { font-size: .62rem; font-weight: 800; color: var(--muted); flex-shrink: 0; width: 18px; text-align: right; }
  .q-btn.active .q-num { color: #60a5fa; }

  /* ── Controls card ───────────────────────────────── */
  #ctrl-body { display: flex; flex-direction: column; gap: 0; }

  .ctrl-section { padding: 12px 14px; border-bottom: 1px solid var(--border); }

  .ctrl-label {
    font-size: .58rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--muted); margin-bottom: 8px;
  }

  /* ── Buttons ─────────────────────────────────────── */
  button {
    cursor: pointer; font-size: .8rem; font-weight: 700;
    padding: 9px 14px; border-radius: 8px; border: 1px solid var(--border);
    background: #0f172a; color: var(--text);
    transition: background .15s, transform .1s;
  }
  button:hover    { background: #1e293b; }
  button:active   { transform: scale(.97); }
  button:disabled { opacity: .35; cursor: not-allowed; }

  .btn-open  { border-color: #1e3a6e; color: #93c5fd; }
  .btn-open:not(:disabled):hover { background: #0c1e3a; }
  .btn-danger { border-color: #4b2020; color: #fca5a5; }
  .btn-danger:hover { background: #1c0a0a; }
  .btn-block  { width: 100%; }
  .btn-sm     { font-size: .7rem; padding: 4px 10px; }
  .btn-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .btn-full   { grid-column: span 2; }

  /* early checkbox */
  .early-label {
    display: flex; align-items: center; gap: 8px;
    font-size: .78rem; color: var(--muted); cursor: pointer; margin-bottom: 10px;
  }
  .early-label input { accent-color: #fb923c; width: 14px; height: 14px; }

  /* color swatches */
  .color-swatches { display: flex; gap: 8px; align-items: center; }
  .swatch {
    width: 26px; height: 26px; border-radius: 6px; cursor: pointer; flex-shrink: 0;
    border: 2px solid transparent; transition: transform .1s, border-color .15s;
  }
  .swatch:hover  { transform: scale(1.15); }
  .swatch.active { border-color: #fff; transform: scale(1.15); }

  /* answer buttons */
  #answer-buttons { display: flex; flex-direction: column; gap: 5px; }
  .ans-btn {
    cursor: pointer; text-align: left; padding: 8px 12px; border-radius: 8px;
    font-size: .78rem; font-weight: 600; border: 1px solid var(--border);
    background: #0f172a; color: var(--text); transition: background .15s; width: 100%;
  }
  .ans-btn:hover           { background: #1e293b; }
  .ans-btn.correct         { border-color: #14532d; color: #86efac; }
  .ans-btn.correct:hover   { background: #0a2318; }
  .ans-btn.wrong           { border-color: #7f1d1d; color: #fca5a5; }
  .ans-btn.wrong:hover     { background: #1c0a0a; }

  /* sim buttons */
  #sim-buttons { display: flex; flex-direction: column; gap: 5px; }
  .sim-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px; border-radius: 8px; cursor: pointer;
    border: 1px solid var(--border); background: #0f172a;
    color: var(--text); font-size: .8rem; font-weight: 700;
    transition: background .15s, transform .08s; width: 100%; text-align: left;
  }
  .sim-btn:active { transform: scale(.97); }
  .sim-btn .sim-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

  /* header link buttons */
  .hdr-link {
    font-size: .72rem; padding: 4px 11px; border-radius: 7px;
    border: 1px solid var(--border); color: var(--muted);
    background: #0f172a; text-decoration: none;
    font-weight: 600;
  }

  /* ── Channel colours ─────────────────────────────── */
  .ch-1 { color: var(--ch1); } .ch-2 { color: var(--ch2); }
  .ch-3 { color: var(--ch3); } .ch-4 { color: var(--ch4); }
  .ch-5 { color: var(--ch5); } .ch-6 { color: var(--ch6); }
  .ch-7 { color: var(--ch7); } .ch-8 { color: var(--ch8); }

  /* ── Settings modal ──────────────────────────────── */
  .smodal-overlay {
    display: none; position: fixed; inset: 0; z-index: 500;
    background: rgba(0,0,0,.7); align-items: center; justify-content: center;
  }
  .smodal-overlay.open { display: flex; }

  .smodal-box {
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
    width: 900px; max-width: 96vw; max-height: 90vh;
    display: flex; flex-direction: column; overflow: hidden;
  }

  .smodal-header {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 13px 18px; border-bottom: 1px solid var(--border); flex-shrink: 0;
    background: #0f172a;
  }
  .smodal-title { font-size: .85rem; font-weight: 800; letter-spacing: .04em; }
  .smodal-close {
    background: none; border: none; color: var(--muted); font-size: 1rem;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
  }
  .smodal-close:hover { background: var(--border); color: var(--text); }

  .smodal-game-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0;
    background: var(--bg);
    font-size: .7rem; color: var(--muted);
  }

  .smodal-body { flex: 1; overflow-y: auto; }

  .sm-empty { color: var(--muted); font-size: .82rem; padding: 24px; text-align: center; }

  /* question item */
  .sq { border-bottom: 1px solid var(--border); }

  /* clickable header row */
  .sq-header {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; background: var(--surface);
    cursor: pointer; user-select: none; transition: background .15s;
  }
  .sq-header:hover { background: #151f2e; }

  /* drag handle */
  .sq-drag {
    color: var(--border); font-size: .95rem; cursor: grab; flex-shrink: 0;
    letter-spacing: -2px; line-height: 1; padding: 0 2px;
    transition: color .15s;
  }
  .sq-header:hover .sq-drag { color: var(--muted); }
  .sq-drag:active { cursor: grabbing; }

  /* drag states */
  .sq.dragging  { opacity: .35; }
  .sq.drop-above { border-top: 2px solid #3b82f6; }
  .sq.drop-below { border-bottom: 2px solid #3b82f6; }


  .sq-num {
    width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem; font-weight: 800; background: var(--border); color: var(--muted);
  }

  .sq-preview {
    flex: 1; font-size: .85rem; font-weight: 600; color: var(--text);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }

  .sq-chevron {
    font-size: .6rem; color: var(--muted); flex-shrink: 0;
    transition: transform .2s;
  }
  .sq.open .sq-chevron { transform: rotate(90deg); }

  /* collapsible body */
  .sq-body { display: none; }
  .sq.open .sq-body { display: block; }

  /* edit row inside body */
  .sq-edit-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px 8px 52px;
    background: #0a1120; border-bottom: 1px solid var(--border);
  }

  .sq-text-input {
    flex: 1; background: transparent; border: none; outline: none;
    color: var(--text); font-size: .85rem; font-weight: 600;
    border-bottom: 1px solid transparent; transition: border-color .2s; padding: 2px 0;
  }
  .sq-text-input:focus { border-bottom-color: var(--border); }

  .sq-move-inline { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }

  .sq-move-sel {
    background: #0f172a; border: 1px solid var(--border); border-radius: 6px;
    color: var(--muted); font-size: .7rem; padding: 4px 7px; outline: none; max-width: 150px;
  }
  .sq-move-sel:focus { border-color: #3b82f6; color: var(--text); }

  .sq-pos-input {
    background: #0f172a; border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-size: .78rem; padding: 4px 6px; outline: none; text-align: center;
  }
  .sq-pos-input:focus { border-color: #3b82f6; }

  .sq-move-btn {
    padding: 4px 10px; border-radius: 6px; font-size: .8rem;
    border-color: #1e3a6e; color: #93c5fd; flex-shrink: 0;
  }
  .sq-move-btn:hover { background: #0c1e3a; }

  /* answers list under question */
  .sq-answers { padding: 4px 14px 8px 52px; background: var(--bg); }

  .sa-row {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 0; border-bottom: 1px solid var(--border);
  }
  .sa-row:last-child { border-bottom: none; }

  .sa-text-input {
    flex: 1; background: transparent; border: none; outline: none;
    color: var(--text); font-size: .82rem; font-weight: 600;
    border-bottom: 1px solid transparent; transition: border-color .2s; padding: 2px 0;
  }
  .sa-text-input:focus { border-bottom-color: var(--border); }

  .sa-value-input {
    width: 58px; background: #0f172a; border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); font-size: .78rem; padding: 3px 6px; outline: none; text-align: center;
  }
  .sa-value-input:focus { border-color: #3b82f6; }

</style>
</head>
<body>

<header>
  <h1>⚡ BridgeLab</h1>
  <div class="pill offline" id="bridge-pill">
    <span class="dot"></span>
    <span id="bridge-text">Bridge offline</span>
  </div>
  <div class="hdr-spacer"></div>
  <div class="hdr-actions">
    <a href="/games"   class="hdr-link">Giochi</a>
    <a href="/project" class="hdr-link" target="_blank">Proiezione</a>
    <button id="btn-launch" onclick="doLaunch()" style="font-size:.72rem;padding:4px 11px;border-radius:7px;">▶ Avvia Bridge</button>
    <button id="btn-close-bridge" onclick="doCloseBridge()" style="font-size:.72rem;padding:4px 11px;border-radius:7px;border-color:#4b2020;color:#fca5a5;">✕ Chiudi Bridge</button>
  </div>
</header>

<div id="phase-banner" class="idle">In attesa</div>

<main>

  <!-- ── Card 1: Partecipanti ──────────────────────── -->
  <div class="card">

    <div class="card-header">
      <span>Partecipanti</span>
      <button onclick="doResetScores()" class="btn-sm btn-danger">↺ Azzera</button>
    </div>

    <div id="winner-section">
      <div id="winner-label">In attesa</div>
      <div id="winner-name">–</div>
      <div id="winner-sub"></div>
    </div>

    <div id="teams-list"><!-- filled by JS --></div>

    <div class="sect">Ordine di risposta</div>
    <div id="buzz-list">
      <div class="empty-hint">Nessuna pressione</div>
    </div>

  </div>

  <!-- ── Card 2: Domande ───────────────────────────── -->
  <div class="card">

    <div class="card-header">
      <span>Domande</span>
      <div style="display:flex;align-items:center;gap:6px;">
        <select id="game-select" onchange="loadGameQuestions(this.value)"
          style="background:#0f172a;border:1px solid var(--border);border-radius:6px;
                 color:var(--text);font-size:.7rem;padding:3px 8px;outline:none;max-width:150px;">
          <option value="">— seleziona gioco —</option>
        </select>
        <button onclick="openSettings()" title="Impostazioni"
          style="padding:3px 8px;font-size:.75rem;border-radius:6px;flex-shrink:0;">⚙</button>
      </div>
    </div>

    <div class="card-body">
      <div id="questions-panel">
        <div class="empty-hint">Seleziona un gioco per vedere le domande.</div>
      </div>
    </div>

    <div class="card-footer">
      <button onclick="doProjectClear()" class="btn-danger btn-block" id="btn-clear-project">Nascondi proiezione</button>
    </div>

  </div>

  <!-- ── Card 3: Controlli ─────────────────────────── -->
  <div class="card">

    <div class="card-header">Controlli</div>

    <div class="card-body" id="ctrl-body">

      <div class="ctrl-section">
        <label class="early-label">
          <input type="checkbox" id="chk-early" checked>
          Check Early
        </label>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
          <label style="font-size:.72rem;color:var(--muted);flex-shrink:0;">Countdown</label>
          <input type="number" id="cd-duration" value="3" min="1" max="30"
            style="width:52px;background:#0f172a;border:1px solid var(--border);border-radius:6px;
                   color:var(--text);font-size:.8rem;padding:4px 6px;outline:none;text-align:center;">
          <label style="font-size:.72rem;color:var(--muted);flex-shrink:0;">s &nbsp;Colore</label>
          <input type="color" id="cd-color" value="#fb923c"
            style="width:32px;height:28px;border:1px solid var(--border);border-radius:6px;
                   background:#0f172a;cursor:pointer;padding:2px;">
        </div>
        <div class="btn-grid">
          <button class="btn-open btn-full" id="btn-open" onclick="doOpen()">▶ Apri Buzzer</button>
          <button onclick="doReset()" class="btn-danger btn-full">↺ Reset Round</button>
        </div>
      </div>

      <div class="ctrl-section">
        <div class="ctrl-label">Colore card vincitore</div>
        <div class="color-swatches">
          <div class="swatch active" data-color="yellow" style="background:#f59e0b" onclick="doSetColor('yellow')" title="Giallo"></div>
          <div class="swatch" data-color="green"  style="background:#22c55e" onclick="doSetColor('green')"  title="Verde"></div>
          <div class="swatch" data-color="blue"   style="background:#3b82f6" onclick="doSetColor('blue')"   title="Blu"></div>
          <div class="swatch" data-color="red"    style="background:#ef4444" onclick="doSetColor('red')"    title="Rosso"></div>
        </div>
      </div>

      <div class="ctrl-section">
        <div class="ctrl-label">Risposte</div>
        <div id="answer-buttons"><div class="empty-hint" style="padding:0;font-size:.75rem;">Seleziona una domanda</div></div>
      </div>

      <div class="ctrl-section">
        <div class="ctrl-label">Simulazione</div>
        <div id="sim-buttons"></div>
      </div>

    </div>

  </div>

</main>

<!-- ── Settings Modal ────────────────────────────────── -->
<div id="settings-modal" class="smodal-overlay" onclick="if(event.target===this)closeSettings()">
  <div class="smodal-box">

    <div class="smodal-header">
      <span class="smodal-title">Impostazioni domande</span>
      <button class="smodal-close" onclick="closeSettings()">✕</button>
    </div>

    <div class="smodal-game-bar">
      <span>Gioco:</span>
      <select id="settings-game-sel" onchange="loadSettingsQs(this.value)"
        style="background:#0f172a;border:1px solid var(--border);border-radius:6px;
               color:var(--text);font-size:.78rem;padding:4px 8px;outline:none;min-width:180px;">
        <option value="">— seleziona —</option>
      </select>
    </div>

    <div class="smodal-body">
      <div id="settings-qs-list">
        <div class="sm-empty">Seleziona un gioco per modificare le domande.</div>
      </div>
    </div>

  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const CH_COLORS   = ['','#3b82f6','#22c55e','#ef4444','#f59e0b','#a855f7','#ec4899','#06b6d4','#84cc16'];
const PHASE_LABELS = { idle: 'In attesa', open: 'BUZZER APERTO', locked: 'BLOCCATO' };

let teams        = [];
let bridgeOnline = false;

// ── Countdown ─────────────────────────────────────────────────────────────────
let _cdInterval  = null;
let _cdEndMs     = 0;
let _cdLastSec   = -1;
let _cdStartedAt = null;

document.addEventListener('DOMContentLoaded', () => {

    fetch('/state')
        .then(r => r.json())
        .then(render);

    if (!window.Echo) { console.error('Echo NON caricato'); return; }

    window.Echo.channel('bridge-state')
        .listen('.state.updated', (data) => {
            console.log('WS EVENT', data);

            if (data.state.phase === 'open' && !_cdInterval) {
                const openedMs = new Date(data.state.opened_at).getTime();
                const duration = (data.state.countdown_duration || 3) * 1000;
                startCountdown(openedMs, duration);
            }

            render({
                bridge_online: data.bridge_online,
                state:         data.state,
                teams:         data.teams,
            });
        });

    if (!window._pollStarted) {
        window._pollStarted = true;
        setInterval(async () => {
            try {
                const r    = await fetch('/state');
                const data = await r.json();
                if (data.bridge_online !== bridgeOnline) render(data);
            } catch(e) {}
        }, 3000);
    }
});

function startCountdown(startMs, durationMs = 3000) {
    if (!startMs) return;
    if (_cdStartedAt === startMs) return;
    _cdStartedAt = startMs;

    if (_cdInterval) clearInterval(_cdInterval);
    _cdEndMs    = Date.now() + durationMs;
    _cdLastSec  = -1;
    _cdInterval = setInterval(tickCountdown, 80);
    tickCountdown();
}

function stopCountdown() {
    if (_cdInterval) { clearInterval(_cdInterval); _cdInterval = null; }
    _cdLastSec = -1;
}

function tickCountdown() {
    const remaining = _cdEndMs - Date.now();
    const banner    = document.getElementById('phase-banner');
    if (remaining <= 0) {
        stopCountdown();
        banner.className   = 'open';
        banner.textContent = PHASE_LABELS['open'];
        return;
    }
    const secs = Math.ceil(remaining / 1000);
    banner.className   = 'countdown';
    banner.textContent = `PREMI TRA ${secs}…`;
}

// ── Render ────────────────────────────────────────────────────────────────────
function render(data) {
    // Bridge pill
    bridgeOnline = !!data.bridge_online;
    document.getElementById('bridge-text').textContent = bridgeOnline ? 'Bridge online' : 'Bridge offline';
    document.getElementById('bridge-pill').className   = 'pill ' + (bridgeOnline ? 'online' : 'offline');
    document.getElementById('btn-launch').disabled      = bridgeOnline;
    document.getElementById('btn-close-bridge').disabled = !bridgeOnline;

    const s = data.state;
    teams   = data.teams || [];

    // Phase banner
    if (!_cdInterval) {
        const banner = document.getElementById('phase-banner');
        banner.className   = s.phase;
        banner.textContent = PHASE_LABELS[s.phase] || s.phase;
    }

    document.getElementById('btn-open').disabled = !bridgeOnline || s.phase === 'open';

    renderTeams(teams, s);
    currentWinnerChannel = s.winner?.channel ?? null;
    renderWinner(s, teams);
    renderBuzzList(s.buzzes || [], s.early_buzzes || [], teams);
}

function renderTeams(teams, s) {
    const list = document.getElementById('teams-list');
    const key  = teams.map(t => `${t.channel}:${t.name}:${t.score}`).join('|')
               + s.phase + (s.winner?.channel ?? '')
               + '|b:' + (s.buzzes || []).map(b => `${b.channel}:${b.order}`).join(',')
               + '|e:' + (s.early_buzzes || []).map(b => b.channel).join(',');
    if (list.dataset.key === key) return;
    list.dataset.key = key;

    list.innerHTML = teams.map(t => {
        const color      = CH_COLORS[t.channel] || '#e2e8f0';
        const isWinner   = s.winner && s.winner.channel === t.channel;
        const buzzEntry  = (s.buzzes || []).find(b => b.channel === t.channel);
        const isBuzz     = !isWinner && !!buzzEntry;
        const isEarly    = !isWinner && !isBuzz && (s.early_buzzes || []).some(b => b.channel === t.channel);
        const badge      = isWinner ? '🏆 1°' : (isBuzz ? `#${buzzEntry.order}` : '');
        const rowClass   = isWinner ? 'winner-row' : (isBuzz ? 'buzz-row' : (isEarly ? 'early-row' : ''));
        return `<div class="team-row ${rowClass}">
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

    renderSimButtons(teams);
}

function renderWinner(s, teams) {
    const wName = document.getElementById('winner-name');
    const wLbl  = document.getElementById('winner-label');
    const wSub  = document.getElementById('winner-sub');

    if (s.winner) {
        stopCountdown();
        const ch    = s.winner.channel;
        const team  = teams.find(t => t.channel === ch);
        const color = CH_COLORS[ch] || '#e2e8f0';
        wLbl.textContent  = '🏆 PRIMO';
        wName.textContent = team ? team.name : `Canale ${ch}`;
        wName.style.color = color;
        wSub.textContent  = `CH ${ch} · ${fmtTime(s.winner.at)}`;
        wSub.style.color  = color;
    } else if (s.phase === 'open') {
        if (_cdInterval) return;
        wLbl.textContent  = '';
        wName.textContent = 'Buzzer aperto';
        wName.style.color = '#60a5fa';
        wSub.textContent  = '';
        wSub.style.color  = '';
    } else {
        stopCountdown();
        wLbl.textContent  = 'In attesa';
        wName.textContent = '–';
        wName.style.color = 'var(--muted)';
        wSub.textContent  = '';
        wSub.style.color  = '';
    }
}

function renderBuzzList(buzzes, earlyBuzzes, teams) {
    const list = document.getElementById('buzz-list');
    const key  = 'b:' + buzzes.map(b => b.channel + ':' + b.order).join(',')
               + '|e:' + earlyBuzzes.map(b => b.channel + ':' + b.at).join(',');
    if (list.dataset.key === key) return;
    list.dataset.key = key;

    if (buzzes.length === 0 && earlyBuzzes.length === 0) {
        list.innerHTML = '<div class="empty-hint">Nessuna pressione</div>';
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
            <div class="buzz-time">CH ${b.channel} · anticipo ${fmtTime(b.at)}</div>
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
        const res  = await fetch('/bridge/launch', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
        const data = await res.json();
        if (!data.ok) alert('Errore avvio bridge:\n' + (data.error ?? 'risposta non valida'));
    } catch(e) { alert('Errore di rete: ' + e.message); }
    setTimeout(() => { btn.disabled = false; btn.textContent = '▶ Avvia Bridge'; }, 3000);
}

async function doCloseBridge() {
    const btn = document.getElementById('btn-close-bridge');
    btn.disabled = true; btn.textContent = '…';
    try { await fetch('/bridge/close', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }); } catch(e) {}
    setTimeout(() => { btn.textContent = '✕ Chiudi Bridge'; }, 1500);
}

async function doOpen() {
    if (!bridgeOnline) return;
    const checkEarly = document.getElementById('chk-early').checked;
    const duration   = parseInt(document.getElementById('cd-duration').value) || 3;
    const color      = document.getElementById('cd-color').value || '#fb923c';
    await post('/host/open', { check_early: checkEarly, countdown_duration: duration, countdown_color: color });
}
async function doReset()       { await post('/host/reset'); }
async function doResetScores() { await post('/host/reset-scores'); }

// ── Games / Questions ─────────────────────────────────────────────────────────
let activeQuestionId    = null;
let questionsData       = {};
let currentWinnerChannel = null;

async function initGamesSelect() {
    try {
        const res  = await fetch('/games', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const sel  = document.getElementById('game-select');
        (data.games || []).forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id; opt.textContent = g.name;
            sel.appendChild(opt);
        });
    } catch(e) { console.error(e); }
}

async function loadGameQuestions(gameId) {
    const panel = document.getElementById('questions-panel');
    if (!gameId) {
        panel.innerHTML = '<div class="empty-hint">Seleziona un gioco per vedere le domande.</div>';
        return;
    }
    try {
        const res  = await fetch(`/games/${gameId}/questions`);
        const data = await res.json();
        const qs   = data.questions || [];

        if (!qs.length) {
            panel.innerHTML = '<div class="empty-hint">Nessuna domanda in questo gioco.</div>';
            return;
        }

        questionsData = {};
        panel.innerHTML = '';
        qs.forEach((q, i) => {
            questionsData[q.id] = q;
            const btn = document.createElement('button');
            btn.className = 'q-btn';
            btn.id = `qbtn-${q.id}`;
            btn.innerHTML = `<span class="q-num">${i + 1}</span>
                             <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(q.text)}</span>`;
            btn.onclick = () => doProjectQuestion(q.id);
            panel.appendChild(btn);
        });
    } catch(e) { console.error(e); }
}

async function doProjectQuestion(id) {
    activeQuestionId = id;
    document.querySelectorAll('.q-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`qbtn-${id}`)?.classList.add('active');
    await fetch('/project/active', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ question_id: id }),
    });
    renderAnswerButtons(id);
}

function renderAnswerButtons(questionId) {
    const wrap = document.getElementById('answer-buttons');
    const q    = questionsData[questionId];
    if (!q || !q.answers || !q.answers.length) {
        wrap.innerHTML = '<div class="empty-hint" style="padding:0;font-size:.75rem;">Nessuna risposta</div>';
        return;
    }
    wrap.innerHTML = q.answers.map(a => {
        const isCorrect = a.value > 0;
        return `<button class="ans-btn ${isCorrect ? 'correct' : 'wrong'}"
          onclick="doSelectAnswer(${a.id}, ${isCorrect})">${escHtml(a.text)}</button>`;
    }).join('');
}

async function doSetColor(color) {
    document.querySelectorAll('.swatch').forEach(s => s.classList.toggle('active', s.dataset.color === color));
    await fetch('/project/color', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ color }),
    });
}

async function doSelectAnswer(answerId, isCorrect) {
    await fetch('/project/answer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ answer_id: answerId }),
    });
    if (currentWinnerChannel) {
        await fetch('/host/answer-light', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ channel: currentWinnerChannel, is_correct: isCorrect }),
        });
    }
    await post(isCorrect ? '/host/correct' : '/host/wrong');
}

async function doProjectClear() {
    activeQuestionId = null;
    document.querySelectorAll('.q-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('answer-buttons').innerHTML =
        '<div class="empty-hint" style="padding:0;font-size:.75rem;">Seleziona una domanda</div>';
    await fetch('/project/active', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ question_id: null }),
    });
}

initGamesSelect();

// ── Simulazione ───────────────────────────────────────────────────────────────
function renderSimButtons(teams) {
    const wrap = document.getElementById('sim-buttons');
    wrap.innerHTML = teams.map(t => {
        const color = CH_COLORS[t.channel] || '#e2e8f0';
        return `<button class="sim-btn" onclick="doSimBuzz(${t.channel})"
          style="border-color:${color}22;">
          <span class="sim-dot" style="background:${color}"></span>
          ${escHtml(t.name)}
        </button>`;
    }).join('');
}

async function doSimBuzz(channel) {
    const early = _cdInterval !== null;
    await fetch('/bridge/event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ channel, pressed: true, early }),
    });
}

async function post(url, body = {}) {
    try {
        await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify(body),
        });
    } catch(e) { console.error(e); }
}

// ── Util ──────────────────────────────────────────────────────────────────────
function fmt(n) {
    const v = parseFloat(n) || 0;
    return v % 1 === 0 ? v.toFixed(0) : v.toFixed(2);
}

function fmtTime(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleTimeString('it-IT'); }
    catch { return iso; }
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Settings modal ────────────────────────────────────────────────────────────
let settingsAllGames      = [];
let settingsCurrentGameId = null;

async function openSettings() {
    try {
        const res  = await fetch('/games', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        settingsAllGames = data.games || [];

        const sel = document.getElementById('settings-game-sel');
        sel.innerHTML = '<option value="">— seleziona —</option>';
        settingsAllGames.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id; opt.textContent = g.name;
            sel.appendChild(opt);
        });

        // Pre-select current game if one is active
        const mainSel = document.getElementById('game-select');
        if (mainSel.value) {
            sel.value = mainSel.value;
            loadSettingsQs(mainSel.value);
        }

        document.getElementById('settings-modal').classList.add('open');
    } catch(e) { console.error(e); }
}

function closeSettings() {
    document.getElementById('settings-modal').classList.remove('open');
}

async function loadSettingsQs(gameId) {
    settingsCurrentGameId = gameId;
    const list = document.getElementById('settings-qs-list');

    if (!gameId) {
        list.innerHTML = '<div class="sm-empty">Seleziona un gioco.</div>';
        return;
    }

    list.innerHTML = '<div class="sm-empty">Caricamento…</div>';

    try {
        const res  = await fetch(`/games/${gameId}/questions`);
        const data = await res.json();
        const qs   = data.questions || [];

        if (!qs.length) {
            list.innerHTML = '<div class="sm-empty">Nessuna domanda in questo gioco.</div>';
            return;
        }

        list.innerHTML = qs.map((q, i) => renderSQ(q, i + 1, parseInt(gameId))).join('');
    } catch(e) { console.error(e); }
}

function renderSQ(q, num, currentGameId) {
    const otherGames  = settingsAllGames.filter(g => g.id !== currentGameId);
    const moveOpts    = otherGames.map(g => `<option value="${g.id}">${escHtml(g.name)}</option>`).join('');
    const answersHtml = (q.answers || []).map(a => renderSA(a)).join('');

    return `<div class="sq" data-qid="${q.id}">
      <div class="sq-header" draggable="true" onclick="toggleSQ(${q.id})">
        <span class="sq-drag" onclick="event.stopPropagation()">⠿⠿</span>
        <div class="sq-num">${num}</div>
        <div class="sq-preview">${escHtml(q.text)}</div>
        <div class="sq-chevron">▶</div>
      </div>
      <div class="sq-body">
        <div class="sq-edit-row">
          <input class="sq-text-input" type="text" value="${escHtml(q.text)}"
                 onblur="settingsSaveQ(${q.id}, this.value)">
          <div class="sq-move-inline">
            <select class="sq-move-sel">
              <option value="">Sposta in…</option>
              ${moveOpts}
            </select>
            <input class="sq-pos-input" type="number" value="${num}" min="1"
                   title="Posizione" style="width:52px;">
            <button class="sq-move-btn" onclick="settingsMoveQ(${q.id}, this)">→</button>
          </div>
        </div>
        ${answersHtml ? `<div class="sq-answers">${answersHtml}</div>` : '<div class="sm-empty" style="padding:8px 14px 8px 52px;font-size:.75rem;">Nessuna risposta</div>'}
      </div>
    </div>`;
}

function toggleSQ(qId) {
    document.querySelector(`.sq[data-qid="${qId}"]`)?.classList.toggle('open');
}


async function saveSettingsQOrder(order) {
    await fetch(`/games/${settingsCurrentGameId}/questions/reorder`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body:    JSON.stringify({ order }),
    });
}

// ── Drag-and-drop reorder (delegated, init once) ──────────────────────────────
(function initSQDrag() {
    const list = document.getElementById('settings-qs-list');
    let dragSrc = null;

    function clearDropClasses() {
        list.querySelectorAll('.sq').forEach(el => el.classList.remove('drop-above', 'drop-below'));
    }

    list.addEventListener('dragstart', e => {
        const header = e.target.closest('.sq-header');
        if (!header) { e.preventDefault(); return; }
        dragSrc = header.closest('.sq');
        dragSrc.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.stopPropagation();
    });

    list.addEventListener('dragend', () => {
        if (dragSrc) dragSrc.classList.remove('dragging');
        dragSrc = null;
        clearDropClasses();
    });

    list.addEventListener('dragover', e => {
        e.preventDefault();
        const sq = e.target.closest('.sq');
        if (!sq || !dragSrc || sq === dragSrc) return;
        clearDropClasses();
        const mid = sq.getBoundingClientRect().top + sq.getBoundingClientRect().height / 2;
        sq.classList.add(e.clientY < mid ? 'drop-above' : 'drop-below');
    });

    list.addEventListener('dragleave', e => {
        const sq = e.target.closest('.sq');
        if (sq && !sq.contains(e.relatedTarget)) sq.classList.remove('drop-above', 'drop-below');
    });

    list.addEventListener('drop', e => {
        e.preventDefault();
        const sq = e.target.closest('.sq');
        if (!sq || !dragSrc || sq === dragSrc) return;
        clearDropClasses();

        const mid = sq.getBoundingClientRect().top + sq.getBoundingClientRect().height / 2;
        if (e.clientY < mid) list.insertBefore(dragSrc, sq);
        else                 list.insertBefore(dragSrc, sq.nextSibling);

        // Renumber
        [...list.querySelectorAll(':scope > .sq')].forEach((el, i) => {
            el.querySelector('.sq-num').textContent = i + 1;
            const posInput = el.querySelector('.sq-pos-input');
            if (posInput) posInput.value = i + 1;
        });

        const newOrder = [...list.querySelectorAll(':scope > .sq')].map(el => parseInt(el.dataset.qid));
        saveSettingsQOrder(newOrder);
    });
})();

function renderSA(a) {
    return `<div class="sa-row" data-aid="${a.id}">
      <input class="sa-text-input" type="text" value="${escHtml(a.text)}"
             onblur="settingsSaveA(${a.id})">
      <input class="sa-value-input" type="number" value="${a.value}" step="0.25"
             onblur="settingsSaveA(${a.id})">
    </div>`;
}


async function settingsSaveQ(qId, text) {
    if (!text.trim()) return;
    await fetch(`/questions/${qId}`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body:    JSON.stringify({ text: text.trim() }),
    });
}

async function settingsMoveQ(qId, btn) {
    const row      = btn.closest('.sq-edit-row');
    const gameId   = row.querySelector('.sq-move-sel').value;
    if (!gameId) return;
    const position = parseInt(row.querySelector('.sq-pos-input').value) || null;
    await fetch(`/questions/${qId}/move`, {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body:    JSON.stringify({ game_id: parseInt(gameId), position }),
    });
    loadSettingsQs(settingsCurrentGameId);
}

async function settingsSaveA(aId) {
    const row   = document.querySelector(`.sa-row[data-aid="${aId}"]`);
    if (!row) return;
    const text  = row.querySelector('.sa-text-input').value.trim();
    const value = parseFloat(row.querySelector('.sa-value-input').value) || 0;
    if (!text) return;
    await fetch(`/answers/${aId}`, {
        method:  'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body:    JSON.stringify({ text, value }),
    });
}

</script>
</body>
</html>
