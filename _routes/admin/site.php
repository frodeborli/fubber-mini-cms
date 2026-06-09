<?php

require __DIR__ . '/_auth.php';

use MiniCms\Content;
use mini\Mini;

header('Content-Type: application/json');

$content = Mini::$mini->get(Content::class);

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        return;
    }
    $content->saveSiteConfig($data);
}

echo json_encode($content->siteConfig());
