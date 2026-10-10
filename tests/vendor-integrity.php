<?php
// Check before autoload: stale Illuminate 8 traits can cause an uncatchable fatal.
$vendor = dirname(__DIR__) . '/core/vendor/';
$obsolete = [
    'illuminate/support/Reflector.php',
    'illuminate/support/Traits/ReflectsClosures.php',
    'illuminate/support/Traits/Conditionable.php',
    'illuminate/collections/HigherOrderWhenProxy.php',
];
foreach ($obsolete as $path) {
    if (is_file($vendor . $path)) {
        fwrite(STDERR, "Mixed vendor directory: $path. Rebuild dependencies from the intended lock in a clean vendor directory.\n");
        exit(1);
    }
}
error_reporting(E_ALL & ~E_DEPRECATED);
require $vendor . 'autoload.php';
$expected = [
    Illuminate\Support\Reflector::class => 'illuminate/reflection/Reflector.php',
    Illuminate\Support\Traits\ReflectsClosures::class => 'illuminate/reflection/Traits/ReflectsClosures.php',
    Illuminate\Support\Traits\Conditionable::class => 'illuminate/conditionable/Traits/Conditionable.php',
    Illuminate\Support\HigherOrderWhenProxy::class => 'illuminate/conditionable/HigherOrderWhenProxy.php',
];
foreach ($expected as $class => $path) {
    if ((new ReflectionClass($class))->getFileName() !== realpath($vendor . $path)) {
        throw new RuntimeException('Wrong autoload source: ' . $class);
    }
}
$values = (new Illuminate\Support\Collection([1]))->when(true, fn ($items) => $items->push(2));
if ($values->all() !== [1, 2]) {
    throw new RuntimeException('Collection::when failed');
}
echo "VENDOR_INTEGRITY_OK\n";
