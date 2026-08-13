/* Reprodução da frase em inglês com a Web Speech API (RF-06). Custo zero. */

const RATES = { slow: 0.6, normal: 0.9, fast: 1.2 };

let cachedVoice = null;

/**
 * Melhor voz en-US disponível. No Chrome a lista chega assíncrona, por isso
 * getVoices() é consultado a cada chamada até vir preenchida.
 */
function pickVoice() {
  if (cachedVoice) return cachedVoice;

  const voices = speechSynthesis.getVoices();
  if (!voices.length) return null;

  const preferred = ['Google US English', 'Samantha', 'Alex'];
  cachedVoice =
    voices.find((v) => preferred.includes(v.name)) ||
    voices.find((v) => v.lang === 'en-US') ||
    voices.find((v) => v.lang.startsWith('en')) ||
    null;

  return cachedVoice;
}

if ('speechSynthesis' in window) {
  speechSynthesis.getVoices();
  speechSynthesis.addEventListener('voiceschanged', () => {
    cachedVoice = null;
    pickVoice();
  });
}

/**
 * Fala a frase e avisa quando o play começou.
 *
 * O onEnd é entregue por evento OU por timer de fallback — no Safari iOS o
 * evento 'end' frequentemente não dispara, e sem o fallback o botão GRAVAR
 * nunca habilitaria, travando o app justamente no público-alvo mobile (RF-06).
 */
export function speak(text, speed, { onStart, onEnd } = {}) {
  if (!('speechSynthesis' in window)) {
    onStart?.();
    onEnd?.();
    return;
  }

  speechSynthesis.cancel();

  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = 'en-US';
  utterance.rate = RATES[speed] ?? RATES.normal;

  const voice = pickVoice();
  if (voice) utterance.voice = voice;

  let finished = false;
  const finish = () => {
    if (finished) return;
    finished = true;
    onEnd?.();
  };

  utterance.addEventListener('start', () => onStart?.());
  utterance.addEventListener('end', finish);
  utterance.addEventListener('error', finish);

  // Fallback: nº de palavras × 400ms ajustado pela velocidade, mínimo 2s
  const words = text.trim().split(/\s+/).length;
  const estimate = Math.max(2000, (words * 400) / (RATES[speed] ?? 1));
  setTimeout(finish, estimate);

  speechSynthesis.speak(utterance);
}
