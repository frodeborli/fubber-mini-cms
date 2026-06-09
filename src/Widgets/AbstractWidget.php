<?php

namespace MiniCms\Widgets;

use MiniCms\CmsContext;
use MiniCms\ComponentCollector;
use MiniCms\ContentStore;
use Stringable;

abstract class AbstractWidget implements Stringable
{
    protected string $slug;
    protected string $label;
    protected int $pos;
    protected string $default;
    protected string $tagSelector;
    protected mixed $value;
    protected string $contextPath;
    protected string $contextLabel;

    public function __construct(string $slug, string $label, int $pos, string $default, string $tagSelector)
    {
        $this->slug = $slug;
        $this->label = $label;
        $this->pos = $pos;
        $this->default = $default;
        $this->tagSelector = $tagSelector;

        $ctx = CmsContext::instance();
        $this->contextPath = $ctx->currentPath();
        $this->contextLabel = $ctx->innerLabel();

        $this->value = $this->readValue();
        $this->registerComponent();
    }

    abstract protected static function type(): string;

    abstract protected function renderContent(): string;

    abstract protected function readValue(): mixed;

    protected function registerComponent(): void
    {
        ComponentCollector::instance()->register(
            $this->contextPath,
            $this->contextLabel,
            $this->slug,
            $this->label,
            $this->pos,
            static::type(),
            $this->default,
            $this->value,
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

    /**
     * Parse a CSS selector-style tag string into tag name, id, and classes.
     * "h1.blue#hero.large" → ['tag' => 'h1', 'id' => 'hero', 'classes' => ['blue', 'large']]
     */
    protected static function parseSelector(string $selector): array
    {
        $tag = 'div';
        $id = null;
        $classes = [];

        preg_match_all('/[#.]?[^#.]+/', $selector, $tokens);
        foreach ($tokens[0] as $token) {
            if ($token[0] === '#') $id = substr($token, 1);
            elseif ($token[0] === '.') $classes[] = substr($token, 1);
            else $tag = $token;
        }

        return ['tag' => $tag, 'id' => $id, 'classes' => $classes];
    }

    protected function openTag(string $extraAttrs = ''): string
    {
        if ($this->tagSelector === '') return '';

        $parsed = self::parseSelector($this->tagSelector);
        $html = '<' . $parsed['tag'];
        if ($parsed['id']) $html .= ' id="' . \mini\h($parsed['id']) . '"';
        if ($parsed['classes']) $html .= ' class="' . \mini\h(implode(' ', $parsed['classes'])) . '"';
        if ($extraAttrs) $html .= $extraAttrs;
        $html .= '>';
        return $html;
    }

    protected function closeTag(): string
    {
        if ($this->tagSelector === '') return '';
        $parsed = self::parseSelector($this->tagSelector);
        return '</' . $parsed['tag'] . '>';
    }

    protected function tagName(): string
    {
        if ($this->tagSelector === '') return '';
        return self::parseSelector($this->tagSelector)['tag'];
    }

    public function renderPublic(): string
    {
        $content = $this->renderContent();
        if ($this->tagSelector === '') return $content;
        return $this->openTag() . $content . $this->closeTag();
    }

    public function renderPreview(): string
    {
        $attrs = ' data-cms-type="' . \mini\h(static::type()) . '"'
            . ' data-cms-context="' . \mini\h($this->contextPath) . '"'
            . ' data-cms-slug="' . \mini\h($this->slug) . '"'
            . ' data-cms-pos="' . $this->pos . '"';

        $tag = $this->tagSelector ?: (static::type() === 'html' ? 'div' : 'span');
        $parsed = self::parseSelector($tag);
        $html = '<' . $parsed['tag'];
        if ($parsed['id']) $html .= ' id="' . \mini\h($parsed['id']) . '"';
        if ($parsed['classes']) $html .= ' class="' . \mini\h(implode(' ', $parsed['classes'])) . '"';
        $html .= $attrs . '>' . $this->renderContent() . '</' . $parsed['tag'] . '>';
        return $html;
    }

    public function __toString(): string
    {
        if ($this->isPreview()) {
            return $this->renderPreview();
        }
        return $this->renderPublic();
    }
}
