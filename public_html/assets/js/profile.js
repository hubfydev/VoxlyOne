/* Perfil: exclusão de conta com dupla confirmação. */

const btnDelete = document.getElementById('btn-delete');
const errorBox = document.getElementById('delete-error');

btnDelete.addEventListener('click', async () => {
  errorBox.hidden = true;

  if (!confirm('Excluir sua conta e TODOS os seus dados?\n\nEsta ação não pode ser desfeita.')) {
    return;
  }

  // Digitar a palavra evita exclusão por toque acidental no celular
  const typed = prompt('Para confirmar, digite EXCLUIR (em maiúsculas):');
  if (typed !== 'EXCLUIR') {
    if (typed !== null) {
      errorBox.textContent = 'Confirmação incorreta. Nada foi excluído.';
      errorBox.hidden = false;
    }
    return;
  }

  btnDelete.disabled = true;
  btnDelete.textContent = 'Excluindo…';

  try {
    const body = new FormData();
    body.append('confirm', 'EXCLUIR');
    body.append('csrf_token', window.CSRF_TOKEN);

    const res = await fetch('/api/delete_account.php', { method: 'POST', body });
    const json = await res.json();

    if (!json.success) {
      errorBox.textContent = json.error?.message || 'Não foi possível excluir a conta.';
      errorBox.hidden = false;
      btnDelete.disabled = false;
      btnDelete.textContent = 'Excluir minha conta';
      return;
    }

    window.location.href = '/index.php';
  } catch {
    errorBox.textContent = 'Sem conexão. Verifique sua internet e tente novamente.';
    errorBox.hidden = false;
    btnDelete.disabled = false;
    btnDelete.textContent = 'Excluir minha conta';
  }
});
