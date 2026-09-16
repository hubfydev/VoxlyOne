/* Painel administrativo: confirmações, filtros que se aplicam sozinhos e mostrar senha. */

// Formulários com data-confirm pedem confirmação antes de enviar (bloquear/desbloquear)
document.addEventListener('submit', (event) => {
  const form = event.target.closest('form[data-confirm]');
  if (form && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
});

// Trocar a ordenação já aplica: no celular, um toque a menos
document.querySelectorAll('[data-autosubmit]').forEach((select) => {
  select.addEventListener('change', () => select.form.requestSubmit());
});

// Mostrar/ocultar senha no login
document.querySelectorAll('[data-toggle-password]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    button.setAttribute('aria-pressed', String(show));
  });
});

// Link direto para uma seção recolhível (ex.: Custos → Preços) já a abre
const target = window.location.hash && document.querySelector(window.location.hash);
if (target instanceof HTMLDetailsElement) {
  target.open = true;
  target.scrollIntoView({ block: 'start' });
}
