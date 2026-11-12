# Corte individual para playlists

Executar `db/migrations/2026-11-11_playlist_score_threshold.sql` antes de publicar
os arquivos PHP/JS/CSS. Instalações novas usam `db/schema.sql` atualizado.
A migração começa com corte 8,0 para todas as contas e não altera gravações ou itens.

Perfil → Configurações permite salvar um corte de 4,0 a 9,0, com passo de 0,1.
A nota deve ser estritamente maior que o corte. A progressão da prática permanece
independente (`SCORE_TO_ADVANCE`); o selo de dominada continua exigindo 10.

A preferência é aplicada ao guardar novos áudios e incluir gravações em novas
playlists. Aumentar o corte não remove itens existentes nem apaga gravações.
Manter/remover associações existentes continua permitido. Uma nova gravação que
supere o corte substitui a anterior da mesma frase em todas as playlists, mesmo
com nota inferior à anterior. Reduzir o corte não recupera áudios descartados.

Consentimento versão 3: todos precisam aceitar o aviso atualizado antes da próxima
análise. Privacidade e termos descrevem a preferência e a retenção de áudio.

Verificação local: `php tests/playlist_preferences.php`, lint dos PHP alterados e
checagem sintática de JS. Verificar em ambiente com banco migrado: persistência
ao recarregar/entrar em outro aparelho, isolamento entre usuários, rejeição de POST
sem autenticação/CSRF, salvar 4/8/9, análise abaixo/igual/acima do corte e reprodução
de itens existentes após subir o corte. Conferir interface em Chrome Android e
Safari iOS.
