<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Página pública: sem require_auth()
$pageTitle = 'Política de Privacidade — VoxlyOne';
$hideNav   = current_user() === null;

require APP_INCLUDES . '/header.php';
?>

<article class="legal">
  <h1>Política de Privacidade</h1>
  <p class="legal__date">Última atualização: agosto de 2026</p>

  <h2>Quem somos</h2>
  <p>
    O VoxlyOne é um aplicativo de treino de pronúncia em inglês operado por
    <strong>[RAZÃO SOCIAL OU NOME COMPLETO]</strong>. Para dúvidas sobre esta
    política ou sobre seus dados, escreva para
    <strong>[SEU E-MAIL DE CONTATO]</strong>.
  </p>

  <h2>Quais dados coletamos</h2>
  <ul>
    <li>
      <strong>Dados da conta Google:</strong> nome, endereço de e-mail, foto de
      perfil e o identificador da sua conta Google. Recebemos esses dados quando
      você escolhe entrar com o Google e os usamos apenas para identificar você
      no aplicativo.
    </li>
    <li>
      <strong>Conteúdo que você cria:</strong> as frases que você cadastra
      (português, inglês e pronúncia aproximada), suas categorias e níveis.
    </li>
    <li>
      <strong>Resultados das análises:</strong> a nota de cada tentativa, o texto
      que a inteligência artificial entendeu e o feedback gerado.
    </li>
  </ul>

  <h2>Sobre suas gravações de voz</h2>
  <p>
    Este é o ponto mais importante desta política, então vamos ser diretos:
  </p>
  <ul>
    <li>
      Sua gravação é enviada ao nosso servidor, repassada imediatamente à
      <strong>OpenAI</strong> para análise da pronúncia e
      <strong>descartada em seguida</strong>.
    </li>
    <li>
      O áudio <strong>não é gravado em disco</strong> nem armazenado em banco de
      dados. Ele existe apenas na memória do servidor durante os poucos segundos
      da análise.
    </li>
    <li>
      O que fica guardado é somente o <strong>resultado em texto</strong>: a nota
      e o feedback.
    </li>
    <li>
      Pedimos seu consentimento explícito antes da primeira gravação, e
      registramos a data desse aceite.
    </li>
  </ul>

  <h2>Compartilhamento com terceiros</h2>
  <p>
    Usamos a <strong>OpenAI</strong> (Estados Unidos) para analisar a pronúncia e
    para traduzir frases. Nesses momentos, o áudio e o texto da frase são
    transmitidos a ela. Não vendemos seus dados, não os usamos para publicidade e
    não os compartilhamos com nenhum outro terceiro.
  </p>
  <p>
    Também usamos o <strong>Google</strong> exclusivamente para autenticação.
  </p>

  <h2>Base legal e seus direitos (LGPD)</h2>
  <p>
    Tratamos seus dados com base no seu consentimento e na execução do serviço que
    você solicitou. Conforme a Lei Geral de Proteção de Dados, você pode a
    qualquer momento:
  </p>
  <ul>
    <li>confirmar quais dados temos sobre você e acessá-los;</li>
    <li>corrigir dados incompletos ou desatualizados;</li>
    <li>revogar o consentimento;</li>
    <li>
      <strong>excluir sua conta e todos os dados associados</strong>, com um
      botão na página de Perfil — a exclusão é imediata e definitiva, e remove
      frases, categorias e todo o histórico de tentativas.
    </li>
  </ul>

  <h2>Por quanto tempo guardamos</h2>
  <p>
    Enquanto sua conta existir. Ao excluí-la, os dados são apagados do banco de
    dados na mesma hora. Cópias de segurança rotineiras podem reter informações
    por até 30 dias adicionais antes de serem sobrescritas.
  </p>

  <h2>Segurança</h2>
  <p>
    Todo o tráfego usa HTTPS. As credenciais de acesso ficam fora da área pública
    do servidor, e cada usuário só enxerga os próprios dados.
  </p>

  <h2>Alterações</h2>
  <p>
    Se esta política mudar de forma relevante, avisaremos no próprio aplicativo
    antes que a mudança passe a valer.
  </p>
</article>

<?php require APP_INCLUDES . '/footer.php'; ?>
