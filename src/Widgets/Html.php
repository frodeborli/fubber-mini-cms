<?php

namespace MiniCms\Widgets;

class Html extends AbstractWidget
{
    protected static function type(): string
    {
        return 'html';
    }

    protected function renderContent(): string
    {
        return $this->resolvedValue();
    }

    public function renderPreview(): string
    {
        $attrs = ' data-cms-type="html"'
            . ' data-cms-file="' . \mini\h($this->file) . '"'
            . ' data-cms-path="' . \mini\h($this->path) . '"'
            . ' data-cms-pos="' . $this->pos . '"';

        $tag = $this->tag ?: 'div';
        return '<' . $tag . $attrs . '>' . $this->renderContent() . '</' . $tag . '>';
    }
}
