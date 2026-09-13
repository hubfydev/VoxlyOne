/*
 * Fila de reprodução da playlist — só a lógica, sem DOM nem áudio.
 *
 * Shuffle e Repeat são independentes:
 *   normal              1 → 2 → 3 → fim
 *   repeat              1 → 2 → 3 → 1 → 2 …
 *   shuffle             permutação completa → fim (ninguém repete na rodada)
 *   shuffle + repeat    permutação → nova permutação …, e a nova rodada não
 *                       começa pela última faixa da anterior (quando há > 1)
 *
 * A fila guarda chaves (ids dos itens), não os itens, para sobreviver a
 * reordenações e remoções sem perder a faixa atual.
 */

export class PlaybackQueue {
  constructor(ids, { shuffle = false, repeat = false, random = Math.random } = {}) {
    this.ids = [...ids];
    this.shuffle = shuffle;
    this.repeat = repeat;
    this.random = random;
    this.order = [];
    this.index = -1; // -1: parada, nada tocando
    this.lastPlayed = null;
    this.rebuild(null);
  }

  get total() {
    return this.ids.length;
  }

  /** Faixa atual, ou null se parada. */
  current() {
    return this.index >= 0 ? this.order[this.index] ?? null : null;
  }

  /** Posição 1-based dentro da rodada atual (0 se parada). */
  position() {
    return this.index + 1;
  }

  /** Começa a tocar — da faixa pedida ou do início (no shuffle, uma aleatória). */
  start(fromId = null) {
    if (!this.total) return null;
    const from = fromId !== null && this.ids.includes(fromId) ? fromId : null;
    this.rebuild(from);
    this.index = 0;
    if (!this.shuffle && from !== null) this.index = this.order.indexOf(from);
    return this.played();
  }

  /** Próxima faixa, ou null quando a playlist termina (sem Repeat). */
  next() {
    if (!this.total) return null;
    if (this.index < 0) return this.start();

    if (this.index < this.order.length - 1) {
      this.index += 1;
      return this.played();
    }

    if (!this.repeat) {
      this.index = -1;
      return null;
    }

    this.newRound();
    return this.played();
  }

  /** Faixa anterior. No começo: com Repeat volta ao fim; sem, fica na primeira. */
  previous() {
    if (!this.total) return null;
    if (this.index < 0) return this.start();

    if (this.index > 0) {
      this.index -= 1;
    } else if (this.repeat) {
      this.index = this.order.length - 1;
    }
    return this.played();
  }

  setRepeat(on) {
    this.repeat = Boolean(on);
  }

  /** Ligar o shuffle começa uma rodada aleatória a partir da faixa atual. */
  setShuffle(on) {
    this.shuffle = Boolean(on);
    const current = this.current();
    this.rebuild(current);
    this.index = current === null ? -1 : this.order.indexOf(current);
  }

  /**
   * Itens mudaram (reordenação, remoção). Mantém a faixa atual; se ela saiu,
   * a próxima chamada a next() segue para a que vinha depois dela.
   */
  setItems(ids) {
    const current = this.current();
    const oldIndex = this.index;
    this.ids = [...ids];

    if (this.shuffle) {
      const kept = this.order.filter((id) => this.ids.includes(id));
      const added = this.shuffled(this.ids.filter((id) => !kept.includes(id)));
      this.order = [...kept, ...added];
    } else {
      this.order = [...this.ids];
    }

    if (current === null) {
      this.index = -1;
    } else if (this.order.includes(current)) {
      this.index = this.order.indexOf(current);
    } else {
      this.index = this.total ? Math.min(oldIndex, this.order.length) - 1 : -1;
    }
  }

  // --- interno --------------------------------------------------------------

  played() {
    const id = this.current();
    if (id !== null) this.lastPlayed = id;
    return id;
  }

  rebuild(first) {
    if (!this.shuffle) {
      this.order = [...this.ids];
      return;
    }
    const rest = this.ids.filter((id) => id !== first);
    this.order = first === null ? this.shuffled(rest) : [first, ...this.shuffled(rest)];
  }

  newRound() {
    this.index = 0;
    if (!this.shuffle) {
      this.order = [...this.ids];
      return;
    }
    this.order = this.shuffled(this.ids);
    // Evita "a mesma frase duas vezes seguidas" na virada da rodada
    if (this.order.length > 1 && this.order[0] === this.lastPlayed) {
      const swap = 1 + Math.floor(this.random() * (this.order.length - 1));
      [this.order[0], this.order[swap]] = [this.order[swap], this.order[0]];
    }
  }

  /** Fisher–Yates: toda permutação é igualmente provável. */
  shuffled(list) {
    const copy = [...list];
    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(this.random() * (i + 1));
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
  }
}
