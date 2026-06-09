<?php

namespace MiniCms\Widgets;

use MiniCms\ContentStore;

class Html extends AbstractWidget
{
    protected static function type(): string
    {
        return 'html';
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
}
