<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($requestPath)) {
    http_response_code(400);
    exit('Invalid request path.');
}

$routes = [
    '/api/' => [__DIR__ . '/../backend/api', true],
    '/uploads/' => [__DIR__ . '/../uploads', false],
];

foreach ($routes as $prefix => [$directory, $isPhpRoute]) {
    if (strncmp($requestPath, $prefix, strlen($prefix)) !== 0) {
        continue;
    }

    $root = realpath($directory);
    $file = realpath($directory . DIRECTORY_SEPARATOR . rawurldecode(substr($requestPath, strlen($prefix))));

    if (
        $root === false
        || $file === false
        || !is_file($file)
        || strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root . DIRECTORY_SEPARATOR)) !== 0
    ) {
        http_response_code(404);
        exit('File not found.');
    }

    if ($isPhpRoute) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
            http_response_code(404);
            exit('File not found.');
        }

        require $file;
        return true;
    }

    $mimeType = mime_content_type($file);
    if ($mimeType !== false) {
        header('Content-Type: ' . $mimeType);
    }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

return false;
