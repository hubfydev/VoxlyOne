/* Dashboard: toggle PT/EN por card e exclusão com confirmação. */

document.addEventListener('click', async (event) => {
  const toggle = event.target.closest('[data-toggle-lang]');
  if (toggle) {
    togglePhraseLang(toggle);
    return;
  }

  const del = event.target.closest('[data-delete]');
  if (del) {
    await deletePhrase(del);
  }
});

/** Alterna a frase visível entre inglês e português, sem recarregar. */
function togglePhraseLang(button) {
  const card = button.closest('[data-phrase]');
  const en = card.querySelector('[data-en]');
  const pt = card.querySelector('[data-pt]');
  if (!pt) return;

  const showingPt = !pt.hidden;
  pt.hidden = showingPt;
  en.hidden = !showingPt;
  button.textContent = showingPt ? 'Ver PT' : 'Ver EN';
}

async function deletePhrase(button) {
  const id = button.dataset.delete;
  const label = button.dataset.label || 'esta frase';

  if (!confirm(`Excluir "${label}"?\n\nAs tentativas registradas também serão apagadas.`)) {
    return;
  }

  button.disabled = true;

  try {
    const body = new FormData();
    body.append('action', 'delete');
    body.append('id', id);
    body.append('csrf_token', window.CSRF_TOKEN);

    const res = await fetch('/api/phrases.php', { method: 'POST', body });
    const json = await res.json();

    if (!json.success) {
      alert(json.error?.message || 'Não foi possível excluir.');
      button.disabled = false;
      return;
    }

    button.closest('[data-phrase]').remove();
  } catch {
    alert('Sem conexão. Verifique sua internet e tente novamente.');
    button.disabled = false;
  }
}
