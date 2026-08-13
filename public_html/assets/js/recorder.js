/*
 * Gravação de voz (RF-07).
 *
 * MediaRecorder captura no formato nativo do browser (webm/opus no Chrome e
 * Firefox, mp4/aac no Safari) e a conversão para WAV 16kHz mono acontece UMA
 * vez, ao final. Isso evita ffmpeg no servidor e a armadilha do iOS, onde o
 * AudioContext roda fixo a 48kHz e ignora o sample rate pedido — gravar direto
 * com header "16000" entregaria áudio acelerado à IA.
 */

const MAX_SECONDS = 30;
const MIN_SECONDS = 1;

export class Recorder {
  constructor({ onTick, onLevel } = {}) {
    this.onTick = onTick;
    this.onLevel = onLevel;
    this.reset();
  }

  reset() {
    this.stream = null;
    this.mediaRecorder = null;
    this.chunks = [];
    this.startedAt = 0;
    this.timer = null;
    this.animation = null;
    this.audioContext = null;
  }

  get isRecording() {
    return this.mediaRecorder?.state === 'recording';
  }

  /** Pede o microfone e começa a gravar. Corta sozinho aos 30s. */
  async start() {
    this.stream = await navigator.mediaDevices.getUserMedia({
      audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true },
    });

    this.chunks = [];
    this.mediaRecorder = new MediaRecorder(this.stream, pickMimeType());
    this.mediaRecorder.addEventListener('dataavailable', (event) => {
      if (event.data.size > 0) this.chunks.push(event.data);
    });
    this.mediaRecorder.start();
    this.startedAt = Date.now();

    this.timer = setInterval(() => {
      const elapsed = (Date.now() - this.startedAt) / 1000;
      this.onTick?.(elapsed);
      if (elapsed >= MAX_SECONDS) this.stop();
    }, 100);

    this.startMeter();
  }

  /** Onda animada: AnalyserNode no stream ao vivo, separado da gravação. */
  startMeter() {
    if (!this.onLevel) return;

    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const source = this.audioContext.createMediaStreamSource(this.stream);
    const analyser = this.audioContext.createAnalyser();
    analyser.fftSize = 256;
    source.connect(analyser);

    const data = new Uint8Array(analyser.frequencyBinCount);
    const tick = () => {
      if (!this.isRecording) return;
      analyser.getByteFrequencyData(data);
      const avg = data.reduce((sum, v) => sum + v, 0) / data.length;
      this.onLevel(Math.min(1, avg / 128));
      this.animation = requestAnimationFrame(tick);
    };
    tick();
  }

  /**
   * Para a gravação e devolve { blob, seconds } já em WAV 16kHz mono.
   * Rejeita se a gravação for curta demais.
   */
  stop() {
    return new Promise((resolve, reject) => {
      if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
        reject(new Error('Nenhuma gravação em andamento.'));
        return;
      }

      const seconds = (Date.now() - this.startedAt) / 1000;

      clearInterval(this.timer);
      if (this.animation) cancelAnimationFrame(this.animation);

      this.mediaRecorder.addEventListener('stop', async () => {
        this.stream.getTracks().forEach((track) => track.stop());
        if (this.audioContext) await this.audioContext.close();

        if (seconds < MIN_SECONDS) {
          this.reset();
          reject(new Error('Gravação muito curta. Fale a frase inteira.'));
          return;
        }

        try {
          const raw = new Blob(this.chunks, { type: this.mediaRecorder.mimeType });
          const blob = await toWav16kMono(raw);
          this.reset();
          resolve({ blob, seconds });
        } catch (error) {
          this.reset();
          reject(new Error('Não foi possível processar o áudio: ' + error.message));
        }
      }, { once: true });

      this.mediaRecorder.stop();
    });
  }

  /** Cancela sem produzir áudio (ex.: usuário saiu da tela). */
  cancel() {
    if (this.isRecording) this.mediaRecorder.stop();
    clearInterval(this.timer);
    if (this.animation) cancelAnimationFrame(this.animation);
    this.stream?.getTracks().forEach((track) => track.stop());
    this.audioContext?.close();
    this.reset();
  }
}

function pickMimeType() {
  const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
  const supported = candidates.find(
    (type) => window.MediaRecorder?.isTypeSupported?.(type)
  );
  return supported ? { mimeType: supported } : {};
}

/** Conversão única ao final: decodeAudioData → OfflineAudioContext → WAV. */
async function toWav16kMono(blob) {
  const context = new (window.AudioContext || window.webkitAudioContext)();
  const decoded = await context.decodeAudioData(await blob.arrayBuffer());

  const offline = new (window.OfflineAudioContext || window.webkitOfflineAudioContext)(
    1,
    Math.ceil(decoded.duration * 16000),
    16000
  );
  const source = offline.createBufferSource();
  source.buffer = decoded;
  source.connect(offline.destination);
  source.start();

  const rendered = await offline.startRendering();

  // Liberar: contextos acumulados travam a próxima gravação em celular fraco
  await context.close();

  return encodeWav(rendered);
}

/** PCM → WAV: header RIFF de 44 bytes + amostras Int16. */
function encodeWav(buffer) {
  const samples = buffer.getChannelData(0);
  const view = new DataView(new ArrayBuffer(44 + samples.length * 2));

  const writeString = (offset, text) => {
    for (let i = 0; i < text.length; i++) view.setUint8(offset + i, text.charCodeAt(i));
  };

  writeString(0, 'RIFF');
  view.setUint32(4, 36 + samples.length * 2, true);
  writeString(8, 'WAVE');
  writeString(12, 'fmt ');
  view.setUint32(16, 16, true);   // tamanho do bloco fmt
  view.setUint16(20, 1, true);    // PCM
  view.setUint16(22, 1, true);    // mono
  view.setUint32(24, 16000, true);
  view.setUint32(28, 32000, true); // byte rate: 16000 × 1 × 2
  view.setUint16(32, 2, true);     // block align
  view.setUint16(34, 16, true);    // bits por amostra
  writeString(36, 'data');
  view.setUint32(40, samples.length * 2, true);

  for (let i = 0; i < samples.length; i++) {
    const clamped = Math.max(-1, Math.min(1, samples[i]));
    view.setInt16(44 + i * 2, clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff, true);
  }

  return new Blob([view], { type: 'audio/wav' });
}
