<?php
require __DIR__ . '/bootstrap.php';
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Tester\CommandTester;

// This file has already verified the dedicated test database in bootstrap.php.
if (!$modx['migration.repository']->repositoryExists()) {
    $modx['migration.repository']->createRepository();
}

$root = dirname(__DIR__) . '/.local/console-test';
$files = $modx['files'];
$files->ensureDirectoryExists($root.'/source/nested');
$files->ensureDirectoryExists($root.'/migrations');
$files->put($root.'/source/nested/probe.txt','original');
$provider = new class($modx) extends ServiceProvider {
 public function boot() { $root=dirname(__DIR__).'/.local/console-test'; $this->publishes([$root.'/source'=>$root.'/published'],'compat-publish'); }
};
$modx->register($provider);
$console = new EvolutionCMS\Console($modx,$modx['events'],$modx->version());
function runCommand($name,$args=[]) { global $console; $t=new CommandTester($console->find($name)); $code=$t->execute($args,['interactive'=>false]);if($code!==0)throw new RuntimeException($name.': '.$t->getDisplay());echo $t->getDisplay(); }
function expect($value,$message){if(!$value)throw new RuntimeException($message);}
try {
 runCommand('vendor:publish',['--tag'=>['compat-publish']]);expect(file_get_contents($root.'/published/nested/probe.txt')==='original','Initial publish');
 $files->put($root.'/source/nested/probe.txt','changed');
 runCommand('vendor:publish',['--tag'=>['compat-publish']]);expect(file_get_contents($root.'/published/nested/probe.txt')==='original','Preserve existing publish');
 runCommand('vendor:publish',['--tag'=>['compat-publish'],'--force'=>true]);expect(file_get_contents($root.'/published/nested/probe.txt')==='changed','Forced publish');
 $files->put($root.'/migrations/2026_10_09_000001_compatibility_probe.php', <<<'MIGRATION'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('compat_migration_probe',function(Blueprint $t){$t->id();$t->string('label',30);});Schema::table('compat_migration_probe',function(Blueprint $t){$t->string('label',80)->nullable()->change();}); }
 public function down(): void {Schema::dropIfExists('compat_migration_probe');}
};
MIGRATION);
 runCommand('migrate',['--path'=>[$root.'/migrations'],'--realpath'=>true,'--force'=>true]);expect(Schema::hasTable('compat_migration_probe'),'Migration create');
 runCommand('migrate:rollback',['--path'=>[$root.'/migrations'],'--realpath'=>true,'--step'=>1,'--force'=>true]);expect(!Schema::hasTable('compat_migration_probe'),'Migration rollback');
 echo "CONSOLE_INTEGRATION_OK publish, preserve, force, migrate, change, rollback\n";
} finally { $files->deleteDirectory($root); }
