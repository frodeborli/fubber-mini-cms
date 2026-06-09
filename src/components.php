<?php

use MiniCms\CmsContext;
use MiniCms\Widgets\Text;
use MiniCms\Widgets\Html;
use MiniCms\Widgets\Image;

function cms_text(string $slug, string $label, int $pos, string $default = '', string $tag = ''): Text
{
    return new Text($slug, $label, $pos, $default, $tag);
}

function cms_html(string $slug, string $label, int $pos, string $default = '', string $tag = ''): Html
{
    return new Html($slug, $label, $pos, $default, $tag);
}

function cms_image(string $slug, string $label, int $pos, string $default = '', string $tag = '', string $alt = '', string $aspect = ''): Image
{
    return new Image($slug, $label, $pos, $default, $tag, $alt, $aspect);
}

function cms_context_start(string $path, string $label): void
{
    CmsContext::instance()->push($path, $label);
}

function cms_context_end(): void
{
    CmsContext::instance()->pop();
}

function cms_partial(string $view, string $label, array $vars = []): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
    $slug = trim($slug, '-');

    $ctx = CmsContext::instance();
    $ctx->push($slug, $label);
    try {
        $html = \mini\render($view, $vars);
    } finally {
        $ctx->pop();
    }
    return $html;
}
