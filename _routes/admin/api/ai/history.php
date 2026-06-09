<?php

use mini\Http\Message\JsonResponse;
use MiniCms\Ai\AgentInterface;

if (empty($_SESSION['cms_user'])) {
    return new JsonResponse(['error' => 'Unauthorized'], [], 401);
}

$agent = \mini\Mini::$mini->get(AgentInterface::class);

return new JsonResponse([
    'messages' => $agent->getHistory(),
    'hasSession' => $agent->hasSession(),
]);
