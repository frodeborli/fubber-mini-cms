<?php

namespace MiniCms\Widgets;

use MiniCms\ContentStore;

class Text extends AbstractWidget
{
    protected static function type(): string
    {
        return 'text';
    }

    protected function readValue(): mixed
    {
        $store = \mini\Mini::$mini->get(ContentStore::class);
        return $store->readHtml($this->contextPath, $this->slug);
    }

    protected function renderContent(): string
    {
        return $this->resolvedValue();
    }

    public function plain(): string
    {
        return strip_tags($this->resolvedValue());
    }
}
