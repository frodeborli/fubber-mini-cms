<?php

use mini\Http\Message\JsonResponse;
use mini\Http\Message\Response;
use MiniCms\Ai\AgentInterface;
use MiniCms\GeneratorStream;

if (empty($_SESSION['cms_user'])) {
    return new JsonResponse(['error' => 'Unauthorized'], [], 401);
}

$pos = (int) ($_GET['pos'] ?? 0);
$agent = \mini\Mini::$mini->get(AgentInterface::class);

$body = new GeneratorStream($agent->stream($pos));

return (new Response('', [
    'Content-Type' => 'application/x-ndjson',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',
]))->withBody($body);
