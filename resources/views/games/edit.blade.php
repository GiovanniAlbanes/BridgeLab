<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php use Illuminate\Support\Facades\Storage; @endphp
<title>{{ $game->name }} — BridgeLab</title>
<style>
  :root { --bg:#0b0f1a; --surface:#111827; --border:#1e293b; --text:#e2e8f0; --muted:#64748b; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:"Segoe UI",system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
  header { display:flex; align-items:center; gap:16px; padding:14px 28px; border-bottom:1px solid var(--border); background:var(--surface); }
  header h1 { font-size:1.1rem; font-weight:700; }
  a { color:#60a5fa; text-decoration:none; }
  .container { max-width:860px; margin:32px auto; padding:0 24px; }
  .section-title { font-size:.65rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); margin-bottom:12px; }
  .btn { cursor:pointer; font-size:.8rem; font-weight:700; padding:7px 14px; border-radius:8px; border:1px solid var(--border); background:#0f172a; color:var(--text); transition:background .15s; }
  .btn:hover { background:#1e293b; }
  .btn:disabled { opacity:.4; cursor:not-allowed; }
  .btn-primary { border-color:#1e3a6e; color:#93c5fd; }
  .btn-primary:hover { background:#0c1e3a; }
  .btn-danger  { border-color:#7f1d1d; color:#fca5a5; }
  .btn-danger:hover  { background:#1c0a0a; }
  .btn-green   { border-color:#14532d; color:#86efac; }
  .btn-green:hover   { background:#0a2318; }
  input[type=text], input[type=number], textarea {
    background:#0f172a; border:1px solid var(--border); border-radius:8px;
    padding:8px 12px; color:var(--text); font-size:.875rem; outline:none; width:100%;
  }
  input:focus, textarea:focus { border-color:#3b82f6; }
  textarea { resize:vertical; min-height:64px; font-family:inherit; }

  /* Questions */
  .q-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; margin-bottom:16px; overflow:hidden; }
  .q-header { display:flex; align-items:center; gap:12px; padding:14px 16px; cursor:pointer; user-select:none; }
  .q-num { width:28px; height:28px; border-radius:50%; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; flex-shrink:0; }
  .q-text-preview { flex:1; font-size:.9rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .q-body { padding:16px; border-top:1px solid var(--border); display:none; }
  .q-body.open { display:block; }
  .q-field { margin-bottom:12px; }
  .q-field label { display:block; font-size:.7rem; color:var(--muted); margin-bottom:5px; text-transform:uppercase; letter-spacing:.08em; }
  .media-preview { margin-top:8px; border-radius:8px; overflow:hidden; max-width:320px; }
  .media-preview img, .media-preview video { width:100%; display:block; border-radius:8px; }

  /* Answers */
  .answers-list { margin-top:12px; }
  .answer-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
  .answer-row input[type=text] { flex:1; }
  .answer-row input[type=number] { width:80px; flex-shrink:0; }
  .add-q-form { background:var(--surface); border:2px dashed var(--border); border-radius:14px; padding:20px; margin-bottom:16px; }
  .row { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; }
</style>
</head>
<body>
<header>
  <h1>⚡ BridgeLab</h1>
  <a href="/games" style="font-size:.85rem;">← Giochi</a>
  <span style="flex:1"></span>
  <span id="game-name-display" style="font-size:.9rem; font-weight:700;">{{ $game->name }}</span>
  <button class="btn btn-primary" onclick="document.getElementById('rename-wrap').classList.toggle('hidden')" style="font-size:.75rem; padding:5px 12px;">Rinomina</button>
</header>

<div id="rename-wrap" class="hidden" style="background:var(--surface); border-bottom:1px solid var(--border); padding:12px 28px; display:flex; gap:10px;">
  <input type="text" id="rename-input" value="{{ $game->name }}" style="max-width:300px;">
  <button class="btn btn-primary" onclick="renameGame()">Salva</button>
</div>
<style>.hidden { display:none !important; }</style>

<div class="container">

  <!-- Add question form -->
  <div class="add-q-form" id="add-q-form">
    <div class="section-title">Nuova domanda</div>
    <div class="q-field">
      <label>Testo</label>
      <textarea id="nq-text" placeholder="Testo della domanda..."></textarea>
    </div>
    <div class="q-field">
      <label>Media (immagine o video, opzionale)</label>
      <input type="file" id="nq-media" accept="image/*,video/*" style="color:var(--muted); font-size:.8rem; padding:6px 0;">
    </div>
    <button class="btn btn-primary" onclick="addQuestion()">+ Aggiungi domanda</button>
  </div>

  <!-- Questions list -->
  <div id="questions-container">
    @foreach($game->questions as $q)
    @include('games._question', ['q' => $q])
    @endforeach
    @if($game->questions->isEmpty())
    <div id="empty-q" style="color:var(--muted); text-align:center; padding:32px 0;">Nessuna domanda. Aggiungine una sopra.</div>
    @endif
  </div>

</div>

<script>
const csrf    = document.querySelector('meta[name="csrf-token"]').content;
const gameId  = {{ $game->id }};

// ── Rename ────────────────────────────────────────────────────────────────────
async function renameGame() {
  const name = document.getElementById('rename-input').value.trim();
  if (!name) return;
  await fetch(`/games/${gameId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({ name }),
  });
  document.getElementById('game-name-display').textContent = name;
  document.getElementById('rename-wrap').classList.add('hidden');
}

// ── Add question ──────────────────────────────────────────────────────────────
async function addQuestion() {
  const text  = document.getElementById('nq-text').value.trim();
  if (!text) return;

  const fd = new FormData();
  fd.append('text', text);
  const mediaFile = document.getElementById('nq-media').files[0];
  if (mediaFile) fd.append('media', mediaFile);

  const res  = await fetch(`/games/${gameId}/questions`, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd });
  if (!res.ok) { const err = await res.json().catch(() => ({})); alert(JSON.stringify(err.message || err.errors || 'Errore')); return; }
  const data = await res.json();
  if (!data.ok) return;

  document.getElementById('nq-text').value  = '';
  document.getElementById('nq-media').value = '';
  document.getElementById('empty-q')?.remove();

  document.getElementById('questions-container').insertAdjacentHTML('beforeend', buildQuestionHtml(data.question));
}

// ── Toggle question body ───────────────────────────────────────────────────────
function toggleQ(id) {
  document.getElementById(`qbody-${id}`).classList.toggle('open');
}

// ── Save question ─────────────────────────────────────────────────────────────
async function saveQuestion(id) {
  const text = document.getElementById(`qtext-${id}`).value.trim();
  if (!text) return;

  const fd = new FormData();
  fd.append('text', text);
  const mediaFile = document.getElementById(`qmedia-${id}`)?.files[0];
  if (mediaFile) fd.append('media', mediaFile);
  if (document.getElementById(`qremove-${id}`)?.checked) fd.append('remove_media', '1');

  const res  = await fetch(`/questions/${id}`, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}, body:fd });
  if (!res.ok) { const err = await res.json().catch(() => ({})); alert(JSON.stringify(err.message || err.errors || 'Errore')); return; }
  const data = await res.json();
  if (!data.ok) return;

  document.getElementById(`qpreview-text-${id}`).textContent = data.question.text;
  refreshMediaPreview(id, data.question);
}

function refreshMediaPreview(id, q) {
  const wrap = document.getElementById(`media-preview-${id}`);
  if (!wrap) return;
  if (!q.media_path) { wrap.innerHTML = ''; return; }
  const url = `/storage/${q.media_path}`;
  wrap.innerHTML = q.media_type === 'video'
    ? `<video src="${url}" controls></video>`
    : `<img src="${url}" alt="">`;
}

// ── Delete question ───────────────────────────────────────────────────────────
async function deleteQuestion(id) {
  if (!confirm('Eliminare questa domanda?')) return;
  await fetch(`/questions/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':csrf} });
  document.getElementById(`qcard-${id}`)?.remove();
}

// ── Add answer ────────────────────────────────────────────────────────────────
async function addAnswer(qid) {
  const textEl  = document.getElementById(`at-${qid}`);
  const valueEl = document.getElementById(`av-${qid}`);
  const text  = textEl.value.trim();
  const value = parseFloat(valueEl.value);
  if (!text || isNaN(value)) return;

  let data;
  try {
    const res = await fetch(`/questions/${qid}/answers`, {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body: JSON.stringify({ text, value }),
    });
    const raw = await res.text();
    try { data = JSON.parse(raw); } catch(e) { alert('Risposta non JSON:\n' + raw.slice(0, 300)); return; }
    if (!res.ok) { alert('Errore ' + res.status + ':\n' + JSON.stringify(data.message || data.errors)); return; }
  } catch(e) { alert('Errore fetch: ' + e.message); return; }
  if (!data?.ok) { alert('ok=false: ' + JSON.stringify(data)); return; }

  textEl.value  = '';
  valueEl.value = '1';

  document.getElementById(`answers-list-${qid}`).insertAdjacentHTML('beforeend', buildAnswerHtml(data.answer));
}

// ── Save answer ───────────────────────────────────────────────────────────────
async function saveAnswer(id) {
  const text  = document.getElementById(`atext-${id}`).value.trim();
  const value = parseFloat(document.getElementById(`avalue-${id}`).value);
  if (!text || isNaN(value)) return;
  await fetch(`/answers/${id}`, {
    method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
    body: JSON.stringify({ text, value }),
  });
}

// ── Delete answer ─────────────────────────────────────────────────────────────
async function deleteAnswer(id) {
  await fetch(`/answers/${id}`, { method:'DELETE', headers:{'X-CSRF-TOKEN':csrf} });
  document.getElementById(`arow-${id}`)?.remove();
}

// ── Build HTML helpers ────────────────────────────────────────────────────────
function buildQuestionHtml(q) {
  const num = document.querySelectorAll('.q-card').length + 1;
  const mediaHtml = q.media_path
    ? (q.media_type === 'video'
        ? `<div class="media-preview"><video src="/storage/${q.media_path}" controls></video></div>`
        : `<div class="media-preview"><img src="/storage/${q.media_path}" alt=""></div>`)
    : '';
  const answersHtml = (q.answers || []).map(a => buildAnswerHtml(a)).join('');

  return `
  <div class="q-card" id="qcard-${q.id}">
    <div class="q-header" onclick="toggleQ(${q.id})">
      <div class="q-num">${num}</div>
      <div class="q-text-preview" id="qpreview-text-${q.id}">${esc(q.text)}</div>
      <button class="btn btn-danger" style="font-size:.7rem;padding:4px 10px;" onclick="event.stopPropagation();deleteQuestion(${q.id})">Elimina</button>
    </div>
    <div class="q-body open" id="qbody-${q.id}">
      <div class="q-field">
        <label>Testo domanda</label>
        <textarea id="qtext-${q.id}">${esc(q.text)}</textarea>
      </div>
      <div class="q-field">
        <label>Media</label>
        <input type="file" id="qmedia-${q.id}" accept="image/*,video/*" style="color:var(--muted);font-size:.8rem;padding:6px 0;">
        <label style="margin-top:6px; display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.75rem; color:var(--muted);">
          <input type="checkbox" id="qremove-${q.id}"> Rimuovi media attuale
        </label>
        <div id="media-preview-${q.id}" class="media-preview" style="margin-top:8px;">${mediaHtml}</div>
      </div>
      <button class="btn btn-green" onclick="saveQuestion(${q.id})" style="margin-bottom:16px;">Salva domanda</button>

      <div class="section-title" style="margin-top:8px;">Risposte</div>
      <div class="answers-list" id="answers-list-${q.id}">${answersHtml}</div>
      <div class="row" style="margin-top:10px;">
        <input type="text" id="at-${q.id}" placeholder="Testo risposta..." style="flex:1;">
        <input type="number" id="av-${q.id}" value="1" step="0.25" style="width:80px;">
        <button class="btn btn-primary" onclick="addAnswer(${q.id})">+</button>
      </div>
    </div>
  </div>`;
}

function buildAnswerHtml(a) {
  return `
  <div class="answer-row" id="arow-${a.id}">
    <input type="text" id="atext-${a.id}" value="${esc(a.text)}" onblur="saveAnswer(${a.id})">
    <input type="number" id="avalue-${a.id}" value="${a.value}" step="0.25" style="width:80px;" onblur="saveAnswer(${a.id})">
    <button class="btn btn-danger" style="font-size:.7rem;padding:4px 10px;" onclick="deleteAnswer(${a.id})">✕</button>
  </div>`;
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
