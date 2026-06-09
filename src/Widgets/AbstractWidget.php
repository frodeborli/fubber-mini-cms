<?php

namespace MiniCms\Widgets;

use MiniCms\ComponentCollector;
use MiniCms\ContentStore;
use Stringable;

abstract class AbstractWidget implements Stringable
{
    protected string $file;
    protected string $path;
    protected int $pos;
    protected string $default;
    protected string $tag;
    protected mixed $value;

    public function __construct(string $file, string $path, int $pos, string $default, string $tag)
    {
        $this->file = $file;
        $this->path = $path;
        $this->pos = $pos;
        $this->default = $default;
        $this->tag = $tag;

        $store = \mini\Mini::$mini->get(ContentStore::class);
        $this->value = $store->read($file, $path);

        $this->registerComponent();
    }

    abstract protected static function type(): string;

    abstract protected function renderContent(): string;

    protected function registerComponent(): void
    {
        ComponentCollector::instance()->register(
            $this->file, $this->path, $this->pos, static::type(), $this->default, $this->value
        );
    }

    protected function resolvedValue(): string
    {
        return $this->value ?? $this->default;
    }

    protected function isPreview(): bool
    {
        return !empty($_GET['_preview']);
    }

    public function renderPublic(): string
    {
        if ($this->tag === '') {
            return $this->renderContent();
        }
        return '<' . $this->tag . '>' . $this->renderContent() . '</' . $this->tag . '>';
    }

    public function renderPreview(): string
    {
        $attrs = ' data-cms-type="' . \mini\h(static::type()) . '"'
            . ' data-cms-file="' . \mini\h($this->file) . '"'
            . ' data-cms-path="' . \mini\h($this->path) . '"'
            . ' data-cms-pos="' . $this->pos . '"';

        $tag = $this->tag ?: 'span';
        return '<' . $tag . $attrs . '>' . $this->renderContent() . '</' . $tag . '>';
    }

    public function __toString(): string
    {
        if ($this->isPreview()) {
            return $this->renderPreview();
        }
        return $this->renderPublic();
    }
}
