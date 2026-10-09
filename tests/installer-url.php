<?php
require dirname(__DIR__) . '/install/src/functions.php';
$cases = [
    [['HTTP_HOST' => '127.0.0.1:18533', 'SCRIPT_NAME' => '/install/index.php'], 'http://127.0.0.1:18533/'],
    [['HTTP_HOST' => 'example.test', 'SCRIPT_NAME' => '/nested/install/index.php', 'HTTPS' => 'on'], 'https://example.test/nested/'],
    [['HTTP_HOST' => 'example.test:8443', 'SCRIPT_NAME' => '/a/b/install/index.php', 'REQUEST_SCHEME' => 'https'], 'https://example.test:8443/a/b/'],
    [['HTTP_HOST' => '[::1]:8080', 'SCRIPT_NAME' => '/install/index.php', 'HTTPS' => 'off'], 'http://[::1]:8080/'],
];
foreach ($cases as [$server, $expected]) {
    $actual = installerSiteUrl($server);
    if ($actual !== $expected || !parse_url($actual, PHP_URL_SCHEME)) {
        throw new RuntimeException('Invalid installer URI: ' . $actual);
    }
}
echo 'INSTALLER_URL_COMPLETE ' . count($cases) . "\n";
