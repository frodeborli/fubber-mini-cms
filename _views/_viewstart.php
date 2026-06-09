<?php

$layout = '_layout.php';

$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$site = \mini\Mini::$mini->get(\MiniCms\Content::class)->siteConfig();
$siteName = $site['name'] ?? 'My Site';

if (!function_exists('query')) {
    function query(string $sql, array $params = []): \mini\Database\Query
    {
        return \mini\db()->query($sql, $params);
    }
}
