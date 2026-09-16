/* Orquestra a tela de prática: play → gravar → analisar → feedback (seção 7). */

import { speak, bindVoiceButtons, preloadReference } from './player.js';
import { Recorder } from './recorder.js';
import { celebrate } from './confetti.js';
import { openPlaylistPicker } from './playlist_picker.js';
import { icon } from './icons.js';

const data = window.PRACTICE;
const $ = (id) => document.getElementById(id);

const els = {
  speedButtons: document.querySelectorAll('[data-speed]'),
  voiceButtons: document.querySelectorAll('[data-voice]'),
  play: $('btn-play'),
  record: $('btn-record'),
  timer: $('rec-timer'),
  wave: $('rec-wave'),
  preview: $('preview'),
  previewBox: $('preview-box'),
  again: $('btn-again'),
  analyze: $('btn-analyze'),
  loading: $('loading'),
  feedback: $('feedback'),
  consent: $('consent-modal'),
  consentAccept: $('consent-accept'),
  consentCancel: $('consent-cancel'),
  error: $('practice-error'),
};

let speed = sessionStorage.getItem('voxly.speed') || 'normal';
let hasPlayed = false;
let wavBlob = null;
let consented = data.consented;

const recorder = new Recorder({
  onTick: (seconds) => {
    els.timer.textContent = `${seconds.toFixed(1)}s`;
  },
  onLevel: (level) => {
    els.wave.style.transform = `scaleY(${0.2 + level * 0.8})`;
  },
});

highlightSpeed();
// Trocar de voz já prepara o áudio dela: o toque em "Ouvir" toca sem esperar
const currentVoice = bindVoiceButtons(els.voiceButtons, (gender) => preloadReference(data.tts?.[gender]));
preloadReference(data.tts?.[currentVoice()]);

// --- velocidade -------------------------------------------------------------

els.speedButtons.forEach((button) => {
  button.addEventListener('click', () => {
    speed = button.dataset.speed;
    sessionStorage.setItem('voxly.speed', speed);
    highlightSpeed();
  });
});

function highlightSpeed() {
  els.speedButtons.forEach((button) => {
    button.classList.toggle('is-active', button.dataset.speed === speed);
    button.setAttribute('aria-pressed', String(button.dataset.speed === speed));
  });
}

// --- play -------------------------------------------------------------------

els.play.addEventListener('click', () => {
  showError('');
  els.play.disabled = true;

  speak(data.textEn, speed, {
    voice: currentVoice(),
    audioUrl: data.tts?.[currentVoice()],
    // Habilita GRAVAR já no início do play: no Safari iOS o evento 'end' é
    // pouco confiável e travaria o app se fosse a única condição.
    onStart: () => {
      hasPlayed = true;
      els.record.disabled = false;
    },
    onEnd: () => {
      hasPlayed = true;
      els.record.disabled = false;
      els.play.disabled = false;
    },
  });
});

// --- gravação ---------------------------------------------------------------

els.record.addEventListener('click', async () => {
  if (recorder.isRecording) {
    await finishRecording();
    return;
  }

  if (!consented) {
    els.consent.hidden = false;
    return;
  }

  await beginRecording();
});

async function beginRecording() {
  showError('');
  try {
    await recorder.start();
    setRecordLabel(true);
    els.record.classList.add('is-recording');
    els.previewBox.hidden = true;
    els.feedback.hidden = true;
  } catch {
    showError(
      'Não conseguimos acessar o microfone. Verifique a permissão do navegador ' +
      'e, no celular, se o site tem acesso ao microfone nas configurações.'
    );
  }
}

async function finishRecording() {
  setRecordLabel(false);
  els.record.classList.remove('is-recording');
  els.wave.style.transform = 'scaleY(0.2)';

  try {
    const { blob } = await recorder.stop();
    wavBlob = blob;
    els.preview.src = URL.createObjectURL(blob);
    els.previewBox.hidden = false;
  } catch (error) {
    showError(error.message);
  }
}

/** Troca ícone + rótulo do botão redondo (Gravar ⇄ Parar) sem perder o ícone. */
function setRecordLabel(recording) {
  els.record.innerHTML = recording
    ? `${icon('square', 'rec-btn__icon')}<span class="rec-btn__label">Parar</span>`
    : `${icon('mic', 'rec-btn__icon')}<span class="rec-btn__label">Gravar</span>`;
}

els.again.addEventListener('click', () => {
  wavBlob = null;
  els.previewBox.hidden = true;
  els.feedback.hidden = true;
  beginRecording();
});

// --- consentimento ----------------------------------------------------------

els.consentAccept.addEventListener('click', () => {
  els.consent.hidden = true;
  consented = true;

  // getUserMedia precisa ser chamado AINDA dentro do gesto do usuário: no
  // Safari iOS, um await antes dele faz o browser recusar o microfone.
  // Por isso a gravação começa já e o aceite é registrado em paralelo.
  beginRecording();

  const body = new FormData();
  body.append('csrf_token', data.csrf);

  fetch('/api/consent.php', { method: 'POST', body })
    .then((res) => res.json())
    .then((json) => {
      if (!json.success) {
        consented = false;
        showError('Não foi possível registrar seu aceite. Tente gravar novamente.');
      }
    })
    .catch(() => {
      consented = false;
      showError('Sem conexão — seu aceite não foi registrado.');
    });
});

els.consentCancel.addEventListener('click', () => {
  els.consent.hidden = true;
});

// --- análise ----------------------------------------------------------------

els.analyze.addEventListener('click', async () => {
  if (!wavBlob) return;

  showError('');
  els.previewBox.hidden = true;
  els.loading.hidden = false;

  const body = new FormData();
  body.append('audio', wavBlob, 'recording.wav');
  body.append('phrase_id', data.phraseId);
  body.append('csrf_token', data.csrf);

  try {
    const res = await fetch('/api/analyze.php', { method: 'POST', body });
    const json = await res.json();
    els.loading.hidden = true;

    if (!json.success) {
      // A gravação continua no browser: dá para tentar de novo sem regravar
      els.previewBox.hidden = false;
      showError(json.error?.message || 'Não foi possível analisar agora.');
      return;
    }

    renderFeedback(json.data);
  } catch {
    els.loading.hidden = true;
    els.previewBox.hidden = false;
    showError(
      navigator.onLine
        ? 'A análise falhou. Sua gravação foi mantida — tente novamente.'
        : 'Você está sem internet. Sua gravação foi mantida.'
    );
  }
});

// --- feedback ---------------------------------------------------------------

function renderFeedback(result) {
  const fb = result.feedback;
  const score = Number(result.score);

  let verdict = 'Continue tentando';
  let tone = 'low';
  if (result.is_mastered) {
    verdict = 'Dominada!';
    tone = 'mastered';
  } else if (result.advanced) {
    verdict = 'Aprovada!';
    tone = 'approved';
  }

  const errors = Array.isArray(fb.errors) ? fb.errors : [];
  const ringIcon = { mastered: 'crown', approved: 'circle-check', low: 'target' }[tone];
  const subline = {
    mastered: 'Pronúncia perfeita — o selo de ouro é seu.',
    approved: 'Você já pode avançar. Com 10, a frase ganha o selo de ouro.',
    low: 'Com 8 você avança. Ouça de novo e siga as dicas abaixo.',
  }[tone];
  // Nota limitada a 0–10 só para desenhar o anel
  const ringScore = Math.max(0, Math.min(10, Number.isFinite(score) ? score : 0));

  els.feedback.innerHTML = `
    <div class="score score--${tone}" style="--score: ${ringScore}">
      <div class="score__ring">
        <span class="score__badge">${icon(ringIcon)}</span>
        <span class="score__value">${score.toFixed(1).replace('.', ',')}</span>
        <span class="score__max">de 10</span>
      </div>
      <span class="score__verdict">${escapeHtml(verdict)}</span>
      <p class="score__sub">${escapeHtml(subline)}</p>
    </div>

    ${fb.heard ? `
      <div class="fb-heard">
        <span class="fb-label">${icon('ear')}Ouvimos</span>
        <p lang="en">“${escapeHtml(fb.heard)}”</p>
      </div>` : ''}

    ${fb.positives ? `<p class="fb-positive">${icon('circle-check')}<span>${escapeHtml(fb.positives)}</span></p>` : ''}

    ${errors.length ? `
      <div class="fb-section">
        <span class="fb-label">${icon('target')}Para ajustar</span>
        <ul class="fb-errors">
          ${errors.map((e) => `
            <li>
              <div class="fb-errors__head">
                <strong lang="en">${escapeHtml(e.word ?? '')}</strong>
                <span class="fb-errors__said">você disse “${escapeHtml(e.said ?? '')}”</span>
              </div>
              <span class="fb-errors__tip">${icon('arrow-right')}<span>${escapeHtml(e.tip ?? '')}</span></span>
            </li>`).join('')}
        </ul>
      </div>` : ''}

    ${fb.naturalness || fb.speed_feedback ? `
      <div class="fb-notes">
        ${fb.naturalness ? `<p class="fb-note">${icon('audio-lines')}<span>${escapeHtml(fb.naturalness)}</span></p>` : ''}
        ${fb.speed_feedback ? `<p class="fb-note">${icon('gauge')}<span>${escapeHtml(fb.speed_feedback)}</span></p>` : ''}
      </div>` : ''}

    ${fb.main_tip ? `
      <div class="fb-tip">
        <span class="fb-tip__icon">${icon('lightbulb')}</span>
        <div>
          <span class="fb-tip__label">Dica principal</span>
          <p>${escapeHtml(fb.main_tip)}</p>
        </div>
      </div>` : ''}

    ${buildPlaylistBox(result)}

    <div class="fb-actions">${buildActions(result)}</div>
  `;

  els.feedback.hidden = false;
  els.feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  if (result.is_mastered) celebrate();

  wireActions(result);
  wirePlaylist(result);
}

/**
 * Playlists (nota > 8): a gravação acabou de ser guardada e pode entrar em
 * playlists agora ou depois. Com nota ≤ 8, só avisa que a aprovada anterior
 * continua valendo. Não mexe nos botões de ação da prática.
 */
function buildPlaylistBox(result) {
  if (result.playlist_eligible && result.recording) {
    const note = result.recording.replaced
      ? 'Esta gravação substituiu a anterior em todas as suas playlists.'
      : 'Sua gravação foi aprovada e pode entrar nas suas playlists.';
    return `
      <div class="fb-playlist">
        <span class="fb-playlist__icon">${icon('headphones')}</span>
        <p>${escapeHtml(note)}</p>
        <button class="btn btn--sm btn--soft" type="button" id="fb-playlist">${icon('list-plus')}<span class="fb-playlist__label">Adicionar à playlist</span></button>
      </div>`;
  }
  if (result.recording) {
    return `
      <div class="fb-playlist fb-playlist--muted">
        <span class="fb-playlist__icon">${icon('headphones')}</span>
        <p>Sua gravação aprovada anterior continua nas playlists.</p>
      </div>`;
  }
  return '';
}

function wirePlaylist(result) {
  $('fb-playlist')?.addEventListener('click', () => {
    openPlaylistPicker({
      recordingId: result.recording.id,
      csrf: data.csrf,
      phraseText: data.textEn,
      onSaved: (playlists) => {
        const button = $('fb-playlist');
        if (!button) return;
        // Troca só o rótulo: o ícone (e o texto exato do botão) continuam
        const label = button.querySelector('.fb-playlist__label') || button;
        label.textContent = playlists.length
          ? `Em ${playlists.length} playlist${playlists.length > 1 ? 's' : ''} ✓`
          : 'Adicionar à playlist';
        button.classList.toggle('is-saved', playlists.length > 0);
      },
    });
  });
}

/**
 * Avançar ≠ dominar: com nota >= 8 já dá para seguir, e a nota 10 vira o selo.
 * O 'Pular por agora' aparece da 8ª tentativa em diante para quem ainda não
 * chegou aos 8 — a válvula anti-frustração do RF-10.
 */
function buildActions(result) {
  const buttons = [];

  if (result.is_mastered) {
    buttons.push(nextButton('Próxima frase'));
  } else if (result.advanced) {
    buttons.push(nextButton('Próxima frase'));
    buttons.push(`<button class="btn btn--ghost" type="button" id="fb-retry">${icon('target')}Tentar o 10</button>`);
  } else {
    buttons.push(`<button class="btn" type="button" id="fb-retry">${icon('rotate-ccw')}Tentar novamente</button>`);
    if (result.attempts_count >= 8) {
      buttons.push(`<button class="btn btn--ghost" type="button" id="fb-skip">${icon('skip-forward')}Pular por agora</button>`);
    }
  }

  return buttons.join('');
}

function nextButton(label) {
  return `<button class="btn" type="button" id="fb-next">${label}${icon('arrow-right')}</button>`;
}

function wireActions(result) {
  $('fb-retry')?.addEventListener('click', () => {
    els.feedback.hidden = true;
    wavBlob = null;
    els.record.disabled = !hasPlayed;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  $('fb-next')?.addEventListener('click', () => {
    window.location.href = '/practice.php';
  });

  $('fb-skip')?.addEventListener('click', async () => {
    const body = new FormData();
    body.append('action', 'skip');
    body.append('id', data.phraseId);
    body.append('csrf_token', data.csrf);

    try {
      await fetch('/api/phrases.php', { method: 'POST', body });
    } catch {
      /* segue para a próxima de qualquer forma */
    }
    window.location.href = '/practice.php';
  });
}

// --- utilidades -------------------------------------------------------------

function showError(message) {
  els.error.textContent = message;
  els.error.hidden = message === '';
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = String(value ?? '');
  return div.innerHTML;
}
