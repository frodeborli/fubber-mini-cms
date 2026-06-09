<?php

require __DIR__ . '/../_auth.php';

use MiniCms\Content;
use mini\Mini;

header('Content-Type: application/json');

$slug = $_GET[0] ?? '';
$content = Mini::$mini->get(Content::class);

match ($_SERVER['REQUEST_METHOD']) {
    'GET' => (function () use ($content, $slug) {
        $page = $content->getPage($slug);
        if (!$page) {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
            return;
        }
        echo json_encode($page);
    })(),

    'PUT', 'POST' => (function () use ($content, $slug) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON body']);
            return;
        }
        $content->savePage($slug, $data);
        echo json_encode($content->getPage($slug));
    })(),

    'DELETE' => (function () use ($content, $slug) {
        if ($content->deletePage($slug)) {
            echo json_encode(['deleted' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Page not found']);
        }
    })(),

    default => (function () {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    })(),
};
