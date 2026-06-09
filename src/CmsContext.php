<?php

namespace MiniCms;

use mini\Util\Path;

class CmsContext
{
    private static ?self $instance = null;

    /** @var array{path: string, label: string}[] */
    private array $stack = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function push(string $path, string $label): void
    {
        if (str_starts_with($path, '/')) {
            $resolved = (string) new Path('_' . ltrim($path, '/'));
        } else {
            $resolved = (string) Path::create($this->currentPath(), '_' . $path);
        }

        $this->stack[] = ['path' => $resolved, 'label' => $label];
    }

    public function pop(): void
    {
        if (empty($this->stack)) {
            throw new \LogicException('cms_context_end() called without matching cms_context_start()');
        }
        array_pop($this->stack);
    }

    public function setPageContext(string $urlPath): void
    {
        $this->stack = [];
        $trimmed = trim($urlPath, '/');
        $dirPath = $trimmed === '' ? 'home' : $trimmed;
        $this->stack[] = ['path' => $dirPath, 'label' => 'Page'];
    }

    public function currentPath(): string
    {
        if (empty($this->stack)) {
            throw new \LogicException('No CMS context active — cms_context_start() or page rendering required');
        }
        return end($this->stack)['path'];
    }

    /**
     * Returns the full label path for the admin UI, e.g. "Page > Features > Card 1"
     */
    public function currentLabel(): string
    {
        return implode(' > ', array_column($this->stack, 'label'));
    }

    /**
     * Returns just the innermost context label.
     */
    public function innerLabel(): string
    {
        if (empty($this->stack)) return '';
        return end($this->stack)['label'];
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    public function assertBalanced(): void
    {
        if (count($this->stack) > 1) {
            $unclosed = array_slice($this->stack, 1);
            $labels = array_column($unclosed, 'label');
            throw new \LogicException('Unclosed CMS context(s): ' . implode(', ', $labels) . ' — missing cms_context_end() call(s)');
        }
    }
}
