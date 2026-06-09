<?php

require __DIR__ . '/../_auth.php';

header('Content-Type: application/json');

$uploadDir = \mini\Mini::$mini->root . '/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded. Send as multipart with field name "file"']);
        return;
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload failed', 'code' => $file['error']]);
        return;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'File type not allowed', 'allowed' => $allowed]);
        return;
    }

    $basename = preg_replace('/[^a-z0-9\-_.]/', '', strtolower($file['name']));
    if ($basename === '' || $basename[0] === '.') {
        $basename = uniqid() . '.' . $ext;
    }

    $target = $uploadDir . '/' . $basename;
    if (file_exists($target)) {
        $name = pathinfo($basename, PATHINFO_FILENAME);
        $i = 2;
        while (file_exists($uploadDir . '/' . $name . '-' . $i . '.' . $ext)) $i++;
        $basename = $name . '-' . $i . '.' . $ext;
        $target = $uploadDir . '/' . $basename;
    }

    move_uploaded_file($file['tmp_name'], $target);

    echo json_encode([
        'filename' => $basename,
        'url' => '/uploads/' . $basename,
        'size' => filesize($target),
    ]);
    return;
}

// GET: list uploads
$files = [];
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f[0] === '.') continue;
        $path = $uploadDir . '/' . $f;
        if (!is_file($path)) continue;
        $files[] = [
            'filename' => $f,
            'url' => '/uploads/' . $f,
            'size' => filesize($path),
            'modified' => date('c', filemtime($path)),
        ];
    }
}

echo json_encode($files);
