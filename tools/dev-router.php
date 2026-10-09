<?php
// Local-only PHP development server. Never deploy this router.
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~^/(?:core|install|tests|tools|reports|vendor|\.git|\.local)(?:/|$)|(?:^|/)\.|\.(?:sql|log|json|lock|md)$~i', $path)) {
    http_response_code(404);
    exit('Not found');
}
$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root . '/') && is_file($file)) {
    return false;
}
if (str_starts_with($path, '/manager')) {
    $_SERVER['SCRIPT_NAME'] = '/manager/index.php';
    $_SERVER['PHP_SELF'] = '/manager/index.php';
    chdir($root . '/manager');
    require $root . '/manager/index.php';
} else {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    require $root . '/index.php';
}
