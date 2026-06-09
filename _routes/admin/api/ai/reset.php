<?php

use mini\Http\Message\JsonResponse;
use MiniCms\Ai\AgentInterface;

if (empty($_SESSION['cms_user'])) {
    return new JsonResponse(['error' => 'Unauthorized'], [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return new JsonResponse(['error' => 'Method not allowed'], [], 405);
}

$agent = \mini\Mini::$mini->get(AgentInterface::class);
$agent->newConversation();

return new JsonResponse(['ok' => true]);
