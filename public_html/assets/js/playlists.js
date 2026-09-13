/* Lista de playlists: criar nova sem sair da página. */

const toggle = document.getElementById('btn-new-playlist');
const form = document.getElementById('new-playlist');
const errorBox = document.getElementById('new-playlist-error');
// form.name seria o atributo do próprio <form>, não o campo
const nameField = form.elements.namedItem('name');

toggle.addEventListener('click', () => {
  form.hidden = !form.hidden;
  toggle.setAttribute('aria-expanded', String(!form.hidden));
  if (!form.hidden) nameField.focus();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.hidden = true;

  if (!nameField.value.trim()) {
    errorBox.textContent = 'Dê um nome para a playlist.';
    errorBox.hidden = false;
    nameField.focus();
    return;
  }

  const button = form.querySelector('[type="submit"]');
  button.disabled = true;

  try {
    const body = new FormData(form);
    body.append('action', 'create');
    body.append('csrf_token', window.CSRF_TOKEN);

    const res = await fetch('/api/playlists.php', { method: 'POST', body });
    const json = await res.json();

    if (!json.success) {
      errorBox.textContent = json.error?.message || 'Não foi possível criar.';
      errorBox.hidden = false;
      button.disabled = false;
      return;
    }

    window.location.href = `/playlist.php?id=${json.data.playlist.id}`;
  } catch {
    errorBox.textContent = 'Sem conexão. Verifique sua internet e tente novamente.';
    errorBox.hidden = false;
    button.disabled = false;
  }
});
