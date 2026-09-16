<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

// Só POST com CSRF: um link ou imagem de outro site não consegue derrubar a sessão
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_check($_POST['csrf_token'] ?? null)) {
    admin_logout();
}

redirect('/admin/index.php');
