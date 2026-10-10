<?php
require __DIR__ . '/bootstrap.php';
$classes = require EVO_CORE_PATH . 'vendor/composer/autoload_classmap.php';
// Upstream's unused interface references an absent AgelxNash package even on L8.
// Do not misreport this known upstream issue as a Laravel 13 regression.
$count = 0;
foreach ($classes as $class => $file) {
    if ($class !== 'EvolutionCMS\\Interfaces\\DatabaseInterface' && str_starts_with($file, EVO_CORE_PATH . 'src/') && str_starts_with($class, 'EvolutionCMS\\')) {
        class_exists($class) || interface_exists($class) || trait_exists($class);
        ++$count;
    }
}
echo "CLASS_LOAD_OK $count\n";
