/* Formulário de frase: geração de tradução + fonética, envio via fetch e playlists. */

import { openPlaylistPicker } from './playlist_picker.js';

const form = document.getElementById('phrase-form');
const errorBox = document.getElementById('form-error');
const btnSave = document.getElementById('btn-save');
const btnGenerate = document.getElementById('btn-generate');
const genStatus = document.getElementById('gen-status');

const fieldEn = document.getElementById('text_en');
const fieldPt = document.getElementById('text_pt');
const fieldPhonetic = document.getElementById('phonetic_guide');

function showError(message) {
  errorBox.textContent = message;
  errorBox.hidden = false;
}

function clearError() {
  errorBox.hidden = true;
}

/**
 * Traduz o que faltar e preenche a fonética. Disponível SEMPRE — inclusive com
 * PT e EN já preenchidos, que é o caso mais comum de quem cadastra os dois.
 */
btnGenerate.addEventListener('click', async () => {
  clearError();

  if (!fieldEn.value.trim() && !fieldPt.value.trim()) {
    showError('Escreva a frase em pelo menos um idioma antes de gerar.');
    return;
  }

  btnGenerate.disabled = true;
  genStatus.textContent = 'Gerando…';

  try {
    const body = new FormData();
    body.append('text_en', fieldEn.value.trim());
    body.append('text_pt', fieldPt.value.trim());
    body.append('csrf_token', form.csrf_token.value);

    const res = await fetch('/api/translate.php', { method: 'POST', body });
    const json = await res.json();

    if (!json.success) {
      genStatus.textContent = '';
      showError(json.error?.message || 'Não foi possível gerar agora.');
      return;
    }

    if (json.data.text_en) fieldEn.value = json.data.text_en;
    if (json.data.text_pt) fieldPt.value = json.data.text_pt;
    if (json.data.phonetic_br) fieldPhonetic.value = json.data.phonetic_br;

    genStatus.textContent = 'Pronto — revise e ajuste se quiser.';
  } catch {
    genStatus.textContent = '';
    showError('Sem conexão. Verifique sua internet e tente novamente.');
  } finally {
    btnGenerate.disabled = false;
  }
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  clearError();

  if (!fieldEn.value.trim()) {
    showError('A frase em inglês é obrigatória.');
    fieldEn.focus();
    return;
  }

  btnSave.disabled = true;

  try {
    const res = await fetch('/api/phrases.php', {
      method: 'POST',
      body: new FormData(form),
    });
    const json = await res.json();

    if (!json.success) {
      showError(json.error?.message || 'Não foi possível salvar.');
      btnSave.disabled = false;
      return;
    }

    window.location.href = '/dashboard.php';
  } catch {
    showError('Sem conexão. Verifique sua internet e tente novamente.');
    btnSave.disabled = false;
  }
});

// Gravação aprovada da frase: o botão só existe quando há uma (nota > 8)
const btnPlaylist = document.getElementById('btn-playlist');
btnPlaylist?.addEventListener('click', () => {
  openPlaylistPicker({
    recordingId: btnPlaylist.dataset.recording,
    csrf: form.csrf_token.value,
    phraseText: btnPlaylist.dataset.label,
  });
});
