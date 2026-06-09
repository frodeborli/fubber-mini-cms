<?php

namespace MiniCms\Widgets;

use MiniCms\ComponentCollector;
use MiniCms\ContentStore;
use MiniCms\ImageProcessor;

class Image extends AbstractWidget
{
    private string $alt;
    private string $aspect;

    public function __construct(string $slug, string $label, int $pos, string $default, string $tagSelector, string $alt = '', string $aspect = '')
    {
        $this->alt = $alt;
        $this->aspect = $aspect;
        parent::__construct($slug, $label, $pos, $default, $tagSelector);
    }

    protected static function type(): string
    {
        return 'image';
    }

    protected function readValue(): mixed
    {
        $store = \mini\Mini::$mini->get(ContentStore::class);
        return $store->readWidget($this->contextPath, $this->slug);
    }

    protected function registerComponent(): void
    {
        $meta = [];
        if ($this->aspect !== '') {
            $meta['aspect'] = $this->aspect;
        }
        ComponentCollector::instance()->register(
            $this->contextPath,
            $this->contextLabel,
            $this->slug,
            $this->label,
            $this->pos,
            static::type(),
            $this->default,
            $this->value,
            $meta,
        );
    }

    private function imgAttrs(): string
    {
        $src = $this->resolvedValue();
        if ($src === '') return '';

        if ($this->aspect !== '') {
            $srcset = $this->buildSrcset($src);
            if ($srcset) {
                return ' src="' . \mini\h($srcset['fallback']) . '"'
                    . ' srcset="' . \mini\h($srcset['srcset']) . '"'
                    . ' sizes="(max-width: 640px) 100vw, 960px"'
                    . ' alt="' . \mini\h($this->alt) . '"';
            }
        }

        return ' src="' . \mini\h($src) . '" alt="' . \mini\h($this->alt) . '"';
    }

    private function aspectStyle(): string
    {
        if ($this->aspect === '') return '';
        $parts = preg_split('/[x:\/]/', $this->aspect);
        if (count($parts) === 2 && (float)$parts[0] > 0 && (float)$parts[1] > 0) {
            return ' style="aspect-ratio:' . $parts[0] . '/' . $parts[1] . '"';
        }
        return '';
    }

    protected function renderContent(): string
    {
        $attrs = $this->imgAttrs();
        if ($attrs !== '') return '<img' . $attrs . $this->aspectStyle() . '>';

        if ($this->aspect !== '') {
            return '<img src="/admin/placeholder/' . \mini\h($this->aspect) . '" alt="' . \mini\h($this->alt) . '"' . $this->aspectStyle() . '>';
        }

        return '';
    }

    private function buildSrcset(string $src): ?array
    {
        $relative = preg_replace('#^/uploads/#', '', $src);
        if ($relative === $src) return null;

        $processor = \mini\Mini::$mini->get(ImageProcessor::class);
        $versions = $processor->getVersions($relative);

        if (!isset($versions[$this->aspect]) || empty($versions[$this->aspect]['widths'])) {
            return null;
        }

        $widths = $versions[$this->aspect]['widths'];
        $parts = [];
        foreach ($widths as $w => $url) {
            $parts[] = $url . ' ' . $w . 'w';
        }

        $fallbackWidth = min(960, max(array_keys($widths)));
        $fallback = $widths[$fallbackWidth] ?? end($widths);

        return ['srcset' => implode(', ', $parts), 'fallback' => $fallback];
    }

    public function renderPublic(): string
    {
        $content = $this->renderContent();
        if ($content === '' || $this->tagSelector === '') return $content;
        return $this->openTag() . $content . $this->closeTag();
    }

    public function renderPreview(): string
    {
        $src = $this->resolvedValue();
        $cmsAttrs = ' data-cms-type="image"'
            . ' data-cms-context="' . \mini\h($this->contextPath) . '"'
            . ' data-cms-slug="' . \mini\h($this->slug) . '"'
            . ' data-cms-pos="' . $this->pos . '"'
            . ' data-cms-src="' . \mini\h($src) . '"';
        if ($this->aspect !== '') {
            $cmsAttrs .= ' data-cms-aspect="' . \mini\h($this->aspect) . '"';
        }

        $imgAttrs = $this->imgAttrs();
        if ($imgAttrs === '') {
            $placeholderSrc = $this->aspect !== ''
                ? '/admin/placeholder/' . \mini\h($this->aspect)
                : '/admin/placeholder/1x1';
            $imgAttrs = ' src="' . $placeholderSrc . '" alt="' . \mini\h($this->alt) . '"';
        }

        $img = '<img' . $cmsAttrs . $imgAttrs . $this->aspectStyle() . '>';
        if ($this->tagSelector === '') return $img;
        return $this->openTag() . $img . $this->closeTag();
    }
}
