<?php

namespace MiniCms\Widgets;

use MiniCms\ComponentCollector;
use MiniCms\ImageProcessor;

class Image extends AbstractWidget
{
    private string $alt;
    private string $aspect;

    public function __construct(string $file, string $path, int $pos, string $default, string $tag, string $alt = '', string $aspect = '')
    {
        $this->alt = $alt;
        $this->aspect = $aspect;
        parent::__construct($file, $path, $pos, $default, $tag);
    }

    protected static function type(): string
    {
        return 'image';
    }

    protected function registerComponent(): void
    {
        $meta = [];
        if ($this->aspect !== '') {
            $meta['aspect'] = $this->aspect;
        }
        ComponentCollector::instance()->register(
            $this->file, $this->path, $this->pos, static::type(), $this->default, $this->value, $meta
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
        if ($content === '' || $this->tag === '') {
            return $content;
        }
        return '<' . $this->tag . '>' . $content . '</' . $this->tag . '>';
    }

    public function renderPreview(): string
    {
        $src = $this->resolvedValue();
        $cmsAttrs = ' data-cms-type="image"'
            . ' data-cms-file="' . \mini\h($this->file) . '"'
            . ' data-cms-path="' . \mini\h($this->path) . '"'
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
        if ($this->tag === '') {
            return $img;
        }
        return '<' . $this->tag . '>' . $img . '</' . $this->tag . '>';
    }
}
