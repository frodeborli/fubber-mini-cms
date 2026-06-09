<?php

namespace MiniCms\Widgets;

class Text extends AbstractWidget
{
    protected static function type(): string
    {
        return 'text';
    }

    protected function renderContent(): string
    {
        return \mini\h($this->resolvedValue());
    }

    public function renderPreview(): string
    {
        $attrs = ' data-cms-type="text"'
            . ' data-cms-file="' . \mini\h($this->file) . '"'
            . ' data-cms-path="' . \mini\h($this->path) . '"'
            . ' data-cms-pos="' . $this->pos . '"';

        $tag = $this->tag ?: 'span';
        return '<' . $tag . $attrs . '>' . $this->renderContent() . '</' . $tag . '>';
    }

    /**
     * Return the plain text value (for use in <title> etc. where no wrapper is wanted).
     */
    public function plain(): string
    {
        return \mini\h($this->resolvedValue());
    }
}
