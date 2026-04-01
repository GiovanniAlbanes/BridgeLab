<div class="q-card" id="qcard-{{ $q->id }}">
  <div class="q-header" onclick="toggleQ({{ $q->id }})">
    <div class="q-num">{{ $loop->iteration }}</div>
    <div class="q-text-preview" id="qpreview-text-{{ $q->id }}">{{ $q->text }}</div>
    <button class="btn btn-danger" style="font-size:.7rem;padding:4px 10px;" onclick="event.stopPropagation();deleteQuestion({{ $q->id }})">Elimina</button>
  </div>
  <div class="q-body" id="qbody-{{ $q->id }}">
    <div class="q-field">
      <label>Testo domanda</label>
      <textarea id="qtext-{{ $q->id }}">{{ $q->text }}</textarea>
    </div>
    <div class="q-field">
      <label>Media</label>
      <input type="file" id="qmedia-{{ $q->id }}" accept="image/*,video/*" style="color:var(--muted);font-size:.8rem;padding:6px 0;">
      <label style="margin-top:6px; display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.75rem; color:var(--muted);">
        <input type="checkbox" id="qremove-{{ $q->id }}"> Rimuovi media attuale
      </label>
      <div id="media-preview-{{ $q->id }}" class="media-preview" style="margin-top:8px;">
        @if($q->media_path)
          @if($q->media_type === 'video')
            <video src="{{ Storage::disk('public')->url($q->media_path) }}" controls></video>
          @else
            <img src="{{ Storage::disk('public')->url($q->media_path) }}" alt="">
          @endif
        @endif
      </div>
    </div>
    <button class="btn btn-green" onclick="saveQuestion({{ $q->id }})" style="margin-bottom:16px;">Salva domanda</button>

    <div class="section-title" style="margin-top:8px;">Risposte</div>
    <div class="answers-list" id="answers-list-{{ $q->id }}">
      @foreach($q->answers as $a)
      <div class="answer-row" id="arow-{{ $a->id }}">
        <input type="text" id="atext-{{ $a->id }}" value="{{ $a->text }}" onblur="saveAnswer({{ $a->id }})">
        <input type="number" id="avalue-{{ $a->id }}" value="{{ $a->value }}" step="0.25" style="width:80px;" onblur="saveAnswer({{ $a->id }})">
        <button class="btn btn-danger" style="font-size:.7rem;padding:4px 10px;" onclick="deleteAnswer({{ $a->id }})">✕</button>
      </div>
      @endforeach
    </div>
    <div class="row" style="margin-top:10px;">
      <input type="text" id="at-{{ $q->id }}" placeholder="Testo risposta..." style="flex:1;">
      <input type="number" id="av-{{ $q->id }}" value="1" step="0.25" style="width:80px;">
      <button class="btn btn-primary" onclick="addAnswer({{ $q->id }})">+</button>
    </div>
  </div>
</div>
