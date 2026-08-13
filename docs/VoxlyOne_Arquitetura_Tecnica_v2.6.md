# VoxlyOne — Arquitetura Técnica & Requisitos

**Plataforma de Aprendizado de Inglês — MVP**

| | |
|---|---|
| **Versão** | 2.6.0 (pivô do VOXLY v1.1 + 5 rodadas de revisão + spike do Dia 1 executado) |
| **Data** | Agosto 2026 |
| **Stack** | PHP 8.2+ puro + MySQL + JS Vanilla |
| **Ambiente** | Hostinger — Hospedagem Compartilhada (LiteSpeed) |
| **IDE** | VS Code + Claude Code |
| **Domínio** | voxly.hubfy.app |

---

## O que mudou da v2.5 para a v2.6

Esta versão não traz decisões novas de projeto: traz **medições**. O spike do Dia 1
foi executado em 11/08/2026 e substituiu estimativas por números reais.

| Item | v2.5 (estimado) | v2.6 (medido) |
|---|---|---|
| Risco do wall-clock do LiteSpeed | Em aberto, podia inviabilizar o projeto | **Resolvido** — 35s ociosos sobreviveram |
| Modelo de áudio | `gpt-4o-audio-preview` | **`gpt-audio-1.5`** (o antigo foi aposentado, dá 404) |
| Latência da análise | 8–15s | **~2,3s** |
| Custo por análise | US$ 0,03 | **~US$ 0,004** |
| Limites do php.ini | A configurar (8M/10M/256M) | Plano já entrega 256M/256M/512M — só verificar |
| Plano B (streaming) e VPS | Contingências prováveis | **Desnecessários** |

Consequência prática: o gargalo em torno do qual boa parte do documento foi
dimensionada é muito menor do que se supunha, e o orçamento tem folga real.

---

## 1. Visão Geral e Decisão de Arquitetura

### 1.1 O Produto

O VoxlyOne é a versão MVP da plataforma Voxly: um app web para brasileiros treinarem
pronúncia em inglês. O usuário cadastra frases (PT/EN), ouve a pronúncia correta em
três velocidades, grava a própria voz, recebe análise de IA com nota de 0 a 10 e
avança com nota ≥ 8.0 (a nota 10 é o selo 'Dominada'). Frases dominadas ficam numa
lista de conquistas com replay.

### 1.2 Por que o pivô para PHP puro

| | |
|---|---|
| **Simplicidade** | Sem build, sem Node, sem VPS: deploy é subir arquivos no File Manager da Hostinger |
| **Custo** | Hospedagem compartilhada já contratada — zero custo adicional de infraestrutura |
| **Velocidade de MVP** | Menos camadas = menos pontos de falha e menos setup — foco total no produto |
| **Realidade da carga** | O trabalho pesado (gravação, TTS) roda no browser; o PHP só autentica, persiste e repassa o áudio à OpenAI |
| **Migração futura** | Código organizado permite migrar para a VPS (que o dono já possui) sem reescrever — apenas mover arquivos |

> **Limite honesto desta arquitetura (revisado com dados reais):** cada análise ocupa
> um processo PHP por ~2–3s, não os 8–15s originalmente estimados. A hospedagem
> compartilhada atende com folga o MVP e bem além dele. O gatilho de migração para a
> VPS deixa de ser previsão e passa a ser observação: migrar se e quando houver
> lentidão real ou esgotamento de processos.

### 1.3 Decisões técnicas chave do MVP

- Gravação com **MediaRecorder** nativo (webm/opus no Chrome-Firefox, mp4/aac no
  Safari) + conversão **ÚNICA ao final** para WAV 16kHz mono via `OfflineAudioContext`
  — elimina ffmpeg no servidor e evita a armadilha do iOS, que roda o AudioContext a
  48kHz e ignora o sample rate pedido
- **`gpt-audio-1.5`** via cURL — avalia o áudio bruto da pronúncia real. Nunca Whisper
  nem modelos `*-transcribe`, que 'corrigem' o sotaque na transcrição
- **Google OAuth 2.0** implementado manualmente em PHP (sem SDK pesado) — fluxo
  authorization code com endpoints oficiais do Google
- **Sessões PHP nativas** com cookie httponly + secure para autenticação pós-login
- **Interface mobile-first**: o público principal usa celular

---

## 2. Requisitos Funcionais

### 2.1 Autenticação

#### RF-01 — Login com Google OAuth 2.0 (PHP puro)

| | |
|---|---|
| **Fluxo** | Authorization Code Flow: `login.php` redireciona para accounts.google.com → `callback.php` troca o code por tokens → busca perfil em openidconnect.googleapis.com/v1/userinfo → **upsert** do usuário (`INSERT ... ON DUPLICATE KEY UPDATE name, avatar_url` — `email` e `google_id` são ambos UNIQUE; um INSERT simples daria erro 500 se o mesmo e-mail voltasse com outro `google_id`) → inicia sessão PHP → redirect para `dashboard.php` |
| **Credenciais** | `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` no `config.php` (fora do public_html) |
| **Sessão** | `session_start()` com cookie httponly, secure e samesite=Lax. Regenerar session_id no login (`session_regenerate_id`) |
| **State** | Parâmetro `state` aleatório (`random_bytes`) validado no callback contra CSRF |
| **Logout** | `logout.php`: `session_destroy()` + limpar cookie + redirect para `index.php` |
| **Dados salvos** | `google_id`, `email`, `name`, `avatar_url`, `created_at` (coluna `plan` só na Fase 2, quando premium existir) |

#### RF-02 — Proteção de páginas

- Todas as páginas exceto `index.php` (landing/login), `privacy.php` e `terms.php`
  exigem sessão ativa
- Helper `require_auth()` no topo de cada página protegida: sem sessão → redirect para
  `index.php`
- Endpoints de API (pasta `/api`) retornam **401 JSON** se sem sessão — nunca redirect

### 2.2 Gerenciamento de Frases

#### RF-03 — Cadastro de Frases

| | |
|---|---|
| **Campos** | `text_en` OBRIGATÓRIO (é o que se pratica; se o usuário digitar só PT, traduzir ANTES de salvar); `text_pt` opcional (NULL); máx 200 caracteres cada; `category` (existente ou nova); `level` (beginner/intermediate/advanced); `phonetic_guide` (opcional) |
| **Tradução automática** | Se apenas um idioma preenchido, botão 'Traduzir' chama `api/translate.php` (GPT-4o mini) e preenche o outro campo. No MESMO JSON, o endpoint retorna `phonetic_br` — pronúncia aproximada em sons do português, sem IPA (ex: 'How are you?' → 'RRAU ar iú') — pré-preenchendo o `phonetic_guide` (editável). Custo marginal: alguns tokens de saída. Botão 'Gerar fonética' **SEMPRE disponível** — mesmo com PT e EN já preenchidos (o caso mais comum de quem cadastra os dois), chamando o mesmo endpoint |
| **Validação** | Server-side em PHP (nunca confiar só no JS): `trim`, campos obrigatórios, `mb_strlen` ≤ 200 (text_pt/text_en) e ≤ 400 (phonetic_guide — pré-preenchido pela IA via `phonetic_br`, mesma classe de bug do `heard`: sem o limite, estouraria o INSERT no strict mode) |

#### RF-04 — Listagem e Organização

- Listagem paginada (20/página) com filtros por categoria, nível e status via query string
- Toggle PT/EN em cada card (JS puro, sem reload)
- Status visual: Não iniciada / Em progresso / **Aprovada** / Dominada — 'Aprovada'
  (`best_score` ≥ 8 e < 10) é rótulo DERIVADO do `best_score` na exibição; o ENUM do
  schema não muda
- Ordenação: por data de criação, por nível ou por status
- Busca por texto com LIKE em `text_pt` e `text_en` — suficiente para dezenas de frases
  por usuário; sem FULLTEXT no MVP

#### RF-05 — Edição e Exclusão

- Edição de qualquer campo da frase própria; exclusão com confirmação (modal JS)
- DELETE em cascata remove tentativas da frase (FK ON DELETE CASCADE)
- Toda query filtra por `user_id` da sessão — usuário nunca vê dados de outro

### 2.3 Player de Áudio

#### RF-06 — Reprodução em Inglês (Web Speech API)

| | |
|---|---|
| **Tecnologia** | `speechSynthesis` do browser — gratuito, sem custo de API |
| **Velocidades** | Slow: rate 0.6 \| Normal: rate 0.9 \| Fast: rate 1.2 — três botões, seleção persiste na sessão (sessionStorage) |
| **Voz** | Selecionar a melhor voz en-US disponível via `getVoices()` (preferir 'Google US English' no Chrome, 'Samantha' no Safari) |
| **Regra** | GRAVAR habilita no evento `onstart` do play + timer de fallback (nº de palavras × 400ms, mínimo 2s); `onend` é tratado como bônus. **NUNCA depender só do `onend`**: no Safari iOS ele frequentemente não dispara — travaria o app exatamente no público mobile que é o alvo |

> Qualidade da voz varia por browser (boa no Chrome/Edge e Safari, robótica no
> Firefox). Upgrade futuro: OpenAI TTS com cache em MP3 no servidor — frases repetidas
> custam zero.

### 2.4 Gravação de Voz

#### RF-07 — Captura em WAV no Browser

| | |
|---|---|
| **Tecnologia** | MediaRecorder nativo para capturar (robusto em todos os browsers) + conversão única ao FINAL: `decodeAudioData` → `OfflineAudioContext` reamostra para WAV 16kHz mono com pitch correto |
| **Por que assim** | Elimina ffmpeg no servidor E a armadilha do iOS (AudioContext fixo em 48kHz — gravar direto com header '16000' entregaria áudio acelerado à IA). `ScriptProcessorNode` é deprecado e engasga a UI em celular fraco |
| **Onda animada** | `AnalyserNode` ligado ao stream ao vivo — separado da gravação, custo mínimo |
| **Tamanho** | 16kHz mono 16-bit ≈ 32KB/segundo — frase de 10s ≈ 320KB |
| **Duração** | Mínimo 1s, máximo 30s (corte automático) |
| **UX** | Permissão de microfone com mensagem clara se negada; animação de onda + contador durante gravação; preview antes de analisar; botão regravar |
| **HTTPS** | Obrigatório (SSL Let's Encrypt já ativo no domínio) — sem HTTPS o `getUserMedia` não funciona |

```js
// Conversão única ao final da gravação (resolve o iOS a 48kHz).
// Validado no spike do Dia 1, em Chrome Android e Safari iOS.
const ctx = new AudioContext();
const decoded = await ctx.decodeAudioData(await blob.arrayBuffer());
const off = new OfflineAudioContext(1,
  Math.ceil(decoded.duration * 16000), 16000);
const src = off.createBufferSource();
src.buffer = decoded; src.connect(off.destination); src.start();
const pcm = await off.startRendering();  // 16kHz mono, reamostrado correto
// encodeWav(pcm): header RIFF de 44 bytes + Int16Array
await ctx.close();  // liberar — contextos acumulados travam a próxima gravação
```

### 2.5 Análise de IA e Feedback

#### RF-08 — Pipeline de áudio (uma etapa)

O WAV gravado é enviado via POST multipart para `api/analyze.php`, que codifica em
base64 e chama o **`gpt-audio-1.5`** via cURL. O modelo ouve o áudio real e retorna
JSON com nota e feedback.

> **NUNCA usar Whisper (nem `gpt-4o-transcribe`, `gpt-transcribe`,
> `gpt-live-transcribe`) para transcrever antes de avaliar:** eles corrigem o sotaque
> na transcrição e mascaram os erros de pronúncia. O modelo de áudio avalia o áudio bruto.

- `temperature: 0` reduz a variação, mas modelos de áudio **NÃO garantem
  determinismo** — a mesma gravação pode oscilar ~1 ponto entre execuções (mais um
  motivo para o avanço ser ≥ 8.0, e não um gate exato em 10)
- `set_time_limit(60)` no início de `analyze.php` + `CURLOPT_CONNECTTIMEOUT 10` +
  `CURLOPT_TIMEOUT 25`. **Medido:** o teto do LiteSpeed suporta pelo menos 35s de
  conexão ociosa e a chamada real leva ~2,3s — os 25s são rede de segurança com folga
  ampla dos dois lados
- Resposta do modelo validada com `json_decode` + verificação de campos antes de salvar
- **Escolha do modelo:** `gpt-audio-mini` custa menos e deu nota próxima (5,5 vs 6,0
  na mesma gravação), mas foi menos coerente no campo `heard` — transcreveu a frase
  como correta e ainda assim apontou erro em outra palavra. Como o diagnóstico é o
  coração do produto e o custo real é baixo, ficamos no `gpt-audio-1.5`. Reavaliar
  apenas com mais amostras comparadas

#### RF-09 — Estrutura do Feedback (JSON)

| Campo | Descrição |
|---|---|
| `score` | 0.0 a 10.0 (uma casa decimal). 0 = incompreensível; 10 = fluente como nativo |
| `heard` | O que o modelo ouviu o usuário dizer |
| `positives` | O que acertou (sempre presente, mesmo em nota baixa) — PT-BR, máx 80 chars |
| `errors[]` | `{ word, said, correct, tip }` por palavra com problema |
| `naturalness` | Ritmo, entonação e fluidez — PT-BR, máx 80 chars |
| `speed_feedback` | Rápido/lento demais para o nível — PT-BR, máx 60 chars |
| `main_tip` | A dica mais importante e memorável — PT-BR, máx 100 chars |

#### RF-10 — Regra de Progressão

- **AVANÇAR ≠ DOMINAR**: o usuário avança para a próxima frase com nota ≥ 8.0; a nota
  10 é o selo 'Dominada' (confetti + página de conquistas). Um gate exato em 10
  travaria o produto: o modelo raramente dá 10 a não-nativos e a nota oscila ~1 ponto
- Cada tentativa salva em `attempts` (nota, heard, feedback JSON, timestamp)
- Sem limite de tentativas; a melhor nota é a exibida no card
- **Anti-frustração**: a partir da 8ª tentativa sem atingir 8.0, aparece 'Pular por
  agora' — atualiza `last_attempt_at` e a frase volta ao FIM da fila
- **Fila da 'próxima frase'** — SEM filtro por `best_score`: filtrar `best_score < 8`
  faria frases aprovadas (ex: 8.5, não-mastered) sumirem da fila para sempre e a fila
  esvaziaria no cenário mais provável. Na ordenação, aprovadas-não-dominadas vão para
  o FIM. O filesort da expressão é irrelevante com dezenas de frases; `idx_queue`
  segue útil no WHERE:

```sql
WHERE user_id = ? AND status <> 'mastered'
ORDER BY (COALESCE(best_score,0) >= 8) ASC,
         last_attempt_at IS NULL DESC, last_attempt_at ASC, created_at ASC
LIMIT 1
```

- Nota 10 → status `mastered` + `mastered_at` + animação de celebração
- Status `mastered` **NUNCA regride**: praticar uma frase já dominada registra a
  tentativa no histórico, mas não altera status, `best_score` para baixo, nem
  sobrescreve `mastered_at`
- `best_score` **NUNCA regride em NENHUM status**: UPDATE com
  `best_score = GREATEST(COALESCE(best_score,0), :score)` — sem isso, tirar 6.0 ao
  'Tentar o 10' desfaria uma aprovação de 8.5 e devolveria a frase à fila principal
- **Transições de status** (completas — não existem outras): `not_started` até a
  primeira tentativa; primeira tentativa registrada → `in_progress`; score 10 →
  `mastered`

### 2.6 Lista de Conquistas

#### RF-11 — Frases Dominadas

- Página `mastered.php`: cards com EN/PT, categoria, nível, data da conquista, nº de
  tentativas
- Play com seletor de velocidade em cada card
- Contador total de dominadas no perfil

### 2.7 Privacidade e Conta

#### RF-12 — Consentimento e Exclusão

| | |
|---|---|
| **Consentimento** | Modal antes da primeira gravação: áudio enviado para análise por IA e descartado em seguida. Aceite gravado por `api/consent.php` (timestamp em `users.consented_at`) |
| **Páginas legais** | `privacy.php` e `terms.php` públicas, linkadas no rodapé e na tela de login |
| **Exclusão de conta** | Botão no perfil: DELETE do usuário remove frases, tentativas e categorias em cascata |
| **Retenção de áudio** | WAV processado em memória (`php://input` / `$_FILES`) e descartado — nunca gravado em disco |
| **LGPD** | Aplica-se mesmo operando dos EUA (Art. 3º — oferta de serviço a residentes no Brasil). Consentimento + exclusão + minimização cobrem o essencial do MVP |

---

## 3. Requisitos Não Funcionais

### 3.1 Performance e Limites da Hospedagem Compartilhada

Metas revisadas com os tempos medidos no spike:

| Operação | Meta | Máx aceitável | Observação |
|---|---|---|---|
| Carregamento de página | < 1.5s | < 3s | PHP renderiza HTML direto; CSS/JS minificados |
| Play TTS | < 500ms | < 1s | Roda no browser |
| Upload WAV (10s) | < 2s | < 4s | ~320KB |
| **Análise IA** | **< 5s** | **< 15s** | Medido: ~2,3s. Loading animado obrigatório |
| Listagem de frases | < 800ms | < 2s | Índices + paginação |

- Hospedagem compartilhada Hostinger: ~20–40 processos PHP simultâneos (varia por
  plano). Com ~2–3s por análise, isso comporta uma vazão muito superior à do MVP
- Rate limit continua essencial — como freio de **abuso**, não de capacidade (ver 3.2)
- **php.ini: nada a configurar.** O plano já entrega `max_execution_time` 120,
  `upload_max_filesize` 256M, `post_max_size` 256M, `memory_limit` 512M — todos acima
  do que o projeto precisa. Apenas verificar
- **Versão do PHP:** o servidor veio com 8.1.34. Trocar para **8.2 ou 8.3** no hPanel
  (Avançado → Configuração PHP) antes de escrever código
- Plano de escala: mover os mesmos arquivos para a VPS Hostinger se um dia for
  necessário — zero reescrita

> **RISCO CRÍTICO DO DIA 1 — RESOLVIDO (11/08/2026).**
> A hipótese era que o timeout de wall-clock do LiteSpeed matasse o processo durante a
> espera da OpenAI (`max_execution_time` não conta I/O externo). **Medição:** conexão
> ociosa sobreviveu a 20s e a 35s; conexão emitindo bytes sobreviveu a 30s; a chamada
> real completou em 2,3s. O teto não é limitante. O plano B do streaming
> (`stream:true` + `flush()`) e a migração para VPS ficam arquivados como
> contingências não utilizadas.

### 3.2 Segurança

| | |
|---|---|
| **SQL Injection** | PDO com prepared statements em TODAS as queries — nunca concatenar input em SQL |
| **XSS** | `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` em toda saída de dado do usuário |
| **CSRF** | Token por sessão em todos os POST (páginas e `/api`) + validação server-side; `state` no OAuth |
| **Sessão** | httponly + secure + samesite=Lax; `session_regenerate_id` no login |
| **Secrets** | `config.php` FORA do public_html (ex: `/home/user/config/config.php`) com `OPENAI_API_KEY`, `GOOGLE_CLIENT_SECRET` e credenciais MySQL |
| **Rate limiting** | Tabela `rate_limits`: máx 30 análises/dia e 50 traduções/dia por usuário + teto GLOBAL diário. Incremento ATÔMICO via `INSERT...ON DUPLICATE KEY UPDATE` antes da chamada — nunca check-depois-grava (duas abas furariam). **Devolução da cota:** em QUALQUER caminho que não grave um attempt (timeout, HTTP 429/500/503, saldo esgotado, abort pelo teto global) — try/finally até o `commit()`; a única exceção é erro de validação do usuário |
| **Freio de custo REAL** | Saldo pré-pago na OpenAI com auto-recarga **DESLIGADA**. O alerta de billing avisa; só o saldo impede |
| **Uploads** | Aceitar apenas audio/wav até 2MB; validar magic bytes (RIFF); nunca salvar em disco |
| **Erros** | `display_errors` Off em produção; log em arquivo fora do public_html; mensagens genéricas ao usuário |
| **Headers** | `.htaccess`: X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, bloqueio de arquivos `^_` |

**Orçamento recalculado com o custo medido (US$ 0,004/análise):**

| Cenário | Custo |
|---|---|
| Um usuário no limite (30/dia, todo dia do mês) | ~US$ 3,50/mês |
| Teto global de 300 análises/dia | ~US$ 1,17/dia (~US$ 35/mês no pior caso absoluto) |
| Para estourar US$ 60/mês | seriam necessários ~17 usuários no limite máximo, todo dia |

`GLOBAL_DAILY_ANALYSES = 300` **permanece**: cabe folgado no orçamento e continua
sendo um botão de emergência útil. Aumentá-lo só ampliaria o estrago de um abuso.

Detalhe do `usage` medido que orienta otimização futura: o custo é dominado pelo
**texto do prompt** (260 tokens de entrada + 153 de saída) e não pelo áudio (43 tokens
para 4,3s). Encurtar o prompt economiza mais do que encurtar a gravação.

### 3.3 Usabilidade (Mobile-First)

- Layout projetado primeiro para 360–430px (celulares) e expandido para desktop
- Botões de ação com mínimo 44×44px (padrão de toque)
- Feedback visual em toda ação: spinners, toasts, estados do botão de gravação
- Análise em andamento: indicador animado com mensagem ('Ouvindo sua pronúncia...')
- Nota com animação ao aparecer; confetti na nota 10
- Contraste AA (WCAG 2.1); labels em todos os inputs

### 3.4 Confiabilidade e Tratamento de Erros

| | |
|---|---|
| **Microfone negado** | Instrução visual de como liberar no browser/celular |
| **Falha na análise** | Erro amigável + 'Tentar novamente' — a gravação permanece no browser, sem regravar |
| **Timeout OpenAI** | `CURLOPT_TIMEOUT` 25s; se estourar, erro tratado, cota devolvida e gravação preservada no browser |
| **Offline** | `navigator.onLine` + captura de fetch failure com mensagem clara |
| **Resposta inválida da IA** | `json_decode` falhou ou campos ausentes → tratar como erro, não salvar tentativa, devolver a cota |

### 3.5 Manutenibilidade e Observabilidade

- PHP 8.2+ com `declare(strict_types=1)` em todos os arquivos
- Estrutura organizada (ver seção 5) — separação clara entre páginas, API, includes e assets
- Funções e variáveis em inglês; comentários em português
- Log de erros com `error_log()` para arquivo dedicado; revisar semanalmente
- `api/health.php` público: executa `SELECT 1` no MySQL e retorna `{ status: 'ok' }` —
  monitorar com UptimeRobot gratuito
- Backup: export semanal do MySQL via hPanel (ou cron com mysqldump) + download mensal local
- Alerta de billing na OpenAI em US$ 50/mês

---

## 4. Banco de Dados (MySQL 8 / MariaDB)

Engine InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci. Criar via phpMyAdmin no hPanel.

### 4.1 Schema Completo

```sql
CREATE TABLE users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_id    VARCHAR(64)  NOT NULL UNIQUE,
  email        VARCHAR(255) NOT NULL UNIQUE,
  name         VARCHAR(120) NOT NULL,
  avatar_url   VARCHAR(500),
  -- plan (free/premium): adicionar na Fase 2, quando premium existir
  consented_at DATETIME NULL,          -- consentimento de gravação (RF-12)
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_cat (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE phrases (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  category_id    INT UNSIGNED NULL,
  text_pt        VARCHAR(200) NULL,     -- opcional; text_en é o obrigatório
  text_en        VARCHAR(200) NOT NULL,
  phonetic_guide VARCHAR(400) NULL,     -- fonética PT-BR é mais longa que o original
  level          ENUM('beginner','intermediate','advanced') NOT NULL,
  status         ENUM('not_started','in_progress','mastered')
                 NOT NULL DEFAULT 'not_started',
  best_score     DECIMAL(3,1) NULL,     -- 0.0 a 10.0
  attempts_count INT UNSIGNED NOT NULL DEFAULT 0,
  mastered_at    DATETIME NULL,
  last_attempt_at DATETIME NULL,        -- ordenação da fila / skip
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_status (user_id, status),
  KEY idx_queue (user_id, status, last_attempt_at),
  KEY idx_category (category_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phrase_id     INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  score         DECIMAL(3,1) NOT NULL,
  heard         VARCHAR(500) NULL,      -- o que a IA ouviu (modelo pode ser verboso)
  feedback_json JSON NOT NULL,
  audio_seconds TINYINT UNSIGNED NULL,  -- derivado: (bytes - 44) / 32000
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Áudio NÃO é armazenado no MVP (descartado após análise)
  KEY idx_phrase (phrase_id),
  KEY idx_user (user_id),
  FOREIGN KEY (phrase_id) REFERENCES phrases(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  user_id INT UNSIGNED NOT NULL,
  action  ENUM('analyze','translate') NOT NULL,
  day     DATE NOT NULL,
  count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, action, day),
  KEY idx_action_day (action, day),     -- teto global sem full scan
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Arquitetura e Estrutura de Arquivos

### 5.1 Diagrama

```
┌──────────────────────────────────────────────────────────┐
│ USUÁRIO (Browser)                                        │
│   HTML + CSS mobile-first + JS Vanilla                   │
│   - Web Speech API   (play com 3 velocidades)            │
│   - Web Audio API    (gravação WAV 16kHz mono)           │
└───────────────┬──────────────────────────────────────────┘
                │ HTTPS (fetch / form POST)
                ▼
┌──────────────────────────────────────────────────────────┐
│ HOSTINGER — Hospedagem Compartilhada (LiteSpeed)         │
│                                                          │
│   public_html/          PHP 8.2 (páginas + /api)         │
│   ../config/config.php  secrets (fora do public_html)    │
│                                                          │
│   MySQL (mesmo servidor) ◄── PDO prepared statements     │
└───────────────┬──────────────────────────────────────────┘
                │ cURL (somente em analyze.php/translate.php)
                ▼
┌──────────────────────────┐
│ OpenAI API               │
│  - gpt-audio-1.5 (nota)  │
│  - GPT-4o mini (traduz)  │
└──────────────────────────┘
```

### 5.2 Estrutura de Arquivos

> **Ajuste ao layout real do servidor (agosto/2026):** `voxly.hubfy.app` é um
> subdomínio cujo document root é `public_html/voxly/`. O pai dele (`public_html/`)
> é o document root do `hubfy.app` — colocar `includes/` ali serviria o código em
> `https://hubfy.app/includes/`. Por isso a parte privada fica em **`voxly-app/`**,
> irmã de `public_html` e fora de qualquer document root.

```
/home/USUARIO/
├── voxly-app/             # privado — nenhum document root aponta para cá
│   ├── config/
│   │   └── config.php     # OPENAI_API_KEY, GOOGLE_CLIENT_ID/SECRET,
│   │                      # credenciais MySQL
│   ├── logs/
│   │   └── app.log        # error_log() do app
│   └── includes/
│       ├── bootstrap.php  # require config, session, PDO, helpers
│       ├── auth.php       # require_auth(), current_user(), upsert_google_user()
│       ├── db.php         # conexão PDO singleton
│       ├── csrf.php       # geração/validação de token
│       ├── rate_limit.php # incremento atômico + refund + teto global
│       ├── openai.php     # call_audio_model(), call_text_model()
│       ├── header.php     # <head> + nav (mobile-first)
│       └── footer.php
└── public_html/           # ← document root do hubfy.app
    └── voxly/             # ← document root do voxly.hubfy.app
        ├── index.php      # landing + botão 'Entrar com Google'
    ├── auth/
    │   ├── login.php      # monta URL do Google e redireciona (com state)
    │   ├── callback.php   # troca code por token, cria sessão
    │   └── logout.php
    ├── dashboard.php      # lista de frases (filtros, busca, paginação)
    ├── phrase_form.php    # criar/editar frase
    ├── practice.php       # tela de prática (play, gravar, feedback)
    ├── mastered.php       # conquistas
    ├── profile.php        # perfil, estatísticas, excluir conta
    ├── privacy.php        # política de privacidade (pública)
    ├── terms.php          # termos de uso (pública)
    ├── api/
    │   ├── analyze.php    # POST WAV → gpt-audio-1.5 → JSON
    │   ├── translate.php  # POST texto → GPT-4o mini → JSON (+ phonetic_br)
    │   ├── phrases.php    # CRUD via POST + action=create|update|delete
    │   ├── consent.php    # grava consented_at (RF-12)
    │   ├── delete_account.php
    │   └── health.php     # SELECT 1 no MySQL + { status: 'ok' }
    ├── assets/
    │   ├── css/app.css    # mobile-first, variáveis CSS
    │   └── js/
    │       ├── recorder.js   # MediaRecorder + OfflineAudioContext → WAV 16kHz
    │       ├── player.js     # speechSynthesis + 3 velocidades
    │       └── practice.js   # orquestra a tela de prática
    └── .htaccess          # headers de segurança + bloqueios
```

---

## 6. Endpoints e Integração OpenAI

### 6.1 POST /api/analyze.php

| | |
|---|---|
| **Auth** | Sessão PHP ativa (401 JSON caso contrário) |
| **Content-Type** | multipart/form-data |
| **Campos** | `audio` (WAV ≤ 2MB), `phrase_id`, `csrf_token` |
| **Validações** | Sessão + `consented_at` preenchido (403 se não — modal só no client-side seria decorativo); CSRF válido; frase pertence ao user; magic bytes RIFF; rate limit 30/dia (incremento atômico) + teto global diário |
| **Resposta 200** | `{ success:true, data:{ attempt_id, score, feedback, is_mastered } }` |
| **Erros** | `{ success:false, error:{ code, message } }` — mensagens genéricas, log completo no servidor |

**Fluxo:**

1. `set_time_limit(60)`
2. Valida (sessão, consentimento, CSRF, posse da frase, RIFF)
3. Incrementa rate limit (atômico; aborta se acima do teto)
4. base64 do WAV
5. cURL `gpt-audio-1.5` (connect 10s, total 25s)
6. `json_decode` + validação de campos
7. **Sanitização:** `mb_substr($heard, 0, 500)` e clamp do score em 0–10 com cast float
   — o modelo pode devolver `11`, `"9.5"` ou negativo, e um `heard` de 600 chars
   estouraria o INSERT no strict mode DEPOIS de pagar a chamada
8. `audio_seconds` derivado no servidor: `(bytes − 44) / 32000` (WAV 16kHz mono 16-bit
   — de graça, sem campo no POST)
9. `beginTransaction()` → `INSERT attempt` + `UPDATE phrase` com
   `best_score = GREATEST(COALESCE(best_score,0), :score)`, status, counts e
   `last_attempt_at = NOW()` em **TODA** tentativa (não só no skip: é o critério de
   ordenação da fila — sem isso, frase tentada 7× continuaria NULL e nunca cederia o
   topo às frases novas)
10. `commit()` → resposta JSON

Sem transação, uma falha entre o INSERT e o UPDATE deixa `attempts_count` divergente
para sempre.

```sql
-- Incremento atômico do rate limit (ANTES da chamada à OpenAI):
INSERT INTO rate_limits (user_id, action, day, count)
VALUES (?, 'analyze', CURDATE(), 1)
ON DUPLICATE KEY UPDATE count = count + 1;
-- SELECT count em seguida; se > 30, abortar (limite do usuário).

-- Teto global diário (botão de emergência):
SELECT COALESCE(SUM(count),0) FROM rate_limits
 WHERE action='analyze' AND day=CURDATE();
-- Acima do teto global → 'sistema em manutenção, tente mais tarde'.

-- REGRA GERAL DE DEVOLUÇÃO: devolver a cota (count = count - 1) em
-- QUALQUER caminho que não grave um attempt — try/finally: se não
-- chegou ao commit(), decrementa. Cobre timeout, HTTP 429/500/503,
-- insufficient_quota (saldo esgotado) e o abort pelo teto global
-- (o incremento vem ANTES da checagem do SUM — sem devolução, o
-- usuário perderia cota por um limite que não é culpa dele).
-- Única exceção: erro de VALIDAÇÃO do usuário — aí não devolve.
```

### 6.2 Chamada cURL — modelo de áudio

```php
$payload = [
    'model'       => OPENAI_AUDIO_MODEL,   // 'gpt-audio-1.5' — definido em config.php
    'temperature' => 0,
    'modalities'  => ['text'],
    'messages'    => [
        ['role' => 'system', 'content' => SYSTEM_PROMPT],
        ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $userPrompt],
            ['type' => 'input_audio',
             'input_audio' => ['data' => $audioBase64, 'format' => 'wav']],
        ]],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 25,   // rede de segurança; o real medido é ~2,3s
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);
```

### 6.3 Prompts

```
// SYSTEM_PROMPT:
'You are an English pronunciation coach specialized in Brazilian
 Portuguese speakers. You will receive an audio recording of a student
 attempting to say a target phrase. Listen carefully to the ACTUAL
 pronunciation, rhythm, intonation and speed.
 Always respond with ONLY a valid JSON object, no markdown.'

// USER_PROMPT (montado em PHP)
// SEM attempt_number: informar que é a 9ª tentativa envia a nota para
// cima (modelos 'recompensam' persistência) — variação gratuita a eliminar.
Target phrase: "{text_en}"
Level: {level}

Listen to the attached audio and evaluate the pronunciation.
Return ONLY this JSON:
{
  "score": number (0-10, one decimal),
  "heard": string (what the student actually said),
  "positives": string (PT-BR, max 80 chars),
  "errors": [{ "word", "said", "correct", "tip" }],
  "naturalness": string (PT-BR, max 80 chars),
  "speed_feedback": string (PT-BR, max 60 chars),
  "main_tip": string (PT-BR, max 100 chars)
}

Scoring: 0-2 incomprehensible | 3-4 very hard to understand |
5-6 understandable with significant errors | 7-8 good, minor errors |
9 near-native | 10 native-level fluency and naturalness
```

> O modelo pode embrulhar a resposta em ``` apesar da instrução. Limpar cercas de
> código antes do `json_decode` (comportamento observado no spike).

### 6.4 Demais Endpoints

| Endpoint | Método/Auth | Função |
|---|---|---|
| `api/translate.php` | POST / sessão + CSRF | Traduz PT↔EN com GPT-4o mini e retorna também `phonetic_br` no mesmo JSON (pronúncia aproximada em sons do PT-BR, sem IPA); rate limit 50/dia (20/dia apertaria cadastro em lote) |
| `api/phrases.php` | POST / sessão + CSRF | CRUD via campo `action=create\|update\|delete` — só POST: PUT/DELETE em hospedagem compartilhada dão dor de cabeça (corpo não chega em `$_POST`, WAFs bloqueiam) |
| `api/consent.php` | POST / sessão + CSRF | `UPDATE users SET consented_at = NOW() WHERE id = ?` — sem este endpoint, o 403 do `analyze.php` travaria a primeira análise de TODO usuário |
| `api/delete_account.php` | POST / sessão + CSRF | Exclui usuário e dados em cascata; destrói sessão |
| `api/health.php` | GET / público | Executa `SELECT 1` no MySQL e retorna `{ status:'ok' }` — sem tocar o banco, reportaria ok com o MySQL fora do ar |

---

## 7. Fluxo da Tela de Prática (practice.php)

```
1. Página carrega com a frase (PT + EN + pronúncia aproximada) e seletor de velocidade
2. Usuário escolhe Slow / Normal / Fast
3. PLAY → speechSynthesis fala a frase em en-US
4. GRAVAR habilita no onstart do play + timer de fallback (iOS: onend não confiável)
   └── na primeira vez: modal de consentimento → api/consent.php antes de liberar
5. GRAVAR → Web Audio API captura WAV (onda animada + contador)
6. PARAR  → preview do áudio + botões [Regravar] [Analisar]
7. ANALISAR → fetch POST para api/analyze.php
        loading: 'Ouvindo sua pronúncia...' (~3s)
8. FeedbackCard renderiza: nota animada, heard, erros, dica
   ├── nota < 8.0  → [Tentar novamente] (volta ao passo 2)
   │   └── 8ª tentativa sem 8.0 → aparece [Pular por agora]
   ├── 8.0 ≤ nota < 10 → 'Aprovada!' + [Próxima frase] + [Tentar o 10]
   └── nota = 10 → confetti + 'Dominada!' + [Próxima frase]
```

### 7.1 Páginas do App

| Página | Arquivo | Descrição |
|---|---|---|
| Login | `index.php` | Landing com botão Google, links privacy/terms |
| Dashboard | `dashboard.php` | Lista paginada, filtros, busca, FAB de nova frase |
| Nova/Editar Frase | `phrase_form.php` | Form com tradução automática e guia fonético |
| Prática | `practice.php` | Coração do app — fluxo acima |
| Conquistas | `mastered.php` | Cards de dominadas com play |
| Perfil | `profile.php` | Estatísticas, excluir conta |

---

## 8. Setup e Deploy

### 8.1 Ordem de Desenvolvimento

1. ~~**DIA 1 — SPIKE DE VALIDAÇÃO**~~ — **CONCLUÍDO em 11/08/2026.** Resultados na
   seção 3.1. Arquitetura validada; `_spike.php` deletado e a chave usada nele
   revogada. O código de gravação do spike (MediaRecorder + OfflineAudioContext) foi
   validado em celular e serve de base para o `recorder.js`
2. **Trocar o PHP para 8.2+ no hPanel** (o servidor veio com 8.1.34)
3. Google Cloud Console: criar OAuth Client ID (tipo Web), origin
   `https://voxly.hubfy.app`, redirect `https://voxly.hubfy.app/auth/callback.php`
4. MySQL: criar banco e usuário no hPanel; rodar o schema da seção 4 no phpMyAdmin
5. `config.php` fora do public_html + `includes/bootstrap.php`
6. Autenticação completa: `login.php`, `callback.php`, `logout.php`, `require_auth()`
7. CRUD de frases + categorias (`dashboard.php`, `phrase_form.php`, `api/phrases.php`)
8. `player.js`: speechSynthesis com 3 velocidades e seleção de voz
9. `recorder.js`: MediaRecorder nativo + conversão final para WAV 16kHz mono via
   OfflineAudioContext, com preview e regravação
10. `api/analyze.php`: pipeline completo + rate limit
11. FeedbackCard + regra de progressão + anti-frustração
12. `mastered.php` + `profile.php` + `delete_account`
13. `privacy.php`, `terms.php`, modal de consentimento + `api/consent.php`
14. Polish mobile: animações, toasts, confetti, testes no iPhone e Android
15. Deploy: upload via File Manager/FTP, `.htaccess`, testar SSL

### 8.2 config.php (fora do public_html)

```php
<?php
declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'uXXXX_voxlyone');
define('DB_USER', 'uXXXX_voxly');
define('DB_PASS', '********');

define('GOOGLE_CLIENT_ID',     '....apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '********');
define('GOOGLE_REDIRECT_URI',  'https://voxly.hubfy.app/auth/callback.php');

define('OPENAI_API_KEY', 'sk-********');
// Modelo de áudio validado no spike do Dia 1.
// Alternativa mais barata (menos coerente no 'heard'): 'gpt-audio-mini'
define('OPENAI_AUDIO_MODEL', 'gpt-audio-1.5');

// Teto global diário de análises (~US$ 1,17/dia com o custo medido)
define('GLOBAL_DAILY_ANALYSES', 300);

define('APP_URL',  'https://voxly.hubfy.app');
define('LOG_FILE', __DIR__ . '/../logs/app.log');
```

### 8.3 .htaccess essencial

```apache
# Forçar HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# Headers de segurança
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set Referrer-Policy "strict-origin-when-cross-origin"

# Bloquear utilitários prefixados com _ (ex: o _spike.php do Dia 1)
<FilesMatch "^_">
  Require all denied
</FilesMatch>
```

### 8.4 Configuração PHP no hPanel

Medido no spike: **o plano já entrega tudo acima do necessário.** Apenas verificar.

| Diretiva | Necessário | Já entregue |
|---|---|---|
| `max_execution_time` | 60 | 120 |
| `upload_max_filesize` | 8M | 256M |
| `post_max_size` | 10M | 256M |
| `memory_limit` | 256M | 512M |
| `display_errors` | Off (produção) | conferir |
| **Versão do PHP** | **8.2+** | **8.1.34 — trocar** |

---

## 9. Instruções para o Claude Code

Ver [CLAUDE.md](../CLAUDE.md) na raiz do projeto — é a versão operacional desta seção,
carregada automaticamente pelo Claude Code, e inclui as regras de domínio que quebram
o app se ignoradas.

---

## 10. Estimativas e Roadmap

### 10.1 Cronograma MVP

| Fase | Entregável | Tempo | Prioridade |
|---|---|---|---|
| 0 — Spike | Validação da arquitetura | ✅ concluído | Crítico |
| 1 — Base | OAuth Google + MySQL + estrutura | 1–2 dias | Crítico |
| 2 — CRUD | Frases e categorias completos | 1–2 dias | Crítico |
| 3 — Player | TTS com 3 velocidades | 0.5 dia | Crítico |
| 4 — Recorder | WAV no browser + preview | 1–2 dias | Crítico |
| 5 — IA | `analyze.php` + FeedbackCard | 1–2 dias | Crítico |
| 6 — Progressão | Regra do 8/10 + conquistas | 1 dia | Crítico |
| 7 — Legal | Privacy, terms, consentimento, excluir conta | 0.5 dia | Crítico |
| 8 — Polish | Mobile, animações, testes reais | 1–2 dias | Importante |
| **TOTAL** | — | **7–12 dias** | — |

### 10.2 Custos

| Item | Custo |
|---|---|
| Hostinger compartilhada | Já contratada — US$ 0 adicional |
| Domínio + SSL | Já contratados — US$ 0 |
| MySQL | Incluído no plano — US$ 0 |
| Google OAuth | Gratuito |
| Web Speech + Web Audio | Gratuitos (browser) |
| OpenAI (áudio + mini) | **~US$ 5–20/mês** no MVP com o custo medido de US$ 0,004/análise — saldo pré-pago com auto-recarga DESLIGADA como freio real |
| **TOTAL** | **~US$ 5–20/mês** (somente OpenAI) |

A faixa anterior (US$ 20–60) foi calculada sobre US$ 0,03 por análise. Com o custo
real medido, o teto global de 300/dia limita o pior caso absoluto a ~US$ 35/mês.

### 10.3 Roadmap de Evolução

| Fase | Item | Gatilho |
|---|---|---|
| Fase 2 | Migrar para VPS Hostinger (mesmos arquivos) | Lentidão ou limite de processos observados na prática |
| Fase 2 | Freemium: limite free + plano premium | Base de usuários validada |
| Fase 2 | OpenAI TTS com cache MP3 | Reclamações de voz robótica |
| Fase 2 | Encurtar o prompt de análise | Se o custo virar preocupação — o texto domina a conta, não o áudio |
| Fase 3 | Salvar áudios (evolução do aluno) | Pedido de usuários |
| Fase 3 | Azure Pronunciation Assessment (fonema a fonema) | Diferenciação premium |
| Fase 3 | Gamificação: streaks, ranking | Retenção |

---

*— Fim do documento VoxlyOne v2.6.0 —*
