<?php

require __DIR__ . '/../_auth.php';

use MiniCms\Content;
use mini\Mini;

header('Content-Type: application/json');

$content = Mini::$mini->get(Content::class);

echo json_encode($content->listPages());
