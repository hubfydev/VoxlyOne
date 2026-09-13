# VoxlyOne — Instruções do Projeto

App web de treino de pronúncia em inglês para brasileiros. O usuário cadastra frases
(PT/EN), ouve a pronúncia correta em três velocidades, grava a própria voz, recebe
análise de IA com nota de 0 a 10, avança com **≥ 8.0** e ganha o selo "Dominada" com **10**.

Documento completo: [docs/VoxlyOne_Arquitetura_Tecnica_v2.6.md](docs/VoxlyOne_Arquitetura_Tecnica_v2.6.md)

## Stack

PHP 8.2+ puro (sem framework) · MySQL via PDO · JS Vanilla sem build · HTML/CSS mobile-first
Hostinger hospedagem compartilhada (LiteSpeed) · Domínio: voxly.hubfy.app
IA: OpenAI `gpt-audio-1.5` via cURL · Auth: Google OAuth 2.0 manual + sessões PHP

## Fatos medidos (spike do Dia 1, 11/08/2026) — não re-derivar

- **Wall-clock do LiteSpeed não é limitante**: conexão ociosa sobreviveu a 35s.
  Streaming e VPS são desnecessários.
- **Modelo correto: `gpt-audio-1.5`**. Os nomes `gpt-4o-audio-preview` e
  `gpt-4o-mini-audio-preview` foram aposentados e retornam 404.
- **Latência real ~2,3s** por análise (não 8–15s).
- **Custo real ~US$ 0,004** por análise (não US$ 0,03). O grosso é o texto do
  prompt, não o áudio — encurtar prompt economiza mais que encurtar áudio.
- **Ambiente do plano**: upload_max_filesize e post_max_size 256M, memory_limit 512M,
  max_execution_time 120, cURL e pdo_mysql presentes. Nada a ajustar no php.ini.
- `gpt-audio-mini` é ~igual em nota mas menos coerente no campo `heard`. Ficamos no
  modelo grande; só reavaliar com mais amostras.

## Regras obrigatórias

- `declare(strict_types=1)` no topo de todo arquivo PHP
- TODA query via PDO prepared statements — proibido concatenar input em SQL
- TODA saída de dado de usuário via `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`
- TODO endpoint POST valida token CSRF server-side (inclusive os de `/api`)
- Secrets APENAS em `config.php` fora do `public_html` — nunca hardcoded
- Endpoints `/api` sempre retornam JSON `{ success, data|error }` com
  `http_response_code` correto; 401 JSON se sem sessão, nunca redirect
- Toda página protegida começa com `require_auth()`; toda query filtra por
  `user_id` da sessão
- Áudio com nota ≤ 8 nunca vai para disco — processar em memória e descartar.
  Só a gravação aprovada (> 8) é guardada, via `includes/recordings.php`
- Mensagens de erro ao usuário: genéricas e em PT-BR; detalhes técnicos só no log
- JS sem frameworks e sem build: estáticos em `assets/js`, ES6+, sem CDN.
  O confetti é ~20 linhas de canvas próprio.
- CSS mobile-first com variáveis CSS; breakpoints `min-width`
- Funções e variáveis em inglês; comentários em português

## Regras de domínio que quebram o app se ignoradas

**Progressão** — avançar ≠ dominar: avança com `score >= 8.0`, "Dominada" só com `10`.
Um gate exato em 10 trava o produto (o modelo raramente dá 10 a não-nativos e a nota
oscila ~1 ponto).

**`best_score` nunca regride**, em nenhum status:
`best_score = GREATEST(COALESCE(best_score,0), :score)`. Sem isso, tirar 6.0 ao
"Tentar o 10" desfaz uma aprovação de 8.5.

**`last_attempt_at = NOW()` em TODA tentativa**, não só no skip — é o critério de
ordenação da fila.

**Fila da próxima frase** (sem filtro por `best_score`, que esvaziaria a fila):
```sql
WHERE user_id = ? AND status <> 'mastered'
ORDER BY (COALESCE(best_score,0) >= 8) ASC,
         last_attempt_at IS NULL DESC, last_attempt_at ASC, created_at ASC
LIMIT 1
```

**Transições de status** (completas, não existem outras): `not_started` até a primeira
tentativa → `in_progress` na primeira tentativa registrada → `mastered` com score 10.
`mastered` nunca regride.

**Rate limit**: incremento ATÔMICO (`INSERT ... ON DUPLICATE KEY UPDATE`) **antes** da
chamada à OpenAI. Devolver a cota (`count - 1`) em QUALQUER caminho que não grave um
attempt. Única exceção: erro de validação do usuário, que aborta antes do incremento.

⚠️ **`exit` não executa `finally` em PHP.** Como `json_ok()`/`json_error()` terminam
com `exit`, chamá-las dentro do `try` pula o refund silenciosamente. Padrão correto:
o `try` só computa e guarda o resultado numa variável; a resposta HTTP vem **depois**
do `finally`. Ver [api/translate.php](public_html/api/translate.php).

**Sanitização da resposta da IA**: `mb_substr($heard, 0, 500)` e clamp do score em
0–10 com cast float, ANTES do INSERT. O modelo pode devolver `11`, `"9.5"` ou um
`heard` de 600 chars — e o INSERT estouraria depois de já ter pago a chamada.

**Transação**: `INSERT attempt` + `UPDATE phrase` no mesmo `beginTransaction()`.
Sem isso, uma falha entre os dois deixa `attempts_count` divergente para sempre.

**Consentimento**: `analyze.php` retorna 403 se `consented_at` for NULL. O modal é
client-side; quem grava é `api/consent.php`.

**Playlists (12/09/2026)** — a playlist toca a **voz do usuário**, nunca a voz
sintetizada de referência. Regras:
- Entra em playlist só gravação com `score > 8` (estrito: `8.0` avança na prática
  mas NÃO entra). Constante `PLAYLIST_MIN_SCORE`, checada também no servidor.
- `recordings` guarda UMA gravação aprovada atual por frase (`UNIQUE phrase_id`).
  Nova aprovação mantém a linha e troca só o arquivo → todas as playlists passam a
  tocar o áudio novo sem update. Nota ≤ 8 não mexe na aprovada.
- Ordem ao substituir: grava arquivo novo → troca referência no banco → apaga o antigo.
- `attach_approved_recording()` NUNCA lança: falha ao guardar áudio não pode virar
  erro numa análise já paga.
- `playlist_items` referencia `recording_id` — nada de copiar arquivo.
- Arquivos em `voxly-app/storage/recordings/{user_id}/`, servidos só pelo
  `recording.php` (confere o dono, suporta Range — o Safari iOS exige).
- FK não apaga arquivo: exclusão de frase e de conta apagam os arquivos explicitamente.
- Excluir playlist ou remover item nunca apaga áudio.

**Consentimento versionado**: `CONSENT_VERSION` em `auth.php` (hoje 2). Mudou o
texto do modal/privacidade sobre gravações → incrementar, e todos aceitam de novo.
Usar `has_current_consent($user)`, não `consented_at !== null`.

**Nunca usar Whisper nem qualquer modelo `*-transcribe`** para transcrever antes de
avaliar — eles corrigem o sotaque e apagam exatamente o erro que queremos medir.

**`phonetic_guide` é funcionalidade de primeira classe**, não acessório: pronúncia
aproximada em sons do português, sem IPA. Pré-preenchida pela IA, editável, e exibida
na tela de prática junto com a frase.

## Estrutura

**No servidor** — `voxly.hubfy.app` é subdomínio com document root em
`public_html/voxly/`. A parte privada NÃO pode ficar em `public_html/`, que é o
document root do `hubfy.app` e serviria o código na web. Ela mora em `voxly-app/`,
irmã de `public_html`:

```
/home/USUARIO/
├── voxly-app/                 # privado — nenhum document root aponta para cá
│   ├── config/config.php      # secrets
│   ├── logs/app.log
│   ├── storage/recordings/    # gravações aprovadas (criada sozinha; fora do git)
│   └── includes/
│       ├── bootstrap.php  auth.php  db.php  csrf.php  phrases.php
│       ├── rate_limit.php  openai.php  header.php  footer.php
│       └── recordings.php  playlists.php  icons.php
└── public_html/               # ← document root do hubfy.app
    └── voxly/                 # ← document root do voxly.hubfy.app
        ├── index.php  dashboard.php  phrase_form.php  practice.php
        ├── mastered.php  profile.php  privacy.php  terms.php
        ├── playlists.php  playlist.php  recording.php
        ├── auth/{login,callback,logout}.php
        ├── api/{analyze,translate,phrases,playlists,consent,delete_account,health}.php
        ├── assets/css/app.css  picker.css  pages/*.css
        ├── assets/fonts/plus-jakarta-sans.woff2  OFL.txt
        ├── assets/js/{recorder,player,practice,...}.js
        ├── assets/js/{playlist_picker,playlist_queue,playlist_player,playlists,icons}.js
        └── .htaccess
```

**No repositório**, `public_html/` corresponde a `public_html/voxly/` no servidor e
`includes/` + `config.example.php` correspondem a `voxly-app/`.

Todo arquivo público carrega **apenas** `boot.php`, nunca o bootstrap direto:

```php
require_once __DIR__ . '/boot.php';      // arquivos na raiz do site
require_once __DIR__ . '/../boot.php';   // arquivos em auth/ e api/
```

`public_html/boot.php` é o único que sabe localizar a pasta privada: sobe diretório
por diretório procurando `voxly-app/includes/bootstrap.php`. Isso faz o app funcionar
com a `voxly-app` na home (preferido) ou dentro do `public_html` (fallback, quando o
gerenciador de arquivos não dá acesso à home), sem editar arquivo nenhum.

Depois do bootstrap carregado, use a constante `APP_INCLUDES` para incluir
`header.php` e `footer.php`.

Endpoints `/api` são todos POST (exceto `health.php`, GET público). O áudio das
gravações sai pelo `recording.php` na raiz (GET, sem efeito colateral, não é JSON).
Migrations incrementais ficam em `db/migrations/` e rodam ANTES de subir o código.
Nada de PUT/DELETE: em hospedagem compartilhada o corpo não chega em `$_POST` e
WAFs bloqueiam. CRUD via `action=create|update|delete`.

## Interface (redesenho de 13/09/2026)

- **Design system em `assets/css/app.css`**: tokens de cor, espaço, raio e sombra, com
  tema escuro automático (`prefers-color-scheme`). **Toda cor vem de token**; o tema
  escuro troca só os tokens. Componentes: `.btn` (+ `--sm --lg --block --ghost --soft
  --danger --icon`), `.card`, `.badge--*`, `.speeds/.speed` (segmentado), `.field`,
  `.empty`, `.modal` (folha inferior no celular).
- **CSS por tela** em `assets/css/pages/{nome}.css`, declarado na página com
  `$pageStyles = ['nome'];` antes do header. `picker.css` carrega em toda tela logada.
- **Navegação**: barra superior (marca + avatar) e **barra de abas inferior** fixa
  (Frases, Playlists, Praticar em destaque, Conquistas, Perfil). Com abas, o rodapé
  legal some — Privacidade e Termos ficam no Perfil.
- **Ícones**: Lucide inline, sem CDN — `icon('nome')` em PHP (`includes/icons.php`) e
  `icon('nome')` em JS (`assets/js/icons.js`). Botão só com ícone exige `aria-label`.
- **Fonte**: Plus Jakarta Sans auto-hospedada em `assets/fonts/` (OFL).
- **Cache**: CSS e JS entram com `asset('/assets/...')`, que acrescenta `?v=mtime` —
  cada deploy invalida só o que mudou.
- Alvos de toque ≥ 44px, inputs com 16px (evita zoom do iOS), nada de rolagem
  horizontal em 360px, `prefers-reduced-motion` respeitado.

## Checklist antes de cada commit

- Nenhum secret no código; prepared statements em tudo; XSS escapado
- `php -l` sem erros em todos os arquivos alterados
- Testado no celular (Chrome Android + Safari iOS) além do desktop
- Erros tratados com mensagem ao usuário + log no servidor

## Estado atual (11/08/2026)

**MVP funcional no ar em voxly.hubfy.app.** Fases 1 a 7 do cronograma concluídas e
testadas no servidor: OAuth Google, CRUD de frases e categorias, tradução + fonética,
tela de prática completa (TTS, gravação, análise, feedback, confetti), conquistas,
perfil com exclusão de conta, páginas legais e consentimento.

Git em `https://github.com/hubfydev/VoxlyOne` — commit `v1.0 - Versao beta`.
O `_spike.php` foi deletado e a chave usada nele, revogada.

**Produto americano.** Operado pela **Hubfy LLC** (Flórida, EUA), contato
`us@hubfy.us`, lei aplicável da Flórida. A interface é em português porque o
público-alvo fala português — nos EUA ou no Brasil. Fuso de referência:
`America/New_York` (constante `APP_TIMEZONE`), alinhado também na sessão do MySQL
para que `CURDATE()` do rate limit zere no horário certo. Datas exibidas via
`format_date()`, no formato "12 ago 2026" — o numérico seria ambíguo entre os
dois públicos.

**Playlists (12/09/2026)**: gravações aprovadas (> 8) guardadas, playlists com
Shuffle/Repeat independentes e salvos por playlist, player da própria voz, seletor
de voz feminina/masculina na referência (por nome da voz; sem voz do gênero no
aparelho, aproxima pelo tom). Testado localmente em MariaDB 11 + PHP 8.3 (19
testes de API + 20 de navegador). Deploy exige rodar
`db/migrations/2026-09-12_playlists.sql` antes de subir os arquivos.

**Pendências:**
- Rodar a migration de playlists no servidor e testar playlists no iPhone real
  (Range/autoplay entre faixas com tela bloqueada)
- Fase 8 (polimento) não iniciada: animações, toasts, revisão em telas pequenas
- Ainda não testado o ciclo completo até a nota 10 (confetti + entrada em Conquistas)
- Resolvido: PHP 8.3 no servidor; dados legais preenchidos; bug do modal de
  consentimento (`[hidden]` sobrescrito por `display:flex`) corrigido
