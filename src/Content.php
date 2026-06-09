<?php

namespace MiniCms;

class Content
{
    private string $basePath;
    private ?array $routes = null;
    private ?array $models = null;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function siteConfig(): array
    {
        $file = $this->basePath . '/site.json';
        if (!is_file($file)) {
            return ['name' => 'My Site', 'description' => ''];
        }
        return json_decode(file_get_contents($file), true) ?: [];
    }

    public function saveSiteConfig(array $data): void
    {
        file_put_contents(
            $this->basePath . '/site.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    /**
     * @return array<string, AbstractPage>
     */
    public function routes(): array
    {
        if ($this->routes === null) {
            $phpFile = $this->basePath . '/routes.php';
            $jsonFile = $this->basePath . '/routes.json';
            if (is_file($phpFile)) {
                $this->routes = require $phpFile;
            } elseif (is_file($jsonFile)) {
                $raw = json_decode(file_get_contents($jsonFile), true) ?: [];
                $this->routes = [];
                foreach ($raw as $path => $meta) {
                    $vars = array_diff_key($meta, array_flip(['view', 'title', 'nav']));
                    $page = new Page($meta['view'], $meta['title'] ?? '', $vars);
                    if (isset($meta['nav']) && $meta['nav'] === false) {
                        $page = $page->withNavVisibility(false);
                    }
                    $this->routes[$path] = $page;
                }
            } else {
                $this->routes = [];
            }
        }
        return $this->routes;
    }

    /**
     * Match a request path and return a ready-to-dispatch page, or null.
     */
    public function resolve(string $path): ?AbstractPage
    {
        $routes = $this->routes();

        if (isset($routes[$path])) {
            return $routes[$path];
        }

        $pathSegments = explode('/', trim($path, '/'));
        foreach ($routes as $pattern => $page) {
            if (strpos($pattern, '{') === false) continue;

            $patternSegments = explode('/', trim($pattern, '/'));
            if (count($patternSegments) !== count($pathSegments)) continue;

            $params = [];
            $match = true;
            for ($i = 0; $i < count($patternSegments); $i++) {
                $ps = $patternSegments[$i];
                if (str_starts_with($ps, '{') && str_ends_with($ps, '}')) {
                    $params[substr($ps, 1, -1)] = $pathSegments[$i];
                } elseif ($ps !== $pathSegments[$i]) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return $page->withRouteVariables($params);
            }
        }

        return null;
    }

    /**
     * Find the route page object by exact path.
     */
    public function findPage(string $path): ?AbstractPage
    {
        return $this->routes()[$path] ?? null;
    }

    /**
     * Find raw route metadata by exact path (legacy compat for templates
     * that read route config like the members list).
     */
    public function findRoute(string $path): ?array
    {
        $routes = $this->routes();
        if (!isset($routes[$path])) return null;
        $page = $routes[$path];
        if ($page instanceof Page) {
            return ['title' => $page->getTitle()] + $page->getVars();
        }
        return ['title' => $page->getTitle()];
    }

    public function saveRoutes(array $routes): void
    {
        $this->routes = $routes;
        file_put_contents(
            $this->basePath . '/routes.json',
            json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    /**
     * Build a tree from all route paths for the admin sidebar.
     */
    public function routeTree(): array
    {
        $routes = $this->routes();
        $tree = [];

        foreach ($routes as $path => $page) {
            if (strpos($path, '{') !== false) continue;

            if ($path === '/') {
                $tree['/'] = ['label' => '/', 'path' => '/'];
                continue;
            }

            $segments = explode('/', trim($path, '/'));
            $node = &$tree;

            for ($i = 0; $i < count($segments); $i++) {
                $seg = $segments[$i];
                $isLast = ($i === count($segments) - 1);

                if (!isset($node[$seg])) {
                    $node[$seg] = ['label' => $seg, 'children' => []];
                }

                if ($isLast) {
                    $node[$seg]['path'] = $path;
                } else {
                    $node = &$node[$seg]['children'];
                }
            }
            unset($node);
        }

        return $this->flattenTree($tree);
    }

    private function flattenTree(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if (!empty($node['children'])) {
                $node['children'] = $this->flattenTree($node['children']);
            }
            $result[] = $node;
        }
        return $result;
    }

    /**
     * @return array<string, Entity>
     */
    public function models(): array
    {
        if ($this->models === null) {
            $file = $this->basePath . '/models.php';
            if (is_file($file)) {
                $this->models = require $file;
            } else {
                $this->models = [];
            }
        }
        return $this->models;
    }

    public function findSlugByModelClass(string $class): ?string
    {
        foreach ($this->models() as $slug => $entity) {
            if ($entity->getModelClass() === $class) {
                return $slug;
            }
        }
        return null;
    }

    public function nav(string $currentPath = ''): array
    {
        $nav = [];
        foreach ($this->routes() as $path => $page) {
            if (strpos($path, '{') !== false) continue;
            if (!$page->isVisibleInNav()) continue;

            $nav[] = [
                'title' => $page->getTitle(),
                'url' => $path,
                'active' => $path === $currentPath,
            ];
        }
        return $nav;
    }
}
