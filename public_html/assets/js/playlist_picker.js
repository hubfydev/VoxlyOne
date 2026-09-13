/*
 * Modal "Adicionar à playlist" — o mesmo na prática, na lista de frases e na
 * edição. Seleção múltipla, criação de playlist sem sair do fluxo e nenhuma
 * obrigação de escolher: fechar sem salvar deixa tudo como estava.
 *
 * O servidor recebe a lista final marcada (set_memberships), então desmarcar
 * aqui também remove a gravação daquela playlist.
 */

let modal = null;

export function openPlaylistPicker({ recordingId, csrf, phraseText = '', onSaved } = {}) {
  modal?.remove();
  modal = buildModal(phraseText);
  document.body.append(modal);

  const els = {
    list: modal.querySelector('[data-pk-list]'),
    empty: modal.querySelector('[data-pk-empty]'),
    error: modal.querySelector('[data-pk-error]'),
    newToggle: modal.querySelector('[data-pk-new-toggle]'),
    newForm: modal.querySelector('[data-pk-new-form]'),
    newName: modal.querySelector('[data-pk-new-name]'),
    newSubmit: modal.querySelector('[data-pk-new-submit]'),
    cancel: modal.querySelector('[data-pk-cancel]'),
    save: modal.querySelector('[data-pk-save]'),
  };

  const previousFocus = document.activeElement;
  const close = () => {
    document.removeEventListener('keydown', onKey);
    modal?.remove();
    modal = null;
    previousFocus?.focus?.();
  };
  const onKey = (event) => {
    if (event.key === 'Escape') close();
  };
  document.addEventListener('keydown', onKey);

  const showError = (message) => {
    els.error.textContent = message;
    els.error.hidden = message === '';
  };

  const post = async (fields) => {
    const body = new FormData();
    body.append('csrf_token', csrf);
    Object.entries(fields).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach((v) => body.append(`${key}[]`, v));
      else body.append(key, value);
    });
    const res = await fetch('/api/playlists.php', { method: 'POST', body });
    const json = await res.json();
    if (!json.success) throw new Error(json.error?.message || 'Não foi possível concluir.');
    return json.data;
  };

  const addRow = (playlist, checked) => {
    const label = document.createElement('label');
    label.className = 'picker__item';
    label.innerHTML = `
      <input type="checkbox" value="${Number(playlist.id)}">
      <span class="picker__name"></span>
      <span class="picker__count"></span>`;
    label.querySelector('input').checked = checked;
    label.querySelector('.picker__name').textContent = playlist.name;
    const count = Number(playlist.items_count) || 0;
    label.querySelector('.picker__count').textContent = `${count} ${count === 1 ? 'frase' : 'frases'}`;
    els.list.append(label);
    els.empty.hidden = true;
    return label;
  };

  // --- carregar -----------------------------------------------------------

  els.list.setAttribute('aria-busy', 'true');
  post({ action: 'list', recording_id: recordingId })
    .then(({ playlists }) => {
      els.list.removeAttribute('aria-busy');
      els.list.textContent = '';
      playlists.forEach((p) => addRow(p, p.has_recording));
      els.empty.hidden = playlists.length > 0;
      els.save.disabled = false;
      (els.list.querySelector('input') || els.newToggle).focus();
    })
    .catch((error) => {
      els.list.removeAttribute('aria-busy');
      els.list.textContent = '';
      showError(navigator.onLine ? error.message : 'Sem conexão. Tente novamente.');
    });

  // --- nova playlist sem sair do fluxo -----------------------------------

  els.newToggle.addEventListener('click', () => {
    els.newToggle.hidden = true;
    els.newForm.hidden = false;
    els.newName.focus();
  });

  const createPlaylist = async () => {
    const name = els.newName.value.trim();
    if (!name) {
      showError('Dê um nome para a playlist.');
      els.newName.focus();
      return;
    }
    showError('');
    els.newSubmit.disabled = true;
    try {
      const { playlist } = await post({ action: 'create', name });
      // Já entra marcada: é o que o usuário quis ao criar a partir daqui
      addRow(playlist, true).querySelector('input').focus();
      els.newName.value = '';
      els.newForm.hidden = true;
      els.newToggle.hidden = false;
    } catch (error) {
      showError(error.message);
    } finally {
      els.newSubmit.disabled = false;
    }
  };

  els.newSubmit.addEventListener('click', createPlaylist);
  els.newName.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      createPlaylist();
    }
  });

  // --- salvar ou sair ------------------------------------------------------

  els.cancel.addEventListener('click', close);
  modal.addEventListener('click', (event) => {
    if (event.target === modal) close();
  });

  els.save.addEventListener('click', async () => {
    showError('');
    els.save.disabled = true;
    const ids = [...els.list.querySelectorAll('input:checked')].map((input) => input.value);
    try {
      const { playlists } = await post({
        action: 'set_memberships',
        recording_id: recordingId,
        playlist_ids: ids,
      });
      close();
      onSaved?.(playlists.filter((p) => p.has_recording));
    } catch (error) {
      showError(navigator.onLine ? error.message : 'Sem conexão. Tente novamente.');
      els.save.disabled = false;
    }
  });
}

function buildModal(phraseText) {
  const wrapper = document.createElement('div');
  wrapper.className = 'modal';
  wrapper.innerHTML = `
    <div class="modal__box picker" role="dialog" aria-modal="true" aria-labelledby="picker-title">
      <h2 id="picker-title">Adicionar à playlist</h2>
      <p class="picker__phrase"></p>
      <div class="picker__list" data-pk-list>
        <span class="loading__dot"></span><span class="loading__dot"></span><span class="loading__dot"></span>
      </div>
      <p class="picker__empty" data-pk-empty hidden>Você ainda não tem playlists. Crie a primeira abaixo.</p>

      <button class="btn btn--sm btn--ghost btn--block" type="button" data-pk-new-toggle>+ Nova playlist</button>
      <div class="picker__new" data-pk-new-form hidden>
        <label class="sr-only" for="picker-new-name">Nome da playlist</label>
        <input id="picker-new-name" type="text" maxlength="80" placeholder="Nome da playlist" data-pk-new-name>
        <button class="btn btn--sm" type="button" data-pk-new-submit>Criar</button>
      </div>

      <p class="form__error" role="alert" data-pk-error hidden></p>

      <div class="modal__actions">
        <button class="btn btn--ghost" type="button" data-pk-cancel>Agora não</button>
        <button class="btn" type="button" data-pk-save disabled>Salvar</button>
      </div>
    </div>`;
  const phrase = wrapper.querySelector('.picker__phrase');
  phrase.textContent = phraseText ? `"${phraseText}"` : '';
  phrase.hidden = !phraseText;
  return wrapper;
}
