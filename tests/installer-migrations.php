<?php
/** Run against a disposable empty database named evo_install_test_* only. */
define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', false);
define('MODX_BASE_PATH', dirname(__DIR__) . '/');
define('MODX_SITE_URL', 'http://localhost/');
define('EVO_CLI_USER', 0);
define('MODX_CLI', true);
define('IN_INSTALL_MODE', true);
require dirname(__DIR__) . '/core/bootstrap.php';
require dirname(__DIR__) . '/install/src/migrations/prepare.php';
restore_exception_handler();
restore_error_handler();
$db = evo()->make('db')->connection();
$expected = getenv('EVO_INSTALL_TEST_DATABASE');
if (!$expected || !str_starts_with($expected, 'evo_install_test_') || $db->getDatabaseName() !== $expected) {
    throw new RuntimeException('Set EVO_INSTALL_TEST_DATABASE to the exact disposable evo_install_test_* database.');
}
$schema = $db->getSchemaBuilder();
$_POST['database_type'] = $db->getDriverName();
if ($schema->hasTable('users') || $schema->hasTable('migrations_install')) {
    throw new RuntimeException('Installer regression requires an empty disposable database.');
}
$checks = 0;
function expectInstall(bool $condition, string $message): void {
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . "\n";
    $checks++;
}
$run = static function (): void {
    $status = \EvolutionCMS\Facades\Console::call('migrate', [
        '--path' => '../install/stubs/migrations', '--force' => true,
    ]);
    if ($status !== 0) {
        throw new RuntimeException('Migration command failed: ' . \EvolutionCMS\Facades\Console::output());
    }
};
prepareInstallerMigrations();
$run();
$repository = evo()->make('migration.repository');
expectInstall(count($repository->getRan()) === 65, 'Fresh install records the pinned 65 migrations');
$db->table('users')->insert(['id' => 1, 'username' => 'installer-fixture', 'password' => 'fixture']);
$db->table('user_attributes')->insert(['internalKey' => 1, 'fullname' => 'Preserve me', 'email' => 'fixture@example.test', 'role' => 1]);
$db->table('system_settings')->insert(['setting_name' => 'settings_version', 'setting_value' => '']);
$before = $db->table('user_attributes')->get()->toJson();
$schema->drop('migrations_install');
prepareInstallerMigrations();
expectInstall(count($repository->getRan()) === 65, 'Completed legacy CLI schema adopts only the pinned baseline');
$run();
$run();
expectInstall($before === $db->table('user_attributes')->get()->toJson(), 'Legacy update and repeat preserve user attributes');

$futureDirectory = sys_get_temp_dir() . '/evo-installer-future-' . bin2hex(random_bytes(5));
mkdir($futureDirectory);
$futureName = '2099_01_01_000000_add_installer_regression_marker';
file_put_contents($futureDirectory . '/' . $futureName . '.php', <<<'MIGRATION'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up() { \Illuminate\Support\Facades\Schema::table('users', function ($table) { $table->string('installer_regression_marker')->nullable(); }); }
    public function down() { \Illuminate\Support\Facades\Schema::table('users', function ($table) { $table->dropColumn('installer_regression_marker'); }); }
};
MIGRATION);
try {
    evo()->make('migrator')->run([$futureDirectory], ['step' => false]);
    expectInstall($schema->hasColumn('users', 'installer_regression_marker') && in_array($futureName, $repository->getRan()), 'A future migration remains pending and is executed and recorded');
    evo()->make('migrator')->run([$futureDirectory], ['step' => false]);
    expectInstall(count(array_keys($repository->getRan(), $futureName)) === 1, 'Repeat does not rerun a future migration');
} finally {
    unlink($futureDirectory . '/' . $futureName . '.php');
    rmdir($futureDirectory);
}
$schema->drop('migrations_install');
$db->table('system_settings')->where('setting_name', 'settings_version')->update(['setting_value' => '2.0']);
try {
    prepareInstallerMigrations();
    throw new LogicException('Unsupported version was accepted');
} catch (RuntimeException $e) {
    expectInstall(str_contains($e->getMessage(), 'unsupported stored version') && !$schema->hasTable('migrations_install'), 'Unknown version aborts before history or migrations change');
}
$db->table('system_settings')->where('setting_name', 'settings_version')->update(['setting_value' => '']);
$schema->table('site_templates', static function ($table) { $table->dropColumn('templatecontroller'); });
try {
    prepareInstallerMigrations();
    throw new LogicException('Partial schema was accepted');
} catch (RuntimeException $e) {
    expectInstall(str_contains($e->getMessage(), 'incomplete CE 3.1 schema') && !$schema->hasTable('migrations_install'), 'Partial schema aborts before history or migrations change');
}
expectInstall($before === $db->table('user_attributes')->get()->toJson(), 'Aborted updates also preserve user attributes');
echo 'INSTALLER_MIGRATIONS_COMPLETE ' . $checks . "\n";
