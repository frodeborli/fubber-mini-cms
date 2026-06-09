<?php

use MiniCms\Widgets\Text;
use MiniCms\Widgets\Html;
use MiniCms\Widgets\Image;

function cms_text(string $file, string $path, int $pos, string $default = '', string $tag = ''): Text
{
    return new Text($file, $path, $pos, $default, $tag);
}

function cms_html(string $file, string $path, int $pos, string $default = '', string $tag = ''): Html
{
    return new Html($file, $path, $pos, $default, $tag);
}

function cms_image(string $file, string $path, int $pos, string $default = '', string $tag = '', string $alt = '', string $aspect = ''): Image
{
    return new Image($file, $path, $pos, $default, $tag, $alt, $aspect);
}
