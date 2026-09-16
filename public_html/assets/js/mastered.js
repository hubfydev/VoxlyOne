/* Conquistas: play por card, na velocidade escolhida no topo. */

import { speak, bindVoiceButtons } from './player.js';

const speedButtons = document.querySelectorAll('[data-speed]');
const currentVoice = bindVoiceButtons(document.querySelectorAll('[data-voice]'));
let speed = sessionStorage.getItem('voxly.speed') || 'normal';

// A velocidade é a mesma da tela de prática (sessionStorage compartilhado)
function highlightSpeed() {
  speedButtons.forEach((button) => {
    const active = button.dataset.speed === speed;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', String(active));
  });
}

speedButtons.forEach((button) => {
  button.addEventListener('click', () => {
    speed = button.dataset.speed;
    sessionStorage.setItem('voxly.speed', speed);
    highlightSpeed();
  });
});

highlightSpeed();

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-play]');
  if (!button) return;

  button.disabled = true;
  const voice = currentVoice();
  speak(button.dataset.play, speed, {
    voice,
    // Voz neural por frase e gênero (data-tts-female / data-tts-male)
    audioUrl: voice === 'male' ? button.dataset.ttsMale : button.dataset.ttsFemale,
    onEnd: () => { button.disabled = false; },
  });
});
