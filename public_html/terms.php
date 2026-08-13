<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Página pública: sem require_auth()
$pageTitle = 'Termos de Uso — VoxlyOne';
$hideNav   = current_user() === null;

require APP_INCLUDES . '/header.php';
?>

<article class="legal">
  <h1>Termos de Uso</h1>
  <p class="legal__date">Última atualização: agosto de 2026</p>

  <h2>O que é o VoxlyOne</h2>
  <p>
    O VoxlyOne é uma ferramenta de estudo que ajuda falantes de português a
    treinar a pronúncia do inglês. Você cadastra frases, ouve a pronúncia,
    grava sua voz e recebe uma avaliação automática com nota e dicas.
  </p>

  <h2>Sua conta</h2>
  <p>
    O acesso é feito com uma conta Google. Você é responsável por manter essa
    conta segura e por tudo que for feito através dela no aplicativo.
  </p>

  <h2>Uso aceitável</h2>
  <ul>
    <li>Envie apenas gravações da sua própria voz.</li>
    <li>
      Não cadastre conteúdo ilegal, ofensivo ou que viole direitos de terceiros.
    </li>
    <li>
      Não tente burlar os limites de uso, sobrecarregar o serviço ou acessar
      dados de outros usuários.
    </li>
  </ul>

  <h2>Limites de uso</h2>
  <p>
    Cada análise de pronúncia tem um custo real de processamento. Por isso há um
    limite diário de análises e traduções por usuário, mostrado na página de
    Perfil. Esses limites podem mudar conforme o serviço evolui.
  </p>

  <h2>Sobre a avaliação automática</h2>
  <p>
    As notas e os comentários são gerados por inteligência artificial e servem
    como <strong>apoio ao estudo</strong>, não como avaliação oficial de
    proficiência. O modelo pode errar, e a mesma gravação pode receber notas
    ligeiramente diferentes em tentativas distintas. Não use o VoxlyOne como
    critério para decisões acadêmicas ou profissionais.
  </p>

  <h2>Disponibilidade</h2>
  <p>
    O serviço é oferecido "como está", sem garantia de funcionamento
    ininterrupto. Ele pode ficar indisponível para manutenção, por falha de
    terceiros dos quais dependemos, ou ao atingir o teto diário de uso.
  </p>

  <h2>Encerramento</h2>
  <p>
    Você pode excluir sua conta a qualquer momento na página de Perfil, o que
    apaga definitivamente todos os seus dados. Podemos suspender contas que
    violem estes termos.
  </p>

  <h2>Privacidade</h2>
  <p>
    O tratamento dos seus dados, incluindo o que acontece com as gravações de
    voz, está descrito na <a href="/privacy.php">Política de Privacidade</a>.
  </p>

  <h2>Contato</h2>
  <p>
    Dúvidas sobre estes termos: <strong>[SEU E-MAIL DE CONTATO]</strong>.
  </p>
</article>

<?php require APP_INCLUDES . '/footer.php'; ?>
