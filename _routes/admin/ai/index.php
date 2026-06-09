<?php

use mini\Http\Message\HtmlResponse;

if (empty($_SESSION['cms_user'])) {
    return new \mini\Http\Message\Response('', ['Location' => '/login?redirect=/admin/ai/'], 302);
}

$html = \mini\render('cms/admin-ai-shell.php', [
    'currentTool' => 'ai',
]);

return new HtmlResponse($html);
