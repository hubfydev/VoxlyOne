<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Página pública: sem require_auth()
$pageTitle = 'Termos de Uso — VoxlyOne';
$pageStyles = ['legal'];
$hideNav   = current_user() === null;

require APP_INCLUDES . '/header.php';
?>

<article class="legal">
  <?php if ($hideNav): ?>
    <a class="brand legal__brand" href="/" aria-label="VoxlyOne — início">
      <span class="brand__mark"><?= icon('audio-lines') ?></span>
      <span>Voxly<span class="brand__one">One</span></span>
    </a>
  <?php endif; ?>

  <header class="legal__head">
    <span class="legal__icon"><?= icon('book-open-text') ?></span>
    <h1>Termos de Uso</h1>
    <p class="legal__date"><?= icon('calendar') ?> <span>Última atualização: setembro de 2026</span></p>
  </header>

  <!-- Índice: âncoras para cada seção -->
  <nav class="legal__toc" aria-label="Índice">
    <details>
      <summary><span>Nesta página</span> <?= icon('chevron-down', 'legal__toc-chevron') ?></summary>
      <ol>
        <li><a href="#o-que-e-o-voxlyone">O que é o VoxlyOne</a></li>
        <li><a href="#quem-pode-usar">Quem pode usar</a></li>
        <li><a href="#sua-conta">Sua conta</a></li>
        <li><a href="#uso-aceitavel">Uso aceitável</a></li>
        <li><a href="#limites-de-uso">Limites de uso</a></li>
        <li><a href="#seu-conteudo">Seu conteúdo</a></li>
        <li><a href="#sobre-a-avaliacao-automatica">Sobre a avaliação automática</a></li>
        <li><a href="#garantias-e-responsabilidade">Garantias e responsabilidade</a></li>
        <li><a href="#encerramento">Encerramento</a></li>
        <li><a href="#privacidade">Privacidade</a></li>
        <li><a href="#lei-aplicavel">Lei aplicável</a></li>
        <li><a href="#contato">Contato</a></li>
      </ol>
    </details>
  </nav>

  <section class="legal__section" id="o-que-e-o-voxlyone" aria-labelledby="o-que-e-o-voxlyone-t">
    <h2 id="o-que-e-o-voxlyone-t">O que é o VoxlyOne</h2>
    <p>
      O VoxlyOne é uma ferramenta de estudo que ajuda falantes de português a
      treinar a pronúncia do inglês. Você cadastra frases, ouve a pronúncia, grava
      sua voz e recebe uma avaliação automática com nota e dicas. As gravações com
      nota acima de 8 podem ser organizadas em playlists para você ouvir a própria
      voz.
    </p>
    <p>
      O serviço é operado pela <strong>Hubfy LLC</strong>, na Flórida,
      Estados Unidos. Ao usar o aplicativo, você concorda com estes termos.
    </p>
  </section>
  <section class="legal__section" id="quem-pode-usar" aria-labelledby="quem-pode-usar-t">
    <h2 id="quem-pode-usar-t">Quem pode usar</h2>
    <p>
      É preciso ter pelo menos 13 anos. Se você tem entre 13 e 18, use apenas com a
      concordância de um responsável.
    </p>
  </section>
  <section class="legal__section" id="sua-conta" aria-labelledby="sua-conta-t">
    <h2 id="sua-conta-t">Sua conta</h2>
    <p>
      O acesso é feito com uma conta Google. Você é responsável por mantê-la segura
      e por tudo que for feito através dela no aplicativo.
    </p>
  </section>
  <section class="legal__section" id="uso-aceitavel" aria-labelledby="uso-aceitavel-t">
    <h2 id="uso-aceitavel-t">Uso aceitável</h2>
    <ul>
      <li>Envie apenas gravações da sua própria voz.</li>
      <li>Não cadastre conteúdo ilegal, ofensivo ou que viole direitos de terceiros.</li>
      <li>
        Não tente burlar os limites de uso, sobrecarregar o serviço ou acessar dados
        de outros usuários.
      </li>
    </ul>
  </section>
  <section class="legal__section" id="limites-de-uso" aria-labelledby="limites-de-uso-t">
    <h2 id="limites-de-uso-t">Limites de uso</h2>
    <p>
      Cada análise de pronúncia tem um custo real de processamento. Por isso há um
      limite diário de análises e traduções por usuário, mostrado na página de
      Perfil. O contador zera à meia-noite no horário do Leste dos EUA
      (America/New_York). Esses limites podem mudar conforme o serviço evolui.
    </p>
  </section>
  <section class="legal__section" id="seu-conteudo" aria-labelledby="seu-conteudo-t">
    <h2 id="seu-conteudo-t">Seu conteúdo</h2>
    <p>
      As frases que você cadastra e as gravações da sua voz continuam sendo suas.
      Você nos concede apenas a permissão necessária para armazená-las e
      processá-las para fazer o serviço funcionar — incluindo guardar suas
      gravações aprovadas para as playlists. Não usamos seu conteúdo para treinar
      modelos próprios.
    </p>
  </section>
  <section class="legal__section legal__section--key" id="sobre-a-avaliacao-automatica" aria-labelledby="sobre-a-avaliacao-automatica-t">
    <h2 id="sobre-a-avaliacao-automatica-t">Sobre a avaliação automática</h2>
    <p>
      As notas e os comentários são gerados por inteligência artificial e servem
      como <strong>apoio ao estudo</strong>, não como avaliação oficial de
      proficiência. O modelo pode errar, e a mesma gravação pode receber notas
      ligeiramente diferentes em tentativas distintas. Não use o VoxlyOne como
      critério para decisões acadêmicas, migratórias ou profissionais.
    </p>
  </section>
  <section class="legal__section" id="garantias-e-responsabilidade" aria-labelledby="garantias-e-responsabilidade-t">
    <h2 id="garantias-e-responsabilidade-t">Garantias e responsabilidade</h2>
    <p>
      O serviço é oferecido <strong>"no estado em que se encontra"</strong>, sem
      garantias de qualquer natureza, expressas ou implícitas, incluindo
      comerciabilidade, adequação a uma finalidade específica ou funcionamento
      ininterrupto. Ele pode ficar indisponível para manutenção, por falha de
      terceiros dos quais dependemos ou ao atingir o teto diário de uso.
    </p>
    <p>
      Na máxima extensão permitida em lei, não respondemos por danos indiretos,
      incidentais ou consequentes decorrentes do uso do serviço.
    </p>
  </section>
  <section class="legal__section" id="encerramento" aria-labelledby="encerramento-t">
    <h2 id="encerramento-t">Encerramento</h2>
    <p>
      Você pode excluir sua conta a qualquer momento na página de Perfil, o que
      apaga definitivamente todos os seus dados. Podemos suspender contas que violem
      estes termos.
    </p>
  </section>
  <section class="legal__section" id="privacidade" aria-labelledby="privacidade-t">
    <h2 id="privacidade-t">Privacidade</h2>
    <p>
      O tratamento dos seus dados, incluindo o que acontece com as gravações de voz,
      está descrito na <a href="/privacy.php">Política de Privacidade</a>.
    </p>
  </section>
  <section class="legal__section" id="lei-aplicavel" aria-labelledby="lei-aplicavel-t">
    <h2 id="lei-aplicavel-t">Lei aplicável</h2>
    <p>
      Estes termos são regidos pelas leis do Estado da
      <strong>Flórida</strong> e dos Estados Unidos da América, sem considerar
      regras de conflito de leis. Usuários fora dos EUA mantêm as proteções
      obrigatórias da legislação local que não possam ser afastadas por contrato.
    </p>
  </section>
  <section class="legal__section" id="contato" aria-labelledby="contato-t">
    <h2 id="contato-t">Contato</h2>
    <p>Dúvidas sobre estes termos: <a href="mailto:us@hubfy.us">us@hubfy.us</a>.</p>
  </section>
</article>

<?php require APP_INCLUDES . '/footer.php'; ?>
