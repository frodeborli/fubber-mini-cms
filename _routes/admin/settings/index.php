<?php

use mini\Http\Message\HtmlResponse;

if (empty($_SESSION['cms_user'])) {
    return new \mini\Http\Message\Response('', ['Location' => '/login?redirect=/admin/settings/'], 302);
}

$html = \mini\render('cms/admin-settings.php');

return new HtmlResponse($html);
