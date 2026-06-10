<?php

use mini\Http\Message\JsonResponse;
use MiniCms\Ai\AgentInterface;
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

if ($agent instanceof \MiniCms\Ai\NullAgent) {
    return new JsonResponse(['error' => 'No AI agent available'], [], 503);
}

if ($agent->isProcessing()) {
    return new JsonResponse(['error' => 'Agent is already processing a request'], [], 409);
}
$content = \mini\Mini::$mini->get(Content::class);

$contextBlock = buildContext($agent, $content, $page);
if ($contextBlock) {
    $prompt = $contextBlock . "\n\n" . $prompt;
}

if ($page) {
    $agent->setLastPage($page);
}

$agent->submitPrompt($prompt);

return new JsonResponse(['ok' => true]);

function buildContext(AgentInterface $agent, Content $content, ?string $page): ?string
{
    $lastPage = $agent->getLastPage();
    $isNewSession = !$agent->hasSession();

    if ($isNewSession) {
        $siteConfig = $content->siteConfig();
        $lines = [];
        $lines[] = '[CMS Context]';
        $lines[] = 'You are the AI assistant for "' . ($siteConfig['name'] ?? 'My Site') . '", a website managed by MiniCMS.';
        $lines[] = 'The project root is the working directory. Content is in _content/, views in _views/, styles in _static/style.css.';
        $lines[] = 'Edit actual files to make changes — the CMS reads templates and content from disk.';
        $lines[] = '';
        $lines[] = 'Mini framework quick reference (read vendor/fubber/mini/CLAUDE.md for full details):';
        $lines[] = '';
        $lines[] = 'Helpers: \mini\h($s) = htmlspecialchars, \mini\render($view, $vars) = render template, \mini\db() = DatabaseInterface, \mini\redirect($url), \mini\csrf($action) = CSRF (echo in form, ->verify($_POST[\'__nonce__\']) to check).';
        $lines[] = '';
        $lines[] = 'Routes: _routes/path.php → URL /path. Return ResponseInterface, array (→ JsonResponse), or Controller. Check $_SERVER[\'REQUEST_METHOD\'] for POST. Use __DEFAULT__.php + AbstractController for sub-routes.';
        $lines[] = '';
        $lines[] = 'Models: extend mini\Database\Model. Attributes from mini\Database\Attributes\: #[Table], #[PrimaryKey(autoIncrement: true)], #[Column], #[ForeignKey(navigation: \'prop\')], #[CreatedAt], #[UpdatedAt]. Metadata from mini\Metadata\Attributes\: #[Title], #[Description]. Validators from mini\Validator\Attributes\: #[Required], #[MaxLength(n)], #[MinLength(n)], #[Pattern(regex)], #[Format(email)].';
        $lines[] = 'CRUD: Model::queryUnsafe(), Model::findUnsafe($id), $model->saveUnsafe(), $model->deleteUnsafe(). Validation is manual: $err = \mini\validator(Model::class)->isInvalid($obj); if ($err) throw new \mini\Validator\ValidationException($err);';
        $lines[] = '';
        $lines[] = 'Migrations: vendor/bin/mini migrations (run pending), vendor/bin/mini migrations make <name> (create file). File in _migrations/YYYY_MM_DD_HHMMSS_name.php, returns anon class with up(DatabaseInterface $db)/down(DatabaseInterface $db).';
        $lines[] = '';
        $lines[] = 'Mail: \mini\mailer()->send($email). Build with (new \mini\Mail\Email())->withFrom($addr)->withAddedTo($addr)->withSubject($s)->withTextBody($text)->withHtmlBody($html). All methods immutable (return new instance). Default transport is PHP mail().';
        $lines[] = '';
        $lines[] = 'Responses: new JsonResponse($data, [], $status), new HtmlResponse($html), new Response($body, $headers, $status). All in mini\Http\Message\.';
        $lines[] = '';
        $lines[] = 'CMS entities: register in _content/models.php. new Entity(Model::class, icon: \'bi-icon\', pluralTitle: \'Title\') gives auto CRUD at /admin/data/{slug}/.';

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
