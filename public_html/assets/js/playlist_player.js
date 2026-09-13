/*
 * Player da playlist. Toca SÓ as gravações aprovadas do próprio usuário —
 * nunca a voz sintetizada de referência (player.js não é importado aqui).
 * A ordem vem da PlaybackQueue; este arquivo cuida de áudio, tela e servidor.
 */

import { PlaybackQueue } from './playlist_queue.js';
import { icon } from './icons.js';

const data = window.PLAYLIST;
const $ = (id) => document.getElementById(id);

const els = {
  title: $('pl-title'),
  description: $('pl-description'),
  status: $('pl-status'),
  nowEn: $('pl-now-en'),
  nowPt: $('pl-now-pt'),
  progress: $('pl-progress'),
  prev: $('pl-prev'),
  play: $('pl-play'),
  next: $('pl-next'),
  shuffle: $('pl-shuffle'),
  repeat: $('pl-repeat'),
  error: $('pl-error'),
  audio: $('pl-audio'),
  count: $('pl-count'),
  list: $('pl-items'),
  empty: $('pl-empty'),
  editForm: $('pl-edit-form'),
  editError: $('pl-edit-error'),
  editStatus: $('pl-edit-status'),
  del: $('pl-delete'),
};

let items = data.items;
let finished = false;   // chegou ao fim sem Repeat
let failures = 0;       // erros de áudio seguidos, para não girar em falso
let lastListKey = null;

const queue = new PlaybackQueue(itemIds(), { shuffle: data.shuffle, repeat: data.repeat });

render();

// --- servidor ---------------------------------------------------------------

async function post(fields) {
  const body = new FormData();
  body.append('csrf_token', data.csrf);
  body.append('playlist_id', data.id);
  Object.entries(fields).forEach(([key, value]) => {
    if (Array.isArray(value)) value.forEach((v) => body.append(`${key}[]`, v));
    else body.append(key, value);
  });
  const res = await fetch('/api/playlists.php', { method: 'POST', body });
  const json = await res.json();
  if (!json.success) throw new Error(json.error?.message || 'Não foi possível concluir.');
  return json.data;
}

function showError(message) {
  els.error.textContent = message;
  els.error.hidden = message === '';
}

function connectionMessage(error) {
  return navigator.onLine ? error.message : 'Sem conexão. Verifique sua internet e tente novamente.';
}

// --- reprodução ---------------------------------------------------------------

function itemIds() {
  return items.map((item) => item.item_id);
}

function findItem(id) {
  return items.find((item) => item.item_id === id) || null;
}

function load(id) {
  const item = findItem(id);
  if (!item) return;
  finished = false;
  showError('');
  // Troca de faixa: a URL muda, então o <audio> recarrega do zero
  if (!els.audio.src.endsWith(item.url)) els.audio.src = item.url;
  else els.audio.currentTime = 0;
  els.audio.play().catch(() => {
    /* autoplay bloqueado: fica pausado e o usuário toca no Play */
  });
  render();
}

function stop() {
  els.audio.pause();
  els.audio.removeAttribute('src');
  els.audio.load();
  finished = true;
  render();
}

els.play.addEventListener('click', () => {
  if (!items.length) return;
  if (!els.audio.paused) {
    els.audio.pause();
    return;
  }
  if (queue.current() !== null) {
    // Retoma de onde parou; se a faixa foi trocada enquanto pausado, carrega a nova
    if (els.audio.getAttribute('src')) els.audio.play().catch(() => {});
    else load(queue.current());
    return;
  }
  load(queue.start());
});

els.next.addEventListener('click', () => {
  if (!items.length) return;
  const id = queue.next();
  if (id === null) stop();
  else load(id);
});

els.prev.addEventListener('click', () => {
  if (!items.length) return;
  // Padrão de player: depois de 3s, "anterior" recomeça a faixa atual
  if (queue.current() !== null && els.audio.currentTime > 3) {
    els.audio.currentTime = 0;
    return;
  }
  load(queue.previous());
});

els.audio.addEventListener('ended', () => {
  failures = 0;
  const id = queue.next();
  if (id === null) stop();
  else load(id);
});

els.audio.addEventListener('error', () => {
  if (!els.audio.getAttribute('src')) return;
  failures += 1;
  if (failures >= items.length) {
    showError('Não foi possível tocar as gravações agora. Tente novamente mais tarde.');
    stop();
    return;
  }
  // Uma gravação com problema não trava a playlist: pula para a próxima
  showError('Não foi possível tocar esta gravação. Pulando para a próxima.');
  const id = queue.next();
  if (id === null) stop();
  else setTimeout(() => load(id), 800);
});

['play', 'pause'].forEach((type) => els.audio.addEventListener(type, render));

els.audio.addEventListener('timeupdate', () => {
  const { currentTime, duration } = els.audio;
  const ratio = duration > 0 ? currentTime / duration : 0;
  els.progress.style.transform = `scaleX(${ratio})`;
});

// --- Shuffle e Repeat (independentes, salvos por playlist) -------------------

function bindMode(button, key, apply) {
  button.addEventListener('click', async () => {
    const enabled = button.getAttribute('aria-pressed') !== 'true';
    apply(enabled);
    render();
    try {
      await post({ action: 'settings', [key]: enabled ? '1' : '0' });
    } catch (error) {
      apply(!enabled);
      render();
      showError(connectionMessage(error));
    }
  });
}

bindMode(els.shuffle, 'shuffle', (on) => queue.setShuffle(on));
bindMode(els.repeat, 'repeat', (on) => queue.setRepeat(on));

// --- itens: tocar, reordenar, remover ------------------------------------------

els.list.addEventListener('click', async (event) => {
  const row = event.target.closest('[data-item]');
  if (!row) return;
  const id = Number(row.dataset.item);

  if (event.target.closest('[data-play-item]')) {
    load(queue.start(id));
    return;
  }

  const move = event.target.closest('[data-move]');
  if (move) {
    await reorder(id, Number(move.dataset.move));
    return;
  }

  if (event.target.closest('[data-remove]')) {
    await removeItem(findItem(id));
  }
});

async function reorder(id, delta) {
  const ids = itemIds();
  const from = ids.indexOf(id);
  const to = from + delta;
  if (from < 0 || to < 0 || to >= ids.length) return;

  const previous = items;
  [ids[from], ids[to]] = [ids[to], ids[from]];
  items = ids.map(findItem);
  queue.setItems(itemIds());
  render();
  focusMoveButton(id, delta);

  try {
    items = (await post({ action: 'reorder', item_ids: ids })).items;
    queue.setItems(itemIds());
    render();
    focusMoveButton(id, delta);
  } catch (error) {
    items = previous;
    queue.setItems(itemIds());
    render();
    showError(connectionMessage(error));
  }
}

/** Mantém o foco no botão depois de re-renderizar: dá para subir várias casas seguidas. */
function focusMoveButton(id, delta) {
  const row = els.list.querySelector(`[data-item="${id}"]`);
  const button = row?.querySelector(`[data-move="${delta}"]`);
  (button && !button.disabled ? button : row?.querySelector('[data-move]:not(:disabled)'))?.focus();
}

async function removeItem(item) {
  if (!item) return;
  if (!confirm(`Remover "${item.text_en}" desta playlist?\n\nA frase e a sua gravação continuam salvas.`)) {
    return;
  }
  try {
    const wasCurrent = queue.current() === item.item_id;
    items = (await post({ action: 'remove_item', recording_id: item.recording_id })).items;
    queue.setItems(itemIds());
    if (!items.length) {
      stop();
    } else if (wasCurrent) {
      // A faixa removida não continua tocando: segue para a próxima
      const wasPlaying = !els.audio.paused;
      const id = queue.next();
      if (id === null) {
        stop();
      } else if (wasPlaying) {
        load(id);
      } else {
        els.audio.removeAttribute('src');
        els.audio.load();
      }
    }
    render();
  } catch (error) {
    showError(connectionMessage(error));
  }
}

// --- editar e excluir ------------------------------------------------------------

els.editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  els.editError.hidden = true;
  els.editStatus.textContent = '';

  const name = els.editForm.elements.namedItem('name').value.trim();
  const description = els.editForm.elements.namedItem('description').value.trim();
  if (!name) {
    els.editError.textContent = 'Dê um nome para a playlist.';
    els.editError.hidden = false;
    return;
  }

  try {
    const { playlist } = await post({ action: 'update', name, description });
    data.name = playlist.name;
    els.title.textContent = playlist.name;
    document.title = `${playlist.name} — VoxlyOne`;
    els.description.textContent = playlist.description || '';
    els.description.hidden = !playlist.description;
    els.editStatus.textContent = 'Salvo.';
    updateMediaSession();
  } catch (error) {
    els.editError.textContent = connectionMessage(error);
    els.editError.hidden = false;
  }
});

els.del.addEventListener('click', async () => {
  if (!confirm(`Excluir a playlist "${data.name}"?\n\nSuas frases e gravações não serão apagadas.`)) {
    return;
  }
  els.del.disabled = true;
  try {
    await post({ action: 'delete' });
    window.location.href = '/playlists.php';
  } catch (error) {
    els.del.disabled = false;
    alert(connectionMessage(error));
  }
});

// --- tela ---------------------------------------------------------------------

function render() {
  const total = items.length;
  const currentId = queue.current();
  const shown = findItem(currentId) || items[0] || null;
  const playing = !els.audio.paused;

  els.count.textContent = `${total} frase${total === 1 ? '' : 's'}`;
  els.empty.hidden = total > 0;
  document.querySelector('.pl-player').hidden = total === 0;

  if (currentId !== null) {
    els.status.textContent = `${playing ? 'Tocando' : 'Pausado'} · ${queue.position()} de ${queue.total}`;
  } else if (finished && total) {
    els.status.textContent = `Fim da playlist · ${total} frase${total === 1 ? '' : 's'}`;
  } else {
    els.status.textContent = `Pronta para tocar · ${total} frase${total === 1 ? '' : 's'}`;
  }

  els.nowEn.textContent = shown ? shown.text_en : '';
  els.nowPt.textContent = shown?.text_pt || '';
  els.nowPt.hidden = !shown?.text_pt;
  if (currentId === null) els.progress.style.transform = 'scaleX(0)';

  // O botão já tem os dois ícones; a classe decide qual aparece (e anima a onda da capa)
  els.play.setAttribute('aria-label', playing ? 'Pausar' : 'Tocar');
  document.querySelector('.pl-player').classList.toggle('is-playing', playing);
  els.list.classList.toggle('is-playing', playing);

  setPressed(els.shuffle, queue.shuffle);
  setPressed(els.repeat, queue.repeat);

  // Recriar a lista a cada play/pause tiraria o foco do teclado sem motivo
  const listKey = `${currentId}|${items.map((i) => `${i.item_id}:${i.text_en}`).join(',')}`;
  if (listKey !== lastListKey) {
    lastListKey = listKey;
    renderList(currentId);
  }
  updateMediaSession();
}

function setPressed(button, on) {
  button.classList.toggle('is-active', on);
  button.setAttribute('aria-pressed', String(on));
}

function renderList(currentId) {
  els.list.textContent = '';
  items.forEach((item, index) => {
    const li = document.createElement('li');
    li.className = 'pl-item';
    li.dataset.item = item.item_id;
    if (item.item_id === currentId) {
      li.classList.add('is-current');
      li.setAttribute('aria-current', 'true');
    }

    li.innerHTML = `
      <button class="pl-item__main" type="button" data-play-item>
        <span class="pl-item__num"></span>
        <span class="pl-item__text">
          <span class="pl-item__en"></span>
          <span class="pl-item__pt"></span>
        </span>
      </button>
      <div class="pl-item__foot">
        <span class="pl-item__score" title="Nota da gravação">${icon('star')}<span></span></span>
        <span class="pl-item__time"></span>
        <div class="pl-item__tools">
          <button class="pl-tool" type="button" data-move="-1" aria-label="Mover para cima">${icon('chevron-up')}</button>
          <button class="pl-tool" type="button" data-move="1" aria-label="Mover para baixo">${icon('chevron-down')}</button>
          <button class="pl-tool pl-tool--danger" type="button" data-remove aria-label="Remover da playlist">${icon('trash-2')}</button>
        </div>
      </div>`;

    const num = li.querySelector('.pl-item__num');
    if (item.item_id === currentId) {
      // Faixa atual: equalizador no lugar do número (anima só enquanto toca)
      num.innerHTML = '<span class="pl-eq" aria-hidden="true"><i></i><i></i><i></i></span><span class="sr-only">Faixa atual</span>';
    } else {
      num.textContent = String(index + 1);
    }
    li.querySelector('.pl-item__en').textContent = item.text_en;
    const pt = li.querySelector('.pl-item__pt');
    pt.textContent = item.text_pt || '';
    pt.hidden = !item.text_pt;
    li.querySelector('.pl-item__score span').textContent = Number(item.score).toFixed(1).replace('.', ',');
    const time = li.querySelector('.pl-item__time');
    const seconds = Number(item.audio_seconds) || 0;
    time.textContent = seconds > 0 ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : '';
    time.hidden = seconds <= 0;
    li.querySelector('[data-move="-1"]').disabled = index === 0;
    li.querySelector('[data-move="1"]').disabled = index === items.length - 1;

    els.list.append(li);
  });
}

/** Controles na tela de bloqueio e nos fones: ouvir a playlist com o celular no bolso. */
function updateMediaSession() {
  if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined') return;
  const item = findItem(queue.current());
  if (!item) return;
  navigator.mediaSession.metadata = new MediaMetadata({
    title: item.text_en,
    artist: 'Minha voz · VoxlyOne',
    album: data.name,
  });
}

if ('mediaSession' in navigator) {
  const handlers = {
    play: () => els.play.click(),
    pause: () => els.audio.pause(),
    previoustrack: () => els.prev.click(),
    nexttrack: () => els.next.click(),
  };
  Object.entries(handlers).forEach(([action, handler]) => {
    try {
      navigator.mediaSession.setActionHandler(action, handler);
    } catch {
      /* ação não suportada neste navegador */
    }
  });
}
