<?php
error_reporting(E_ALL & ~E_DEPRECATED);
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/evo-installer-env-' . bin2hex(random_bytes(6));
mkdir($fixture . '/vendor', 0700, true);
mkdir($fixture . '/custom', 0700, true);
file_put_contents($fixture . '/vendor/autoload.php', '<?php require_once ' . var_export($root . '/core/vendor/autoload.php', true) . ';');
define('EVO_CORE_PATH', $fixture . '/');

$check = static function ($actual, $expected, $label) {
    if ($actual !== $expected) {
        throw new RuntimeException($label);
    }
};
try {
    // A new installation has no .env and must still show the wizard.
    require $root . '/install/src/bootstrap.php';
    $check(env('EVO_INSTALLER_TEST_MISSING', 'fallback'), 'fallback', 'Missing .env fallback');
    $_ENV['EVO_INSTALLER_TEST_EXISTING'] = 'server-value';
    file_put_contents($fixture . '/custom/.env', <<<'ENV'
EVO_INSTALLER_TEST_HOST=mysql
EVO_INSTALLER_TEST_DATABASE="${EVO_INSTALLER_TEST_HOST}_site"
EVO_INSTALLER_TEST_PASSWORD='test # пароль = value'
EVO_INSTALLER_TEST_EXISTING=file-value
EVO_INSTALLER_TEST_BOOL=false
ENV);
    require $root . '/install/src/bootstrap.php';
    require $root . '/install/src/functions.php';
    $check(env('EVO_INSTALLER_TEST_HOST'), 'mysql', 'Installer reads .env');
    $check(env('EVO_INSTALLER_TEST_DATABASE'), 'mysql_site', 'Dotenv interpolation');
    $check(env('EVO_INSTALLER_TEST_PASSWORD'), 'test # пароль = value', 'Quoted password');
    $check(env('EVO_INSTALLER_TEST_EXISTING'), 'server-value', 'Existing environment is preserved');
    $check(env('EVO_INSTALLER_TEST_BOOL'), false, 'Application-compatible boolean');
    $check(installerDatabaseDsn('mysql', 'mysql', 'site', 3307), 'mysql:host=mysql;port=3307;dbname=site', 'Config driver and port');
    $check(installerDatabaseDsn('mysql', 'mysql:3308', 'site'), 'mysql:host=mysql;port=3308;dbname=site', 'Wizard host:port');
    $check(installerDatabaseDsn('pgsql', 'database', 'site', 5433), 'pgsql:host=database;port=5433;dbname=site', 'PostgreSQL DSN');
    echo "INSTALLER_ENVIRONMENT_COMPLETE 9 checks\n";
} finally {
    @unlink($fixture . '/custom/.env');
    unlink($fixture . '/vendor/autoload.php');
    rmdir($fixture . '/vendor');
    rmdir($fixture . '/custom');
    rmdir($fixture);
}
