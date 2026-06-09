<?php

// Shared auth gate for admin routes.
// Include this at the top of every admin route file.
// ADMIN_TOKEN must be set in site.ini [env] or .env.

(function () {
    $token = getenv('ADMIN_TOKEN');
    if (!$token) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ADMIN_TOKEN not configured']);
        exit;
    }

    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ') || substr($auth, 7) !== $token) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
})();
