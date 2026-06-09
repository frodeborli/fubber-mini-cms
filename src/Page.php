<?php

namespace MiniCms;

use mini\Http\Message\HtmlResponse;
use Psr\Http\Message\ResponseInterface;

class Page extends AbstractPage
{
    protected string $view;
    protected string $title;
    protected array $vars;

    public function __construct(string $view, string $title = '', array $vars = [])
    {
        $this->view = str_ends_with($view, '.php') ? $view : $view . '.php';
        $this->title = $title;
        $this->vars = $vars;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    protected function isSamePage(AbstractPage $other): bool
    {
        return $other instanceof static
            && $this->view === $other->view
            && $this->routeVars === $other->routeVars;
    }

    /**
     * Override to handle form submissions or other request logic.
     * Only called when the view will actually be rendered (public view
     * or preview iframe), never on the admin wrapper request.
     *
     * Return a ResponseInterface to short-circuit rendering (e.g. redirect).
     */
    protected function handleRequest(): ?ResponseInterface
    {
        return null;
    }

    public function getResponse(): ResponseInterface
    {
        $loggedIn = !empty($_SESSION['cms_user']);
        $isPreview = !empty($_GET['_preview']);

        if (!$loggedIn || $isPreview) {
            $response = $this->handleRequest();
            if ($response !== null) {
                if ($isPreview && $this->isRedirect($response)) {
                    return $this->topRedirect($response);
                }
                return $response;
            }
            $vars = array_merge($this->vars, $this->routeVars, ['page' => $this]);
            $html = \mini\render($this->view, $vars);
            return new HtmlResponse($html);
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $iframePath = strtok($uri, '?');
        $queryString = parse_url($uri, PHP_URL_QUERY) ?? '';
        parse_str($queryString, $query);
        $query['_preview'] = '1';
        $iframeUrl = $iframePath . '?' . http_build_query($query);

        $isFormSubmit = $_SERVER['REQUEST_METHOD'] !== 'GET';

        if ($isFormSubmit) {
            $components = [];
        } else {
            $vars = array_merge($this->vars, $this->routeVars, ['page' => $this]);
            $collector = ComponentCollector::instance();
            $collector->startCollecting();
            \mini\render($this->view, $vars);
            $components = $collector->stopCollecting();

            foreach ($components as &$group) {
                uasort($group, fn($a, $b) => $a['pos'] <=> $b['pos']);
            }
            unset($group);
        }

        $shellVars = [
            'iframeUrl' => $isFormSubmit ? 'about:blank' : $iframeUrl,
            'components' => $components,
            'currentPath' => $iframePath,
        ];

        if ($isFormSubmit) {
            $shellVars['replayMethod'] = $_SERVER['REQUEST_METHOD'];
            $shellVars['replayAction'] = $iframeUrl;
            $shellVars['replayData'] = $this->flattenPostData(iterator_to_array($_POST));
        }

        $html = \mini\render('cms/admin-shell.php', $shellVars);

        return new HtmlResponse($html);
    }

    private function isRedirect(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();
        return $status >= 300 && $status < 400 && $response->hasHeader('Location');
    }

    private function topRedirect(ResponseInterface $response): ResponseInterface
    {
        $url = $response->getHeader('Location')[0];
        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return new HtmlResponse(
            "<!doctype html><script>top.location.href=" . json_encode($url) . ";</script>"
        );
    }

    private function flattenPostData(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenPostData($value, $name));
            } else {
                $flat[] = ['name' => $name, 'value' => $value];
            }
        }
        return $flat;
    }
}
