<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Closure autoloader from
// https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader-examples.md#closure-example

spl_autoload_register(function ($class) {
    $prefix = 'LizardsAndPumpkins\\DataPool\\SearchEngine\\Solr\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = __DIR__ . '/Suites/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

spl_autoload_register(function ($class) {
    $prefix = 'LizardsAndPumpkins\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = __DIR__ . '/../../vendor/lizards-and-pumpkins/catalog/tests/Unit/Suites/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
