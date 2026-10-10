<?php
require dirname(__DIR__) . '/install/src/functions.php';
require dirname(__DIR__) . '/core/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

if (PHP_OS_FAMILY === 'Windows') {
    $oldUrl = 'http://evolara13.local' . rtrim(dirname(dirname('/install/index.php')), '/.') . '/';
    try {
        Request::create($oldUrl);
        throw new RuntimeException('Expected the old Windows root URI to fail: ' . $oldUrl);
    } catch (BadRequestException $e) {
        if ($e->getMessage() !== 'Invalid URI: Host is malformed.') {
            throw $e;
        }
        echo "WINDOWS_OLD_URI_REPRODUCED " . $oldUrl . "\n";
    }
}
$cases = [
    [['HTTP_HOST' => '127.0.0.1:18533', 'SCRIPT_NAME' => '/install/index.php'], 'http://127.0.0.1:18533/'],
    [['HTTP_HOST' => 'example.test', 'SCRIPT_NAME' => '/nested/install/index.php', 'HTTPS' => 'on'], 'https://example.test/nested/'],
    [['HTTP_HOST' => 'example.test:8443', 'SCRIPT_NAME' => '/a/b/install/index.php', 'REQUEST_SCHEME' => 'https'], 'https://example.test:8443/a/b/'],
    [['HTTP_HOST' => '[::1]:8080', 'SCRIPT_NAME' => '/install/index.php', 'HTTPS' => 'off'], 'http://[::1]:8080/'],
    [['HTTP_HOST' => 'evolara13.local', 'SCRIPT_NAME' => '/install/index.php'], 'http://evolara13.local/'],
    [['HTTP_HOST' => 'evolara13.local', 'SCRIPT_NAME' => '\\install\\index.php'], 'http://evolara13.local/'],
    [['HTTP_HOST' => 'evolara13.local', 'SCRIPT_NAME' => '\\nested\\install\\index.php'], 'http://evolara13.local/nested/'],
    [['HTTP_HOST' => 'evolara13.local:8080', 'SCRIPT_NAME' => '/nested/install/index.php', 'HTTPS' => 'off'], 'http://evolara13.local:8080/nested/'],
];
foreach ($cases as [$server, $expected]) {
    $actual = installerSiteUrl($server);
    if ($actual !== $expected || !parse_url($actual, PHP_URL_SCHEME)) {
        throw new RuntimeException('Invalid installer URI: ' . $actual);
    }
    Request::create($actual);
}
echo 'INSTALLER_URL_COMPLETE ' . count($cases) . "\n";
