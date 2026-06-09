<?php

use mini\Http\Message\HtmlResponse;
use mini\Http\Message\Response;
use mini\Session\SessionInterface;

// Logout
if (!empty($_GET['logout'])) {
    \mini\Mini::$mini->get(SessionInterface::class)->destroy();
    return new Response('', ['Location' => '/'], 302);
}

// Already logged in
if (!empty($_SESSION['cms_user'])) {
    return new Response('', ['Location' => '/'], 302);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $expectedPassword = getenv('CMS_PASSWORD');

    if (!$expectedPassword) {
        $error = 'CMS_PASSWORD not configured. Set it in site.ini [env].';
    } elseif (hash_equals($expectedPassword, $password)) {
        $_SESSION['cms_user'] = 'admin';
        $redirect = $_GET['redirect'] ?? '/';
        return new Response('', ['Location' => $redirect], 302);
    } else {
        $error = 'Invalid password.';
    }
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/admin/vendor/source-sans-3/index.css">
    <link rel="stylesheet" href="/admin/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/vendor/adminlte/adminlte.min.css">
</head>
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h1 class="h4 mb-0">Sign In</h1>
            </div>
            <div class="card-body login-card-body">
HTML;

if ($error) {
    $html .= '<div class="alert alert-danger alert-sm py-2">' . \mini\h($error) . '</div>';
}

$html .= <<<HTML
                <form method="post">
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" autofocus>
                        <div class="input-group-text"><i class="bi bi-lock-fill"></i></div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="/admin/dist/cms.min.js"></script>
</body>
</html>
HTML;

return new HtmlResponse($html);
