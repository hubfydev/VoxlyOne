/* Reprodução da frase em inglês com a Web Speech API (RF-06). Custo zero.
   Voz feminina ou masculina à escolha do usuário. */

const RATES = { slow: 0.6, normal: 0.9, fast: 1.2 };

const VOICE_STORAGE_KEY = 'voxly.voice';

/*
 * A Web Speech API não informa o gênero da voz, então ele é deduzido do nome.
 * As listas cobrem as vozes en-US mais comuns de Chrome, Edge, macOS/iOS e
 * Android (os códigos x-xxx do Google TTS). Sem nenhuma voz reconhecida, cai na
 * melhor voz en-US disponível com o tom ajustado — aproximação, mas nunca mudo.
 */
const FEMALE_VOICES = [
  'Google US English', 'Samantha', 'Microsoft Aria', 'Microsoft Jenny', 'Microsoft Zira',
  'Microsoft Michelle', 'Microsoft Ana', 'Allison', 'Ava', 'Susan', 'Victoria', 'Zoe',
  'Nicky', 'Joanna', 'Salli', 'Kendra', 'Kimberly', 'Ivy', 'Karen', 'Moira', 'Tessa',
  'Serena', 'Fiona', 'Kate', 'Emma', 'Libby', 'Sonia', 'Martha',
];
const MALE_VOICES = [
  'Alex', 'Aaron', 'Fred', 'Tom', 'Evan', 'Nathan', 'Microsoft Guy', 'Microsoft David',
  'Microsoft Mark', 'Microsoft Christopher', 'Microsoft Eric', 'Microsoft Roger',
  'Microsoft Andrew', 'Microsoft Brian', 'Microsoft Steffan', 'Google UK English Male',
  'Daniel', 'Oliver', 'Arthur', 'Rishi', 'Gordon', 'Lee', 'Ralph', 'Bruce', 'Junior',
  'Albert', 'Matthew', 'Joey', 'Justin', 'Kevin', 'Ryan', 'Thomas',
];
const ANDROID_FEMALE = /x-(sfg|iob|iog|tpc|tpf)\b/i;
const ANDROID_MALE = /x-(iol|iom|tpd)\b/i;

const cachedVoices = { female: null, male: null, any: null };

/** Gênero reconhecido pelo nome da voz: 'female', 'male' ou null. */
function voiceGender(voice) {
  const name = `${voice.name} ${voice.voiceURI}`;
  if (/\bfemale\b/i.test(name) || ANDROID_FEMALE.test(name)) return 'female';
  if (/\bmale\b/i.test(name) || ANDROID_MALE.test(name)) return 'male';
  if (FEMALE_VOICES.some((token) => voice.name.startsWith(token))) return 'female';
  if (MALE_VOICES.some((token) => voice.name.startsWith(token))) return 'male';
  return null;
}

/**
 * Melhor voz en-US disponível. No Chrome a lista chega assíncrona, por isso
 * getVoices() é consultado a cada chamada até vir preenchida.
 */
function pickVoice() {
  if (cachedVoices.any) return cachedVoices.any;

  const voices = speechSynthesis.getVoices();
  if (!voices.length) return null;

  const preferred = ['Google US English', 'Samantha', 'Alex'];
  cachedVoices.any =
    voices.find((v) => preferred.includes(v.name)) ||
    voices.find((v) => v.lang === 'en-US') ||
    voices.find((v) => v.lang.startsWith('en')) ||
    null;

  return cachedVoices.any;
}

/** Melhor voz en-US do gênero pedido; en-* de outro país só se não houver en-US. */
function pickGenderVoice(gender) {
  if (cachedVoices[gender]) return cachedVoices[gender];

  const voices = speechSynthesis.getVoices();
  if (!voices.length) return null;

  const english = (v) => v.lang.replace('_', '-').toLowerCase();
  const matching = voices.filter((v) => english(v).startsWith('en') && voiceGender(v) === gender);
  const list = gender === 'female' ? FEMALE_VOICES : MALE_VOICES;
  const rank = (v) => {
    const index = list.findIndex((token) => v.name.startsWith(token));
    return (english(v) === 'en-us' ? 0 : 1000) + (index === -1 ? 500 : index);
  };

  cachedVoices[gender] = matching.sort((a, b) => rank(a) - rank(b))[0] || null;
  return cachedVoices[gender];
}

if ('speechSynthesis' in window) {
  speechSynthesis.getVoices();
  speechSynthesis.addEventListener('voiceschanged', () => {
    cachedVoices.female = null;
    cachedVoices.male = null;
    cachedVoices.any = null;
    pickVoice();
  });
}

/** Voz escolhida pelo usuário ('female' | 'male'). Lembrada entre visitas. */
export function getVoicePreference() {
  try {
    return localStorage.getItem(VOICE_STORAGE_KEY) === 'male' ? 'male' : 'female';
  } catch {
    return 'female';
  }
}

export function setVoicePreference(gender) {
  try {
    localStorage.setItem(VOICE_STORAGE_KEY, gender === 'male' ? 'male' : 'female');
  } catch {
    /* modo privado: vale só nesta página */
  }
}

/**
 * Liga um grupo de botões [data-voice] à preferência de voz. Independente dos
 * botões de velocidade — os dois controles não se conhecem.
 */
export function bindVoiceButtons(buttons, onChange) {
  let current = getVoicePreference();

  const highlight = () => {
    buttons.forEach((button) => {
      const active = button.dataset.voice === current;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      current = button.dataset.voice === 'male' ? 'male' : 'female';
      setVoicePreference(current);
      highlight();
      onChange?.(current);
    });
  });

  highlight();
  return () => current;
}

/**
 * Fala a frase e avisa quando o play começou.
 *
 * O onEnd é entregue por evento OU por timer de fallback — no Safari iOS o
 * evento 'end' frequentemente não dispara, e sem o fallback o botão GRAVAR
 * nunca habilitaria, travando o app justamente no público-alvo mobile (RF-06).
 */
export function speak(text, speed, { onStart, onEnd, voice: gender } = {}) {
  if (!('speechSynthesis' in window)) {
    onStart?.();
    onEnd?.();
    return;
  }

  speechSynthesis.cancel();

  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = 'en-US';
  utterance.rate = RATES[speed] ?? RATES.normal;

  // Sem gênero pedido, o comportamento é exatamente o de antes
  const genderVoice = gender ? pickGenderVoice(gender) : null;
  const voice = genderVoice || pickVoice();
  if (voice) utterance.voice = voice;

  // Nenhuma voz do gênero neste aparelho: aproxima pelo tom da voz padrão
  if (gender && !genderVoice && (!voice || voiceGender(voice) !== gender)) {
    utterance.pitch = gender === 'male' ? 0.7 : 1.25;
  }

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
