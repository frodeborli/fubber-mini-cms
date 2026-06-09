<?php

use mini\Http\Message\JsonResponse;
use MiniCms\Ai\AgentInterface;
use MiniCms\Ai\ClaudeCodeAgent;
use MiniCms\Content;

if (empty($_SESSION['cms_user'])) {
    return new JsonResponse(['error' => 'Unauthorized'], [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return new JsonResponse(['error' => 'Method not allowed'], [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');
$page = $input['page'] ?? null;

if ($prompt === '') {
    return new JsonResponse(['error' => 'Prompt required'], [], 400);
}

$agent = \mini\Mini::$mini->get(AgentInterface::class);
$content = \mini\Mini::$mini->get(Content::class);

$contextBlock = buildContext($agent, $content, $page);
if ($contextBlock) {
    $prompt = $contextBlock . "\n\n" . $prompt;
}

if ($page && $agent instanceof ClaudeCodeAgent) {
    $agent->setLastPage($page);
}

$agent->submitPrompt($prompt);

return new JsonResponse(['ok' => true]);

function buildContext(AgentInterface $agent, Content $content, ?string $page): ?string
{
    $lastPage = ($agent instanceof ClaudeCodeAgent) ? $agent->getLastPage() : null;
    $isNewSession = !$agent->hasSession();

    if ($isNewSession) {
        $siteConfig = $content->siteConfig();
        $lines = [];
        $lines[] = '[CMS Context]';
        $lines[] = 'You are the AI assistant for "' . ($siteConfig['name'] ?? 'My Site') . '", a website managed by MiniCMS.';
        $lines[] = 'The project root is the working directory. Content is in _content/, views in _views/, styles in _static/style.css.';
        $lines[] = 'Edit actual files to make changes — the CMS reads templates and content from disk.';

        if ($page) {
            $resolved = $content->resolve($page);
            $title = $resolved ? $resolved->getTitle() : '';
            $lines[] = '';
            $lines[] = 'The user is currently on page: ' . $page . ($title ? ' (' . $title . ')' : '');
        }

        $routes = $content->routes();
        if ($routes) {
            $lines[] = '';
            $lines[] = 'Site pages:';
            foreach ($routes as $path => $routePage) {
                $lines[] = '  ' . $path . ' — ' . $routePage->getTitle();
            }
        }

        $models = $content->models();
        if ($models) {
            $lines[] = '';
            $lines[] = 'Data models (admin CRUD at /admin/data/{slug}/):';
            foreach ($models as $slug => $entity) {
                $lines[] = '  ' . $slug . ' — ' . $entity->getPluralTitle();
            }
        }

        $lines[] = '[/CMS Context]';
        return implode("\n", $lines);
    }

    if ($page && $page !== $lastPage) {
        $resolved = $content->resolve($page);
        $title = $resolved ? $resolved->getTitle() : '';
        return '[The user has navigated to page: ' . $page . ($title ? ' (' . $title . ')' : '') . ']';
    }

    return null;
}
