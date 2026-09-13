<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Página pública: sem require_auth()
$pageTitle = 'Política de Privacidade — VoxlyOne';
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
    <span class="legal__icon"><?= icon('shield-check') ?></span>
    <h1>Política de Privacidade</h1>
    <p class="legal__date"><?= icon('calendar') ?> <span>Última atualização: setembro de 2026</span></p>
  </header>

  <!-- Índice: âncoras para cada seção -->
  <nav class="legal__toc" aria-label="Índice">
    <details>
      <summary><span>Nesta página</span> <?= icon('chevron-down', 'legal__toc-chevron') ?></summary>
      <ol>
        <li><a href="#quem-somos">Quem somos</a></li>
        <li><a href="#quais-dados-coletamos">Quais dados coletamos</a></li>
        <li><a href="#sobre-suas-gravacoes-de-voz">Sobre suas gravações de voz</a></li>
        <li><a href="#compartilhamento-com-terceiros">Compartilhamento com terceiros</a></li>
        <li><a href="#seus-direitos">Seus direitos</a></li>
        <li><a href="#criancas">Crianças</a></li>
        <li><a href="#transferencia-internacional">Transferência internacional</a></li>
        <li><a href="#por-quanto-tempo-guardamos">Por quanto tempo guardamos</a></li>
        <li><a href="#seguranca">Segurança</a></li>
        <li><a href="#alteracoes">Alterações</a></li>
      </ol>
    </details>
  </nav>

  <section class="legal__section" id="quem-somos" aria-labelledby="quem-somos-t">
    <h2 id="quem-somos-t">Quem somos</h2>
    <p>
      O VoxlyOne é um aplicativo de treino de pronúncia em inglês operado por
      <strong>Hubfy LLC</strong>, sediada na Flórida,
      <strong>Estados Unidos da América</strong>. Nossos servidores e o tratamento
      dos dados ocorrem nos EUA. Para dúvidas sobre esta política, escreva para
      <a href="mailto:us@hubfy.us">us@hubfy.us</a>.
    </p>
    <p>
      O aplicativo é oferecido em português porque foi feito para quem fala
      português e quer treinar a pronúncia do inglês — dentro ou fora dos EUA.
    </p>
  </section>
  <section class="legal__section" id="quais-dados-coletamos" aria-labelledby="quais-dados-coletamos-t">
    <h2 id="quais-dados-coletamos-t">Quais dados coletamos</h2>
    <ul>
      <li>
        <strong>Dados da conta Google:</strong> nome, endereço de e-mail, foto de
        perfil e o identificador da sua conta. Recebemos esses dados quando você
        escolhe entrar com o Google e os usamos apenas para identificar você.
      </li>
      <li>
        <strong>Conteúdo que você cria:</strong> as frases que você cadastra
        (português, inglês e pronúncia aproximada), suas categorias e níveis.
      </li>
      <li>
        <strong>Resultados das análises:</strong> a nota de cada tentativa, o texto
        que a inteligência artificial entendeu e o feedback gerado.
      </li>
      <li>
        <strong>Gravações aprovadas:</strong> o áudio da sua última gravação com nota
        acima de 8 em cada frase, e as playlists que você monta com elas.
      </li>
    </ul>
    <p>
      Não usamos cookies de rastreamento, não temos publicidade e não fazemos
      perfilamento. O único cookie é o da sua sessão de login.
    </p>
  </section>
  <section class="legal__section legal__section--key" id="sobre-suas-gravacoes-de-voz" aria-labelledby="sobre-suas-gravacoes-de-voz-t">
    <h2 id="sobre-suas-gravacoes-de-voz-t">Sobre suas gravações de voz</h2>
    <p>Este é o ponto mais importante desta política, então vamos ser diretos:</p>
    <ul>
      <li>
        Sua gravação é enviada ao nosso servidor e repassada imediatamente à
        <strong>OpenAI</strong> para análise da pronúncia.
      </li>
      <li>
        Se a nota for <strong>8 ou menos</strong>, o áudio é
        <strong>descartado em seguida</strong>: não é gravado em disco nem em banco
        de dados. Dessa tentativa guardamos só o resultado em texto — a nota e o
        feedback.
      </li>
      <li>
        Se a nota for <strong>maior que 8</strong>, guardamos o áudio para que você
        possa ouvir a própria voz nas suas playlists. Fica guardada apenas
        <strong>a última gravação aprovada de cada frase</strong>: uma nova aprovação
        substitui a anterior, que é apagada.
      </li>
      <li>
        Esses áudios ficam numa área privada do servidor, fora do acesso público, e
        <strong>só você consegue ouvi-los</strong>, depois de entrar na sua conta.
        Não os compartilhamos com ninguém e não os usamos para treinar modelos.
      </li>
      <li>
        Não usamos sua voz para identificar você e não criamos impressão vocal
        (<em>voiceprint</em>) nem qualquer outro dado biométrico a partir dela.
      </li>
      <li>
        Você apaga a gravação guardada excluindo a frase, e todas elas excluindo a
        conta. Tirar uma gravação de uma playlist, ou excluir a playlist, não apaga
        o áudio.
      </li>
      <li>
        Pedimos seu consentimento explícito antes da primeira gravação, registramos
        a data desse aceite e pedimos de novo sempre que esta seção mudar.
      </li>
    </ul>
  </section>
  <section class="legal__section" id="compartilhamento-com-terceiros" aria-labelledby="compartilhamento-com-terceiros-t">
    <h2 id="compartilhamento-com-terceiros-t">Compartilhamento com terceiros</h2>
    <p>
      Usamos a <strong>OpenAI</strong> para analisar a pronúncia e traduzir frases.
      Nesses momentos, o áudio e o texto da frase são transmitidos a ela. Usamos o
      <strong>Google</strong> exclusivamente para autenticação.
    </p>
    <p>
      <strong>Não vendemos nem compartilhamos seus dados pessoais</strong> para
      publicidade comportamental, no sentido que a legislação de privacidade da
      Califórnia dá a esses termos. Não há nenhum outro terceiro com acesso.
    </p>
  </section>
  <section class="legal__section" id="seus-direitos" aria-labelledby="seus-direitos-t">
    <h2 id="seus-direitos-t">Seus direitos</h2>
    <p>
      Independentemente de onde você mora, oferecemos os mesmos direitos a todos os
      usuários: acessar os dados que temos sobre você, corrigi-los, revogar o
      consentimento e <strong>excluir sua conta com todos os dados associados</strong>,
      por um botão na página de Perfil. A exclusão é imediata e definitiva, e remove
      frases, categorias, todo o histórico de tentativas, as gravações guardadas e
      as playlists.
    </p>
    <p>
      <strong>Residentes nos Estados Unidos:</strong> alguns estados — como
      Califórnia, Colorado, Connecticut, Virgínia e Utah — garantem direitos
      adicionais de acesso, correção, exclusão e portabilidade, além do direito de
      não sofrer discriminação por exercê-los. Escreva para o e-mail acima para
      exercer qualquer um deles.
    </p>
    <p>
      <strong>Residentes no Brasil:</strong> ainda que operemos dos EUA, a Lei Geral
      de Proteção de Dados (LGPD) se aplica quando oferecemos serviço a pessoas no
      Brasil. Você tem os direitos previstos no art. 18 da lei, atendidos pelo mesmo
      canal de contato.
    </p>
  </section>
  <section class="legal__section" id="criancas" aria-labelledby="criancas-t">
    <h2 id="criancas-t">Crianças</h2>
    <p>
      O VoxlyOne não se destina a menores de 13 anos e não coletamos
      intencionalmente dados dessa faixa etária. Se soubermos que isso ocorreu,
      apagaremos a conta. Se você é responsável e acredita que uma criança criou uma
      conta, escreva para o e-mail acima.
    </p>
  </section>
  <section class="legal__section" id="transferencia-internacional" aria-labelledby="transferencia-internacional-t">
    <h2 id="transferencia-internacional-t">Transferência internacional</h2>
    <p>
      Os dados são processados nos Estados Unidos. Ao usar o aplicativo de fora dos
      EUA, você entende que suas informações serão transferidas e tratadas lá, sob a
      legislação americana, com as salvaguardas descritas nesta política.
    </p>
  </section>
  <section class="legal__section" id="por-quanto-tempo-guardamos" aria-labelledby="por-quanto-tempo-guardamos-t">
    <h2 id="por-quanto-tempo-guardamos-t">Por quanto tempo guardamos</h2>
    <p>
      Enquanto sua conta existir. Ao excluí-la, os dados são apagados do banco e os
      arquivos de áudio são apagados do servidor na mesma hora. Cópias de segurança rotineiras podem reter informações por até 30
      dias adicionais antes de serem sobrescritas.
    </p>
  </section>
  <section class="legal__section" id="seguranca" aria-labelledby="seguranca-t">
    <h2 id="seguranca-t">Segurança</h2>
    <p>
      Todo o tráfego usa HTTPS. As credenciais de acesso ficam fora da área pública
      do servidor, e cada usuário só enxerga os próprios dados. Nenhum sistema é
      infalível, mas tratamos o mínimo de dados possível — a começar por descartar
      todo áudio que não foi aprovado e guardar só uma gravação por frase.
    </p>
  </section>
  <section class="legal__section" id="alteracoes" aria-labelledby="alteracoes-t">
    <h2 id="alteracoes-t">Alterações</h2>
    <p>
      Se esta política mudar de forma relevante, avisaremos no próprio aplicativo
      antes que a mudança passe a valer.
    </p>
  </section>
</article>

<?php require APP_INCLUDES . '/footer.php'; ?>
