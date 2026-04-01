<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>BridgeLab — Giochi</title>
<style>
  :root { --bg:#0b0f1a; --surface:#111827; --border:#1e293b; --text:#e2e8f0; --muted:#64748b; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:"Segoe UI",system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
  header { display:flex; align-items:center; gap:16px; padding:14px 28px; border-bottom:1px solid var(--border); background:var(--surface); }
  header h1 { font-size:1.1rem; font-weight:700; }
  a { color:#60a5fa; text-decoration:none; }
  a:hover { text-decoration:underline; }
  .container { max-width:720px; margin:40px auto; padding:0 24px; }
  .card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:16px; display:flex; align-items:center; gap:16px; }
  .card-info { flex:1; }
  .card-name { font-size:1rem; font-weight:700; }
  .card-meta { font-size:.75rem; color:var(--muted); margin-top:3px; }
  .btn { cursor:pointer; font-size:.8rem; font-weight:700; padding:8px 16px; border-radius:8px; border:1px solid var(--border); background:#0f172a; color:var(--text); transition:background .15s; }
  .btn:hover { background:#1e293b; }
  .btn-primary { border-color:#1e3a6e; color:#93c5fd; }
  .btn-primary:hover { background:#0c1e3a; }
  .btn-danger { border-color:#7f1d1d; color:#fca5a5; }
  .btn-danger:hover { background:#1c0a0a; }
  .new-form { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:24px; display:flex; gap:10px; }
  input[type=text] { flex:1; background:#0f172a; border:1px solid var(--border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:.9rem; outline:none; }
  input[type=text]:focus { border-color:#3b82f6; }
  .empty { color:var(--muted); text-align:center; padding:40px 0; }
</style>
</head>
<body>
<header>
  <h1>⚡ BridgeLab</h1>
  <a href="/" style="font-size:.85rem;">← Host</a>
  <span style="flex:1"></span>
  <a href="/project" target="_blank" style="font-size:.8rem; padding:6px 14px; border:1px solid var(--border); border-radius:8px; color:var(--muted);">Proiezione</a>
</header>

<div class="container">
  <h2 style="font-size:1rem; font-weight:700; margin-bottom:16px; color:var(--muted); text-transform:uppercase; letter-spacing:.1em;">Gestione Giochi</h2>

  <div class="new-form">
    <input type="text" id="new-name" placeholder="Nome nuovo gioco..." maxlength="100">
    <button class="btn btn-primary" onclick="createGame()">+ Crea</button>
  </div>

  <div id="games-list">
    @forelse($games as $game)
    <div class="card" id="game-{{ $game->id }}">
      <div class="card-info">
        <div class="card-name">{{ $game->name }}</div>
        <div class="card-meta">{{ $game->questions_count }} {{ $game->questions_count === 1 ? 'domanda' : 'domande' }}</div>
      </div>
      <a href="/games/{{ $game->id }}" class="btn btn-primary">Modifica</a>
      <button class="btn btn-danger" onclick="deleteGame({{ $game->id }}, this)">Elimina</button>
    </div>
    @empty
    <div class="empty" id="empty-msg">Nessun gioco ancora. Creane uno!</div>
    @endforelse
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function createGame() {
  const input = document.getElementById('new-name');
  const name = input.value.trim();
  if (!name) return;

  const res = await fetch('/games', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({ name }),
  });
  const data = await res.json();
  if (!data.ok) return;

  input.value = '';
  document.getElementById('empty-msg')?.remove();

  const div = document.createElement('div');
  div.className = 'card';
  div.id = `game-${data.game.id}`;
  div.innerHTML = `
    <div class="card-info">
      <div class="card-name">${esc(data.game.name)}</div>
      <div class="card-meta">0 domande</div>
    </div>
    <a href="/games/${data.game.id}" class="btn btn-primary">Modifica</a>
    <button class="btn btn-danger" onclick="deleteGame(${data.game.id}, this)">Elimina</button>
  `;
  document.getElementById('games-list').prepend(div);
}

async function deleteGame(id, btn) {
  if (!confirm('Eliminare il gioco e tutte le sue domande?')) return;
  btn.disabled = true;
  await fetch(`/games/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf } });
  document.getElementById(`game-${id}`)?.remove();
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.getElementById('new-name').addEventListener('keydown', e => { if (e.key === 'Enter') createGame(); });
</script>
</body>
</html>
