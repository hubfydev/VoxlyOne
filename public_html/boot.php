<?php
declare(strict_types=1);

/**
 * Ponte entre a parte pública e a privada.
 *
 * Único arquivo que sabe LOCALIZAR a pasta `voxly-app`: sobe diretório por
 * diretório a partir daqui até encontrá-la. Assim o app funciona com a pasta
 * privada na home do usuário (preferido) ou dentro do public_html (fallback),
 * sem precisar mexer em todos os arquivos públicos se o layout mudar.
 *
 * Todo arquivo público carrega este arquivo — e nada além dele:
 *   raiz do site:  require_once __DIR__ . '/boot.php';
 *   subpastas:     require_once __DIR__ . '/../boot.php';
 */

$appRoot = null;
$dir     = __DIR__;

for ($i = 0; $i < 6; $i++) {
    if (is_file($dir . '/voxly-app/includes/bootstrap.php')) {
        $appRoot = $dir . '/voxly-app';
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break; // chegou na raiz do sistema de arquivos
    }
    $dir = $parent;
}

if ($appRoot === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Instalacao incompleta: a pasta voxly-app nao foi encontrada.\n");
}

require_once $appRoot . '/includes/bootstrap.php';
unset($appRoot, $dir, $parent, $i);
