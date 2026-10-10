<?php
require __DIR__ . '/bootstrap.php';
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SiteHtmlsnippet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

$checks = [];
function check($name, callable $callback) {
    global $checks;
    try { $result = $callback(); if ($result === false) throw new RuntimeException('Assertion returned false'); $checks[]=['test'=>$name,'status'=>'PASS']; }
    catch (Throwable $e) { $checks[]=['test'=>$name,'status'=>'FAIL','error'=>get_class($e).': '.$e->getMessage()]; }
}
function same($actual, $expected) { if ($actual !== $expected) throw new RuntimeException(var_export($actual,true).' !== '.var_export($expected,true)); }

check('Illuminate 13 installed', fn()=>same(explode('.',ltrim(Composer\InstalledVersions::getPrettyVersion('illuminate/support'),'v'))[0], '13'));
check('Legacy global helpers and DocumentParser', fn()=>same(evo(), evolutionCMS()));
check('Container aliases', fn()=>same(app(Illuminate\Contracts\Foundation\Application::class), evo()));
check('Paths preserve legacy roots and support Laravel path arguments', function() use($modx) {same($modx->storagePath(), EVO_STORAGE_PATH);same($modx->storagePath('test'),EVO_STORAGE_PATH.'test');same($modx->langPath('en'),EVO_CORE_PATH.'lang/en');same($modx->configPath('app.php'),EVO_CORE_PATH.'config/app.php');});
check('Service provider registration idempotence and both force signatures', function() use($modx) {
    $provider = new class($modx) extends Illuminate\Support\ServiceProvider { public static $runs=0; public function register(){++self::$runs;} };
    $modx->register($provider);$modx->register($provider);same($provider::$runs,1);
    $modx->register($provider,true);same($provider::$runs,2);$modx->register($provider,[],true);same($provider::$runs,3);
});
check('Provider boot callbacks', function() use($modx){$done=false;$modx->booted(function()use(&$done){$done=true;});same($done,true);});
check('Termination callbacks', function() use($modx){$done=0;$modx->terminating(function()use(&$done){++$done;});$modx->terminate();$modx->terminate();same($done,1);});
check('Provider-specific boot callbacks', function()use($modx){$calls=[];$p=new class($modx) extends Illuminate\Support\ServiceProvider {};$p->booting(function()use(&$calls){$calls[]='before';});$p->booted(function()use(&$calls){$calls[]='after';});$modx->register($p);same($calls,['before','after']);});
check('Maintenance contract respects Evo status and lifecycle', function()use($modx){$status=$modx->getConfig('site_status');try{$modx->setConfig('site_status',1);same($modx->isDownForMaintenance(),false);$modx->maintenanceMode()->activate(['reason'=>'compatibility']);same($modx->isDownForMaintenance(),true);same($modx->maintenanceMode()->data(),['reason'=>'compatibility']);$modx->maintenanceMode()->deactivate();same($modx->isDownForMaintenance(),false);$modx->setConfig('site_status',0);same($modx->isDownForMaintenance(),true);}finally{$modx->maintenanceMode()->deactivate();$modx->setConfig('site_status',$status);}});
check('Doctrine cache bridge for MODxAPI',function()use($modx){$bridge=$modx['cache'];$bridge->save('compat-doctrine',['a'=>1],60);same($bridge->fetch('compat-doctrine'),['a'=>1]);$bridge->delete('compat-doctrine');});
check('Legacy DBAPI select/getRow/makeArray', function()use($modx){$rows=$modx->db->makeArray($modx->db->select('id,pagetitle',$modx->getFullTableName('site_content'),'id=1'));same((int)$rows[0]['id'],1);});
check('Native schema inspection', fn()=>same(Schema::hasColumn('site_content','pagetitle'),true));
check('Query builder raw expressions', fn()=>same((int)DB::table('site_content')->selectRaw('COUNT(*) AS n')->first()->n, SiteContent::withTrashed()->count()));
check('Legacy document API', fn()=>same((int)$modx->getDocument(1)['id'],1));
check('MODxAPI modResource legacy reader', function()use($modx){$doc=$modx->doc->edit(1);same((int)$doc->getID(),1);same($doc->get('pagetitle'),SiteContent::find(1)->pagetitle);});
check('MODxAPI modUsers legacy reader', function()use($modx){$user=$modx->user->edit(1);same((int)$user->getID(),1);same($user->get('username'),'admin');});
check('Eloquent relationships and custom collection', function(){ $page=SiteContent::findOrFail(1);same($page->children instanceof EvolutionCMS\Extensions\Collection,true);$page->tpl; });
check('Carbon timestamp conversion', fn()=>same(SiteContent::findOrFail(1)->created_at instanceof Carbon\CarbonInterface,true));
check('Evo now helper with numeric configuration', fn()=>same($modx->now() instanceof Carbon\CarbonInterface,true));
check('Blade escaping, loops and includes', function()use($modx){$dir=EVO_STORAGE_PATH.'compat-views';@mkdir($dir);file_put_contents($dir.'/child.blade.php','{{ $label }}');file_put_contents($dir.'/main.blade.php',"@foreach(\$items as \$item)@include('compat::child', ['label'=>\$item])@endforeach");try{$modx['view']->addNamespace('compat',$dir);same(trim($modx['view']->make('compat::main',['items'=>['<a>','b']])->render()),'&lt;a&gt;b');}finally{unlink($dir.'/main.blade.php');unlink($dir.'/child.blade.php');rmdir($dir);}});
check('Translations and validation', function(){same(Validator::make(['email'=>'broken'],['email'=>'required|email'])->fails(),true);same(Validator::make(['email'=>'a@example.test'],['email'=>'required|email'])->passes(),true);});
check('Cache arrays and expiry', function(){Cache::put('compat-test',['a'=>1],60);same(Cache::get('compat-test'),['a'=>1]);Cache::forget('compat-test');same(Cache::get('compat-test'),null);});
check('Flysystem storage write/read/list/copy/move/delete', function(){ $d=Storage::disk('storage');try{same($d->put('compat-test/a.txt','hello'),true);same($d->get('compat-test/a.txt'),'hello');$d->copy('compat-test/a.txt','compat-test/b.txt');$d->move('compat-test/b.txt','compat-test/c.txt');same(count($d->files('compat-test')),2);same($d->exists('compat-test/b.txt'),false);}finally{$d->deleteDirectory('compat-test');}});
check('Illuminate events bridged to Evo', function()use($modx){$modx['events']->listen('evolution.OnCompatProbe',fn($params)=>'hello '.$params['name']);same($modx->invokeEvent('OnCompatProbe',['name'=>'world']),['hello world']);});
check('Legacy placeholder parser', fn()=>same($modx->parseText('Hi [+name+]',['name'=>'Evo']),'Hi Evo'));

DB::beginTransaction();
try {
 check('Legacy DBAPI insert/update/delete and transaction', function()use($modx){$t=$modx->getFullTableName('site_htmlsnippets');$id=$modx->db->insert(['name'=>'CompatDb','snippet'=>'before'],$t);$modx->db->update(['snippet'=>'after'],$t,'id='.(int)$id);same($modx->db->getValue($modx->db->select('snippet',$t,'id='.(int)$id)),'after');$modx->db->delete($t,'id='.(int)$id);same($modx->db->getRecordCount($modx->db->select('*',$t,'id='.(int)$id)),0);});
 check('Eloquent page creation, save and soft delete/restore', function() { $page=SiteContent::create(['pagetitle'=>'Compatibility transient','alias'=>'compat-transient','published'=>1,'parent'=>0,'template'=>0]); $id=$page->id; $page->pagetitle='Changed';$page->save();same(SiteContent::find($id)->pagetitle,'Changed');$page->delete();same(SiteContent::find($id),null);$page->restore();same(SiteContent::find($id)->pagetitle,'Changed'); });
 check('Persisted legacy snippet parameters and DB access', function()use($modx){SiteSnippet::create(['name'=>'CompatProbe','snippet'=>'return $greeting . ":" . $modx->db->getValue($modx->db->select("pagetitle", $modx->getFullTableName("site_content"), "id=1"));','properties'=>'','category'=>0]);same($modx->runSnippet('CompatProbe',['greeting'=>'legacy']),'legacy:'.SiteContent::find(1)->pagetitle);});
 check('Persisted legacy chunk', function()use($modx){SiteHtmlsnippet::create(['name'=>'CompatChunk','snippet'=>'<b>[+label+]</b>']);same($modx->parseChunk('CompatChunk',['label'=>'works'],'[+','+]'),'<b>works</b>');});
 check('Persisted legacy plugin event and output', function()use($modx){SitePlugin::create(['name'=>'CompatPlugin','plugincode'=>'$modx->event->output("plugin:" . $value);','properties'=>'','disabled'=>0]);$modx->pluginEvent['OnCompatLegacy']=['CompatPlugin'];same($modx->invokeEvent('OnCompatLegacy',['value'=>'ok']),['plugin:ok']);});
} finally {DB::rollBack();}
check('Transaction rollback removed test data', fn()=>same(DB::table('site_snippets')->where('name','CompatProbe')->exists(),false));

file_put_contents(dirname(__DIR__).'/reports/compatibility.json',json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
foreach($checks as $result) echo $result['status'].' '.$result['test'].(isset($result['error'])?' — '.$result['error']:'')."\n";
$failed=count(array_filter($checks,fn($x)=>$x['status']==='FAIL'));
echo 'COMPATIBILITY_COMPLETE '.count($checks).' checks, '.$failed." failed\n";
exit($failed ? 1 : 0);
