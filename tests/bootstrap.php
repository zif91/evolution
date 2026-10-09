<?php
// Integration tests deliberately require the isolated local database.
$credentialsPath = dirname(__DIR__) . '/.local/credentials.json';
if (!is_file($credentialsPath)) {
    throw new RuntimeException('Create the isolated local test installation first.');
}
$credentials = json_decode(file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', true);
define('IN_INSTALL_MODE', false);
require dirname(__DIR__) . '/core/bootstrap.php';
$modx = evo();
if ($modx['db']->connection()->getDatabaseName() !== $credentials['database'] || $credentials['database'] !== 'evolution_lara13_test') {
    throw new RuntimeException('Refusing to run against any database other than evolution_lara13_test.');
}
restore_exception_handler();
restore_error_handler();
