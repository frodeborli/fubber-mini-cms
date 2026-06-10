<?php

use mini\Http\Message\JsonResponse;

if (empty($_SESSION['cms_user'])) {
    return new JsonResponse(['error' => 'Unauthorized'], [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return new JsonResponse(['error' => 'Method not allowed'], [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$current = $input['current'] ?? '';
$new = $input['new'] ?? '';

if ($current === '' || $new === '') {
    return new JsonResponse(['error' => 'Both fields are required'], [], 400);
}

if (strlen($new) < 8) {
    return new JsonResponse(['error' => 'New password must be at least 8 characters'], [], 400);
}

$hashFile = \mini\Mini::$mini->root . '/.cms/password.hash';
$verified = false;

if (is_file($hashFile)) {
    $verified = password_verify($current, trim(file_get_contents($hashFile)));
} else {
    $envPassword = getenv('CMS_PASSWORD');
    $verified = $envPassword && hash_equals($envPassword, $current);
}

if (!$verified) {
    return new JsonResponse(['error' => 'Current password is incorrect'], [], 403);
}

$dir = dirname($hashFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($hashFile, password_hash($new, PASSWORD_BCRYPT) . "\n");

return new JsonResponse(['ok' => true]);
